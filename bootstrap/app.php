<?php

use App\Http\Middleware\AuthenticateStaffDevice;
use App\Http\Middleware\EnsureStaffSignedIn;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Global (not just the web group) so error responses for unmatched
        // routes carry the headers too.
        $middleware->append(SecurityHeaders::class);

        // Trust X-Forwarded-* from any proxy in front of the app - both an
        // HTTPS tunnel (ngrok/Cloudflare Tunnel, needed to test the staff
        // scanner's camera on a real phone) and Hostinger's production
        // front-end terminate TLS and forward plain HTTP. Without this,
        // Laravel doesn't know the original request was secure, so it
        // generates http:// asset URLs on an https:// page - mixed content,
        // blocked by the browser.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'staff.auth' => AuthenticateStaffDevice::class,
            'staff.signed-in' => EnsureStaffSignedIn::class,
        ]);

        // Hit by curl/CI after a deploy, not a browser session — no CSRF
        // cookie exists for it to check. Protected instead by a bearer
        // token (see App\Http\Controllers\DeployController). Same reasoning
        // for api/staff/* - the scanner authenticates with a bearer token
        // (AuthenticateStaffDevice), not a session, so there's no CSRF
        // cookie for those requests either.
        $middleware->validateCsrfTokens(except: [
            'deploy/migrate',
            'deploy/seed-admin',
            'api/staff/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Friendly Inertia error page instead of Laravel's default one for
        // browser visits. JSON callers (the card/scan fetch endpoints) keep
        // their normal JSON error bodies - the pages calling them handle
        // those themselves. 500/503 are left alone while debugging, so the
        // real stack trace is still visible locally.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return $response;
            }

            $friendly = [403, 404, 419, 429];

            if (! config('app.debug')) {
                $friendly = [...$friendly, 500, 503];
            }

            if (! in_array($status, $friendly, true)) {
                return $response;
            }

            return Inertia::render('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
