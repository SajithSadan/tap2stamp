<?php

use App\Http\Middleware\AuthenticateStaffDevice;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        //
    })->create();
