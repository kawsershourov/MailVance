<?php

namespace App\Services;

/**
 * Turns design-panel settings into email-safe HTML.
 *
 * Email clients (Outlook especially) ignore most modern CSS, so the output uses
 * nested tables with inline styles rather than flex/grid and a <style> block.
 */
class EmailTemplateBuilderService
{
    /** Font stacks that are safe across mail clients. */
    public const FONTS = [
        'Arial' => "Arial, 'Helvetica Neue', Helvetica, sans-serif",
        'Helvetica' => "'Helvetica Neue', Helvetica, Arial, sans-serif",
        'Verdana' => 'Verdana, Geneva, sans-serif',
        'Tahoma' => 'Tahoma, Verdana, Segoe, sans-serif',
        'Trebuchet MS' => "'Trebuchet MS', Helvetica, sans-serif",
        'Georgia' => "Georgia, 'Times New Roman', serif",
        'Times New Roman' => "'Times New Roman', Times, serif",
        'Courier New' => "'Courier New', Courier, monospace",
    ];

    public const DEFAULTS = [
        'logo_width' => 160,
        'logo_align' => 'center',
        'brand_color' => '#4f46e5',
        'background_color' => '#f1f5f9',
        'card_color' => '#ffffff',
        'heading_color' => '#0f172a',
        'text_color' => '#334155',
        'muted_color' => '#94a3b8',
        'button_text_color' => '#ffffff',
        'font_family' => 'Arial',
        'font_size' => 16,
        'heading_size' => 26,
        'content_width' => 600,
        'radius' => 12,
        'align' => 'left',
        'sections' => [
            'logo' => ['align' => 'center', 'pt' => 32, 'pr' => 32, 'pb' => 0, 'pl' => 32],
            'heading' => ['align' => 'left', 'pt' => 32, 'pr' => 32, 'pb' => 0, 'pl' => 32],
            'body' => ['align' => 'left', 'pt' => 16, 'pr' => 32, 'pb' => 0, 'pl' => 32],
            'button' => ['align' => 'left', 'pt' => 24, 'pr' => 32, 'pb' => 0, 'pl' => 32],
            'divider' => ['align' => 'left', 'pt' => 32, 'pr' => 32, 'pb' => 0, 'pl' => 32],
            'footer' => ['align' => 'center', 'pt' => 24, 'pr' => 32, 'pb' => 32, 'pl' => 32],
        ],
        // Mobile overrides. A null entry means "inherit the desktop value", so
        // editing the phone layout never rewrites the desktop design.
        'mobile' => [
            'font_size' => null,
            'heading_size' => null,
            'sections' => [
                'logo' => ['align' => null, 'pt' => null, 'pr' => null, 'pb' => null, 'pl' => null],
                'heading' => ['align' => null, 'pt' => null, 'pr' => null, 'pb' => null, 'pl' => null],
                'body' => ['align' => null, 'pt' => null, 'pr' => null, 'pb' => null, 'pl' => null],
                'button' => ['align' => null, 'pt' => null, 'pr' => null, 'pb' => null, 'pl' => null],
                'divider' => ['align' => null, 'pt' => null, 'pr' => null, 'pb' => null, 'pl' => null],
                'footer' => ['align' => null, 'pt' => null, 'pr' => null, 'pb' => null, 'pl' => null],
            ],
        ],
        'preheader' => '',
        'heading' => 'Hello {{first_name}}!',
        'body_text' => "We're glad to have you with us. Here's what's new this month at {{company}}.",
        'show_button' => true,
        'button_text' => 'Read More',
        'button_url' => 'https://example.com',
        'show_divider' => true,
        'footer_text' => 'You are receiving this because you subscribed with {{email}}.',
        'show_address' => false,
        'address_text' => 'Your Company · 123 Street · City, Country',
    ];

    public function normalize(array $design): array
    {
        $d = array_merge(self::DEFAULTS, array_filter(
            $design,
            fn ($v) => $v !== null && $v !== ''
        ));

        // Booleans arrive as "0"/"1"/"true" from JSON or form input.
        foreach (['show_button', 'show_divider', 'show_address', 'has_logo'] as $flag) {
            $d[$flag] = filter_var($design[$flag] ?? ($d[$flag] ?? false), FILTER_VALIDATE_BOOLEAN);
        }

        foreach (['logo_width', 'font_size', 'heading_size', 'content_width', 'radius'] as $n) {
            $d[$n] = (int) $d[$n];
        }

        if (! isset(self::FONTS[$d['font_family']])) {
            $d['font_family'] = 'Arial';
        }

        // The logo panel historically wrote a top-level `logo_align` that the
        // renderer never read, so the control silently did nothing. Fold it in
        // as the logo section's alignment unless that was set explicitly.
        $incomingSections = is_array($design['sections'] ?? null) ? $design['sections'] : [];
        if (! isset($incomingSections['logo']['align']) && isset($design['logo_align'])) {
            $incomingSections['logo']['align'] = $design['logo_align'];
        }

        $d['button_url'] = $this->safeLinkUrl($d['button_url'] ?? null);

        foreach (['brand_color', 'background_color', 'card_color', 'heading_color', 'text_color', 'muted_color', 'button_text_color'] as $colorKey) {
            $d[$colorKey] = $this->safeColor($d[$colorKey] ?? null, self::DEFAULTS[$colorKey]);
        }

        $d['sections'] = $this->normalizeSections($incomingSections);

        // Keep the two in step so the editor never shows a stale value.
        $d['logo_align'] = $d['sections']['logo']['align'];
        $d['mobile'] = $this->normalizeMobile($design['mobile'] ?? []);

        return $d;
    }

