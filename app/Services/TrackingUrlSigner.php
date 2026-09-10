<?php

namespace App\Services;

/**
 * Signs the `url` parameter carried by the click-tracking endpoint.
 *
 * Campaign links legitimately point anywhere on the web, so a destination
 * allowlist is not an option — instead every URL this platform rewrites is
 * stamped with an HMAC, and the redirect refuses anything it did not stamp.
 * Without it, /track/click is an open redirect on the sending domain: exactly
 * the primitive a phisher wants, and the fastest way to burn a mail domain's
 * reputation.
 */
class TrackingUrlSigner
{
    /** Truncated to 128 bits — plenty against forgery, and keeps links short. */
    private const SIGNATURE_BYTES = 16;

    public function sign(string $url): string
    {
        $digest = hash_hmac('sha256', $url, $this->key(), true);

        return rtrim(strtr(base64_encode(substr($digest, 0, self::SIGNATURE_BYTES)), '+/', '-_'), '=');
    }

    public function verify(string $url, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($url), $signature);
    }

    /**
     * Only http(s) may ever be redirected to. `FILTER_VALIDATE_URL` alone is a
     * syntax check and happily passes schemes a browser will execute.
     */
    public function isRedirectable(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && parse_url($url, PHP_URL_HOST) !== null;
    }

    /** The app key, base64-decoded when it carries Laravel's usual prefix. */
    private function key(): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7), true);
        }

        return $key;
    }
}
