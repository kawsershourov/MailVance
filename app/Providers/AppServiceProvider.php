<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Blade-side counterparts to the `permission` / `role` route middleware,
        // so the UI can hide what the current user is not allowed to reach.
        Blade::if('permission', function (string ...$slugs) {
            return Auth::check() && Auth::user()->hasAnyPermission(...$slugs);
        });

        Blade::if('anyrole', function (string ...$slugs) {
            return Auth::check() && Auth::user()->hasRole(...$slugs);
        });

        $this->configureRateLimiting();
        $this->configurePasswordPolicy();

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Laravel 11 ships no RouteServiceProvider, so named limiters have to be
     * declared here before any route can reference them with `throttle:`.
     */
    protected function configureRateLimiting(): void
    {
        // Keyed on the submitted email *and* the IP: keying on email alone would
        // let an attacker lock a known victim out of their own account.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(3)->by($request->ip()));

        // Opens a socket to a user-supplied host, so it stays tight even though
        // the host itself is validated.
        RateLimiter::for('smtp-test', fn (Request $request) => Limit::perMinute(5)->by($this->actorKey($request)));

        // 200MB per upload — a handful per minute is already generous.
        RateLimiter::for('csv-upload', fn (Request $request) => Limit::perMinute(5)->by($this->actorKey($request)));

        RateLimiter::for('campaign-launch', fn (Request $request) => Limit::perMinute(10)->by($this->actorKey($request)));

        // CPU-bound rendering and analysis, driven straight from the editor.
        RateLimiter::for('preview', fn (Request $request) => Limit::perMinute(60)->by($this->actorKey($request)));

        // Outbound DNS lookups on a caller-supplied domain.
        RateLimiter::for('deliverability', fn (Request $request) => Limit::perMinute(20)->by($this->actorKey($request)));

        // Public, and legitimately hit once per recipient per open/click — so
        // generous, but not unbounded.
        RateLimiter::for('tracking', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        RateLimiter::for('unsubscribe', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }

    /** Per-user where we know who is calling, per-IP otherwise. */
    protected function actorKey(Request $request): string
    {
        return (string) ($request->user()?->id ?: $request->ip());
    }

    protected function configurePasswordPolicy(): void
    {
        Password::defaults(function () {
            $rule = Password::min(12)->mixedCase()->numbers();

            // The breach check calls out to haveibeenpwned, so it is skipped in
            // tests and local work where there may be no network.
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
