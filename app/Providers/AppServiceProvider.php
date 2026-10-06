<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // Activity log: every admin / owner sign-in and sign-out (email or Google).
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                ActivityLogger::record('auth.signed_in', 'Signed in', null, null, null, ActivityLogger::user($event->user));
            }
        });
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                ActivityLogger::record('auth.signed_out', 'Signed out', null, null, null, ActivityLogger::user($event->user));
            }
        });

        // Production sits behind a TLS-terminating proxy (see trustProxies
        // in bootstrap/app.php) - generated URLs must always be https://, or
        // the staff scanner's camera (HTTPS-only) and assets break.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Per device, not per IP: every staff phone in a shop shares the
        // shop's Wi-Fi (one IP), so an IP limit would throttle the whole
        // counter because of one busy device. Keyed by the token's hash so the
        // raw bearer token never ends up in a cache key.
        RateLimiter::for('staff-scan', function (Request $request) {
            $token = $request->bearerToken();

            return Limit::perMinute(60)->by($token ? 'device:'.hash('sha256', $token) : 'ip:'.$request->ip());
        });

        // A 4-digit PIN is only 10,000 guesses - cap wrong-PIN attempts per
        // device hard, same per-device keying as staff-scan above.
        RateLimiter::for('staff-pin', function (Request $request) {
            $token = $request->bearerToken();

            return Limit::perMinute(5)->by($token ? 'pin:'.hash('sha256', $token) : 'ip:'.$request->ip());
        });
    }
}
