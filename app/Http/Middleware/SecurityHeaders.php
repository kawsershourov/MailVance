<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline response headers for every web route.
 *
 * The app had none at all, which left the console framable (clickjacking), let
 * browsers sniff a served upload's type, and leaked full campaign URLs in the
 * Referer header when /track/click redirected to a third-party site.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), interest-cohort=()'
        );

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->policy());
        }

        // HSTS is only meaningful over TLS, and pinning it on a plain-HTTP dev
        // box would strand the developer on a scheme they cannot serve.
        if ($request->secure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * The template editor is Alpine-driven with inline handlers and inline
     * component state, and Tailwind ships arbitrary inline styles, so
     * 'unsafe-inline' is currently load-bearing for script and style. Everything
     * else is locked down — notably `frame-ancestors 'none'` (the modern
     * X-Frame-Options) and `object-src 'none'`.
     */
    protected function policy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            // Campaign artwork and template logos legitimately come from anywhere.
            "img-src 'self' data: https: http:",
            "connect-src 'self'",
            // The email preview is rendered into a sandboxed same-document frame.
            "frame-src 'self'",
        ]);
    }
}