    /**
     * Confines the call-to-action link to schemes a recipient can safely follow.
     *
     * `htmlspecialchars` leaves `javascript:` completely intact, so without a
     * scheme check the button becomes a live script URL — in the editor preview
     * and in the mail that actually goes out.
     */
    private function safeLinkUrl(?string $url): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        // Merge tags are resolved later, so a URL that is entirely a tag is fine.
        if (str_starts_with($url, '{{') && str_ends_with($url, '}}')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return $url;
        }

        // A bare domain is a common and harmless thing to type.
        if ($scheme === '' && ! str_contains($url, ':')) {
            return 'https://'.ltrim($url, '/');
        }

        return self::DEFAULTS['button_url'];
    }

    /**
     * Colours land inside `style="..."` attributes. Quotes are already escaped,
     * so the attribute cannot be broken out of — but an unvalidated value can
     * still append a declaration such as `#fff;background-image:url(...)` and
     * pull in an external resource. Only real colour syntax is accepted.
     */
    private function safeColor(?string $value, string $fallback): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $fallback;
        }

        $isHex = (bool) preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value);
        $isFunc = (bool) preg_match('/^rgba?\(\s*[0-9.]+\s*,\s*[0-9.]+\s*,\s*[0-9.]+\s*(?:,\s*[0-9.]+\s*)?\)$/', $value);
        $isNamed = (bool) preg_match('/^[a-zA-Z]{3,20}$/', $value);

        return ($isHex || $isFunc || $isNamed) ? $value : $fallback;
    }

    /**
     * Clamp the mobile overrides. Anything left null keeps inheriting desktop,
     * which is what makes the two views independent.
     */
    private function normalizeMobile(array $incoming): array
    {
        $clampSize = function ($value, int $min, int $max) {
            if ($value === null || $value === '') {
                return null;
            }

            return max($min, min($max, (int) $value));
        };

        $out = [
            'font_size' => $clampSize($incoming['font_size'] ?? null, 10, 32),
            'heading_size' => $clampSize($incoming['heading_size'] ?? null, 14, 48),
            'sections' => [],
        ];

        $sections = is_array($incoming['sections'] ?? null) ? $incoming['sections'] : [];

        foreach (array_keys(self::DEFAULTS['sections']) as $name) {
            $given = is_array($sections[$name] ?? null) ? $sections[$name] : [];

            $align = $given['align'] ?? null;
            $out['sections'][$name]['align'] = in_array($align, ['left', 'center', 'right'], true) ? $align : null;

            foreach (['pt', 'pr', 'pb', 'pl'] as $side) {
                $value = $given[$side] ?? null;
                $out['sections'][$name][$side] = ($value === null || $value === '')
                    ? null
                    : max(0, min(200, (int) $value));
            }
        }

        return $out;
    }

    /** True when the design carries at least one mobile override. */
    public function hasMobileOverrides(array $normalized): bool
    {
        $m = $normalized['mobile'];

        if ($m['font_size'] !== null || $m['heading_size'] !== null) {
            return true;
        }

        foreach ($m['sections'] as $section) {
            foreach ($section as $value) {
                if ($value !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Clamp each section's alignment and padding to sane, email-safe values. */
    private function normalizeSections(array $incoming): array
    {
        $out = [];

        foreach (self::DEFAULTS['sections'] as $name => $defaults) {
            $given = is_array($incoming[$name] ?? null) ? $incoming[$name] : [];

            $align = $given['align'] ?? $defaults['align'];
            $out[$name]['align'] = in_array($align, ['left', 'center', 'right'], true) ? $align : $defaults['align'];

            foreach (['pt', 'pr', 'pb', 'pl'] as $side) {
                $value = $given[$side] ?? $defaults[$side];
                $out[$name][$side] = max(0, min(200, (int) $value));
            }
        }

        return $out;
    }

    /**
     * Builds the <style> block that applies the mobile overrides.
     *
     * Media queries are the only way to vary a design by viewport in email, and
     * they must beat the inline styles the blocks carry, hence !important.
     * Outlook desktop ignores the whole block, which is correct: it is a
     * desktop client and keeps the desktop design.
     */
    private function mobileCss(array $d): string
    {
        $m = $d['mobile'];

        // A fixed-width card does not shrink on its own — without this the
        // whole design overflows the screen on a phone, which is what makes
        // the mobile view look nothing like the desktop one.
        $rules = [
            '.mf-card{width:100% !important;}',
        ];

        if ($m['font_size'] !== null) {
            $rules[] = '.mf-body p{font-size:'.$m['font_size'].'px !important;}';
            $rules[] = '.mf-btn a{font-size:'.$m['font_size'].'px !important;}';
        }

        if ($m['heading_size'] !== null) {
            $rules[] = '.mf-heading h1{font-size:'.$m['heading_size'].'px !important;line-height:1.25 !important;}';
        }

        foreach ($m['sections'] as $name => $section) {
            $declarations = [];

            // Padding is all-or-nothing in CSS shorthand, so any overridden side
            // is emitted alongside the desktop value for the other three.
            $sides = array_filter(
                ['pt', 'pr', 'pb', 'pl'],
                fn ($side) => $section[$side] !== null
            );

            if ($sides !== []) {
                $resolved = [];
                foreach (['pt', 'pr', 'pb', 'pl'] as $side) {
                    $resolved[$side] = $section[$side] ?? $d['sections'][$name][$side];
                }
                $declarations[] = "padding:{$resolved['pt']}px {$resolved['pr']}px {$resolved['pb']}px {$resolved['pl']}px !important";
            }

            if ($section['align'] !== null) {
                $declarations[] = 'text-align:'.$section['align'].' !important';
            }

            if ($declarations !== []) {
                $rules[] = '.mf-'.$name.'{'.implode(';', $declarations).';}';
            }

            // A nested table ignores the parent's text-align, so the button's
            // wrapper is made inline-block for the alignment to take hold.
            if ($name === 'button' && $section['align'] !== null) {
                $rules[] = '.mf-btn-table{display:inline-block !important;}';
            }

            // Same reason as logoMargin(): text-align alone cannot move it.
            if ($name === 'logo' && $section['align'] !== null) {
                $rules[] = '.mf-logo-img{'.rtrim($this->logoMargin($section['align']), ';').' !important;}';
            }
        }

        return '
<style type="text/css">
@media only screen and (max-width:600px){
'.implode("\n", $rules).'
}
</style>';
    }

    /**
     * Horizontal placement for the logo.
     *
     * The logo is display:block so it keeps its own line in every client, and a
     * block element ignores the cell's align attribute — only auto margins move
     * it. Without this the logo sits hard left whatever the setting says.
     */
    private function logoMargin(string $align): string
    {
        return match ($align) {
            'center' => 'margin:0 auto;',
            'right' => 'margin:0 0 0 auto;',
            default => 'margin:0;',
        };
    }

    /** Inline padding declaration for one section. */
    private function pad(array $d, string $section): string
    {
        $s = $d['sections'][$section];

        return "padding:{$s['pt']}px {$s['pr']}px {$s['pb']}px {$s['pl']}px;";
    }

    private function sectionAlign(array $d, string $section): string
    {
        return $d['sections'][$section]['align'];
    }

    /**
     * @param  bool  $forPreview  swap the cid: logo reference for a browser-visible URL
     */
    public function render(array $design, ?string $logoPreviewUrl = null, bool $forPreview = false): string
    {
        $d = $this->normalize($design);
        $font = self::FONTS[$d['font_family']];
        $width = $d['content_width'];

        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        // Logo: cid: in real mail (embedded part), a served URL while editing.
        $logoBlock = '';
        if (! empty($d['has_logo'])) {
            $src = $forPreview ? ($logoPreviewUrl ?: '') : 'cid:mailflow-logo';
            if ($src !== '') {
                $logoBlock = '
            <tr>
                <td class="mf-logo" align="'.$e($this->sectionAlign($d, 'logo')).'" style="'.$this->pad($d, 'logo').'">
                    <img class="mf-logo-img" src="'.$e($src).'" width="'.$d['logo_width'].'" alt="" style="display:block;'.$this->logoMargin($this->sectionAlign($d, 'logo')).'border:0;outline:none;text-decoration:none;width:'.$d['logo_width'].'px;max-width:100%;height:auto;" />
                </td>
            </tr>';
            }
        }

        $preheader = '';
        if (! empty($d['preheader'])) {
            $preheader = '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:'.$e($d['background_color']).';">'
                .$e($d['preheader']).'</div>';
        }

        $headingBlock = '';
        if (! empty($d['heading'])) {
            $headingBlock = '
            <tr>
                <td class="mf-heading" align="'.$e($this->sectionAlign($d, 'heading')).'" style="'.$this->pad($d, 'heading').'">
                    <h1 style="margin:0;font-family:'.$font.';font-size:'.$d['heading_size'].'px;line-height:1.3;font-weight:bold;color:'.$e($d['heading_color']).';">'
                        .$e($d['heading']).'</h1>
                </td>
            </tr>';
        }

        $bodyBlock = '';
        if (! empty($d['body_text'])) {
            $paragraphs = preg_split('/\n{2,}/', trim((string) $d['body_text']));
            $html = '';
            foreach ($paragraphs as $p) {
                $html .= '<p style="margin:0 0 16px 0;font-family:'.$font.';font-size:'.$d['font_size'].'px;line-height:1.6;color:'.$e($d['text_color']).';">'
                    .nl2br($e(trim($p))).'</p>';
            }
            $bodyBlock = '
            <tr>
                <td class="mf-body" align="'.$e($this->sectionAlign($d, 'body')).'" style="'.$this->pad($d, 'body').'">'.$html.'</td>
            </tr>';
        }

        $buttonBlock = '';
        if ($d['show_button'] && ! empty($d['button_text'])) {
            // Table-wrapped anchor: the only button markup Outlook renders reliably.
            $buttonBlock = '
            <tr>
                <td class="mf-button mf-btn" align="'.$e($this->sectionAlign($d, 'button')).'" style="'.$this->pad($d, 'button').'">
                    <table class="mf-btn-table" role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse:separate;">
                        <tr>
                            <td align="center" bgcolor="'.$e($d['brand_color']).'" style="border-radius:'.$d['radius'].'px;">
                                <a href="'.$e($d['button_url']).'" target="_blank" style="display:inline-block;padding:14px 28px;font-family:'.$font.';font-size:'.$d['font_size'].'px;font-weight:bold;color:'.$e($d['button_text_color']).';text-decoration:none;border-radius:'.$d['radius'].'px;">'
                                    .$e($d['button_text']).'</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>';
        }

        $dividerBlock = $d['show_divider'] ? '
            <tr>
                <td class="mf-divider" style="'.$this->pad($d, 'divider').'">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                        <tr><td style="border-top:1px solid #e2e8f0;font-size:0;line-height:0;">&nbsp;</td></tr>
                    </table>
                </td>
            </tr>' : '';

        $addressBlock = $d['show_address'] && ! empty($d['address_text'])
            ? '<p style="margin:8px 0 0 0;font-family:'.$font.';font-size:12px;line-height:1.5;color:'.$e($d['muted_color']).';">'.$e($d['address_text']).'</p>'
            : '';

        $footerBlock = '
            <tr>
                <td class="mf-footer" align="'.$e($this->sectionAlign($d, 'footer')).'" style="'.$this->pad($d, 'footer').'">
                    <p style="margin:0;font-family:'.$font.';font-size:12px;line-height:1.5;color:'.$e($d['muted_color']).';">'
                        .$e($d['footer_text']).'</p>
                    '.$addressBlock.'
                    <p style="margin:12px 0 0 0;font-family:'.$font.';font-size:12px;line-height:1.5;">
                        <a href="{{unsubscribe_url}}" style="color:'.$e($d['muted_color']).';text-decoration:underline;">Unsubscribe</a>
                    </p>
                </td>
            </tr>';

        return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Email</title>'.$this->mobileCss($d).'
</head>
<body style="margin:0;padding:0;background-color:'.$e($d['background_color']).';">
'.$preheader.'
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:'.$e($d['background_color']).';">
    <tr>
        <td align="center" style="padding:32px 12px;">
            <table class="mf-card" role="presentation" border="0" cellpadding="0" cellspacing="0" width="'.$width.'" style="width:'.$width.'px;max-width:100%;background-color:'.$e($d['card_color']).';border-radius:'.$d['radius'].'px;overflow:hidden;">'
                .$logoBlock
                .$headingBlock
                .$bodyBlock
                .$buttonBlock
                .$dividerBlock
                .$footerBlock.'
            </table>
        </td>
    </tr>
</table>
</body>
</html>';
    }

    /** Plain-text alternative, so the email is not HTML-only (a spam signal). */
    public function renderText(array $design): string
    {
        $d = $this->normalize($design);
        $parts = array_filter([
            $d['heading'] ?? '',
            $d['body_text'] ?? '',
            $d['show_button'] && ! empty($d['button_text']) ? "{$d['button_text']}: {$d['button_url']}" : '',
            $d['footer_text'] ?? '',
            'Unsubscribe: {{unsubscribe_url}}',
        ]);

        return implode("\n\n", array_map(fn ($p) => trim(strip_tags((string) $p)), $parts));
    }
}
