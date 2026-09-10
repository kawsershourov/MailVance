<?php

namespace App\Services;

use App\Models\Contact;

class TemplateRendererService
{
    private readonly TrackingUrlSigner $signer;

    /** The signer is stateless, so direct construction stays possible. */
    public function __construct(?TrackingUrlSigner $signer = null)
    {
        $this->signer = $signer ?? new TrackingUrlSigner;
    }

    /**
     * Replace merge tags in subject and body with contact data
     */
    public function render(string $content, ?Contact $contact = null, ?string $unsubscribeUrl = null): string
    {
        $firstName = $contact ? ($contact->first_name ?: 'Friend') : 'John';
        $lastName = $contact ? ($contact->last_name ?: '') : 'Doe';
        $email = $contact ? $contact->email : 'john.doe@example.com';
        $company = $contact ? ($contact->company ?: 'Acme Corp') : 'Acme Corp';
        $name = trim("{$firstName} {$lastName}") ?: $email;
        $unsub = $unsubscribeUrl ?: '#unsubscribe';

        $replacements = [
            '{{first_name}}' => htmlspecialchars($firstName),
            '{{last_name}}' => htmlspecialchars($lastName),
            '{{name}}' => htmlspecialchars($name),
            '{{email}}' => htmlspecialchars($email),
            '{{company}}' => htmlspecialchars($company),
            '{{unsubscribe_url}}' => $unsub,
        ];

        // Also check custom fields if contact has JSON
        if ($contact && is_array($contact->custom_fields)) {
            foreach ($contact->custom_fields as $key => $val) {
                $replacements['{{'.$key.'}}'] = htmlspecialchars((string) $val);
            }
        }

        return strtr($content, $replacements);
    }

    /**
     * Rewrite hyperlinks in HTML body to point to tracking endpoint
     */
    public function rewriteLinksForTracking(string $html, string $trackingToken): string
    {
        $appUrl = rtrim(config('app.url'), '/');
        $trackBase = "{$appUrl}/track/click/{$trackingToken}?url=";

        return preg_replace_callback('/<a\s+([^>]*?)href=["\'](https?:\/\/[^"\']+)["\']([^>]*)>/i', function ($matches) use ($trackBase) {
            $originalUrl = htmlspecialchars_decode($matches[2], ENT_QUOTES);

            // Skip tracking unsubscribe URLs
            if (str_contains($originalUrl, '/unsubscribe/')) {
                return $matches[0];
            }

            // The signature is what lets /track/click tell a link this platform
            // generated from one an attacker appended, so the endpoint cannot be
            // reused as an open redirect on the sending domain.
            $trackedUrl = $trackBase.urlencode($originalUrl)
                .'&sig='.$this->signer->sign($originalUrl);

            return '<a '.$matches[1].'href="'.htmlspecialchars($trackedUrl, ENT_QUOTES).'"'.$matches[3].'>';
        }, $html);
    }
}
