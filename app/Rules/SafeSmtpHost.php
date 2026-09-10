<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Keeps a user-supplied SMTP host from pointing back into our own network.
 *
 * The relay host is dialled with fsockopen() by the diagnostics screen, so
 * without this any signed-up account could use the platform as an internal port
 * scanner — 127.0.0.1, 169.254.169.254 (cloud metadata) and RFC1918 space all
 * included. It also rejects the DSN metacharacters that would otherwise let a
 * host string smuggle transport options past Transport::fromDsn().
 */
class SafeSmtpHost implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $host = is_string($value) ? trim($value) : '';

        if ($host === '') {
            $fail('The :attribute is required.');

            return;
        }

        // A hostname or bare IP literal, nothing else. This is what blocks
        // `smtp.example.com?verify_peer=0` and `user@evil.tld` from reaching the
        // DSN parser and quietly disabling certificate verification.
        if (! preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/', $host)
            && ! filter_var($host, FILTER_VALIDATE_IP)) {
            $fail('The :attribute must be a valid hostname or IP address.');

            return;
        }

        if (! config('mailflow.smtp.block_private_hosts', true)) {
            return;
        }

        if (str_ends_with(strtolower($host), '.local') || strtolower($host) === 'localhost') {
            $fail('The :attribute may not point at a private or local address.');

            return;
        }

        foreach ($this->resolve($host) as $ip) {
            if (! $this->isPublic($ip)) {
                $fail('The :attribute resolves to a private or reserved address, which is not allowed.');

                return;
            }
        }
    }

    /**
     * Every address the host resolves to — a name with one public and one
     * private A record must not slip through on the public one.
     *
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        $ips = [];

        foreach ($records as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        $ips = array_values(array_filter($ips));

        // A name that does not resolve cannot be shown to be safe, but it also
        // cannot reach anything — let the connection attempt fail instead of
        // blocking a relay whose DNS is briefly unavailable.
        return $ips;
    }

    public static function isPublic(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
