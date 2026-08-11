<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        // The session cookie can only be marked Secure once the app is
        // actually served over HTTPS — forcing it in local dev (plain
        // HTTP via `php artisan serve`) would silently break every
        // login. env('SESSION_SECURE_COOKIE') still wins when set
        // explicitly (e.g. behind a proxy that terminates TLS).
        if (app()->isProduction() && config('session.secure') === null) {
            Config::set('session.secure', true);
        }
    }

    /**
     * Rate limits for the JSON API (routes/api.php). Login has its own,
     * stricter limiter shared with the web UI (see
     * FortifyServiceProvider::configureRateLimiting) since both are the
     * same brute-force surface for the same account.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
