<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SiteSetting extends Model
{
    protected $fillable = [
        'site_title',
        'tagline',
        'company_name',
        'support_email',
        'brand_color',
        'logo_path',
        'favicon_path',
    ];

    /** The single settings row, created on first read. Cached indefinitely. */
    public static function current(): self
    {
        return Cache::rememberForever('site_settings', fn () => static::query()->firstOrCreate(['id' => 1]));
    }

    /** Call after every write so the cached row reflects the change. */
    public static function forget(): void
    {
        Cache::forget('site_settings');
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path).'?v='.$this->updated_at?->timestamp;
    }

    public function faviconUrl(): string
    {
        if ($this->favicon_path) {
            return Storage::disk('public')->url($this->favicon_path).'?v='.$this->updated_at?->timestamp;
        }

        // No custom favicon uploaded: render the default "M" mark in the
        // current brand color (or the indigo default) as a data URI, rather
        // than serving the static SVG file, so a Brand Color change is
        // reflected in the browser tab without a favicon upload.
        return $this->defaultFaviconDataUri();
    }

    protected function defaultFaviconDataUri(): string
    {
        [$r, $g, $b] = explode(' ', $this->brandPaletteRgb()[600]);
        $hex = sprintf('#%02x%02x%02x', $r, $g, $b);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="64" height="64">'
            .'<rect width="24" height="24" rx="6" fill="'.$hex.'"/>'
            .'<path d="M5 18.5 V5.5 L12 12.5 L19 5.5 V18.5" stroke="#ffffff" stroke-width="2.4" '
            .'stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function displayTitle(): string
    {
        return $this->site_title ?: 'MailVance';
    }

    public function displayTagline(): string
    {
        return $this->tagline ?: 'Enterprise Email Marketing & Sender';
    }

    /**
     * The `brand-50`..`brand-950` Tailwind ramp as "R G B" strings, keyed by
     * shade, for injection into CSS custom properties. Returns the app's
     * built-in indigo palette when no custom color is set.
     *
     * @return array<int, string>
     */
    public function brandPaletteRgb(): array
    {
        $default = [
            50 => '238 242 255', 100 => '224 231 255', 200 => '199 210 254',
            300 => '165 180 252', 400 => '129 140 248', 500 => '99 102 241',
            600 => '79 70 229', 700 => '67 56 202', 800 => '55 48 163',
            900 => '49 46 129', 950 => '30 27 75',
        ];

        if (! $this->brand_color || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $this->brand_color)) {
            return $default;
        }

        return static::deriveBrandPalette($this->brand_color);
    }

    /**
     * Builds a full 11-stop shade ramp from a single admin-picked color,
     * which stands in for the 600 stop (Tailwind's default button/accent
     * weight — matching where `bg-brand-600` etc. are used throughout the
     * app).
     *
     * Tailwind's named palettes (indigo, blue, emerald, ...) apply nearly the
     * same *lightness offset* and the same saturation *ratio* at each stop
     * relative to their own 600 stop — only hue, and the picked color's own
     * lightness/saturation level, differ between colors. So the app's own
     * indigo ramp's offsets/ratios are reused here, applied on top of the
     * admin's color, to produce a ramp that looks native and still resembles
     * the color they actually picked at the 600 stop.
     *
     * @return array<int, string>
     */
    protected static function deriveBrandPalette(string $hex): array
    {
        // Indigo's own lightness at each stop, expressed as an offset from
        // its 600 stop (58.6%) — e.g. 50 is 38.1 points lighter than 600.
        $lightnessOffset = [
            50 => 38.1, 100 => 35.3, 200 => 30.2, 300 => 23.2, 400 => 15.3,
            500 => 8.1, 600 => 0.0, 700 => -8.0, 800 => -17.2, 900 => -24.3, 950 => -38.6,
        ];

        $saturationRatio = [
            50 => 1.327, 100 => 1.327, 200 => 1.280, 300 => 1.240, 400 => 1.187,
            500 => 1.107, 600 => 1.0, 700 => 0.768, 800 => 0.723, 900 => 0.629, 950 => 0.625,
        ];

        [$hue, $saturation, $lightness] = static::hexToHsl($hex);

        $palette = [];

        foreach ($lightnessOffset as $stop => $offset) {
            $shadeSaturation = max(0.0, min(100.0, $saturation * $saturationRatio[$stop]));
            $shadeLightness = max(0.0, min(100.0, $lightness + $offset));
            $palette[$stop] = static::hslToRgbString($hue, $shadeSaturation, $shadeLightness);
        }

        return $palette;
    }

    /** @return array{0: float, 1: float, 2: float} [hue in degrees, saturation 0-100, lightness 0-100] */
    protected static function hexToHsl(string $hex): array
    {
        [$r, $g, $b] = static::hexToRgbFloats($hex);

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l * 100];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => fmod(($g - $b) / $d, 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        } * 60;

        if ($h < 0) {
            $h += 360;
        }

        return [$h, $s * 100, $l * 100];
    }

    /** @return array{0: float, 1: float, 2: float} */
    protected static function hexToRgbFloats(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    protected static function hslToRgbString(float $h, float $s, float $l): string
    {
        $s /= 100;
        $l /= 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0.0],
            $h < 120 => [$x, $c, 0.0],
            $h < 180 => [0.0, $c, $x],
            $h < 240 => [0.0, $x, $c],
            $h < 300 => [$x, 0.0, $c],
            default => [$c, 0.0, $x],
        };

        return implode(' ', [
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        ]);
    }
}
