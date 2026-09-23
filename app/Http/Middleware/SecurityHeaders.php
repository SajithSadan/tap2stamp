<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser hardening on every response. No CSP yet - Google Fonts,
 * Pusher's websocket and Vite's dev server would each need allow-listing,
 * and a wrong CSP silently breaks the scanner, so it's left as a later step.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        // Cross-origin requests (e.g. Google Fonts) only ever see our origin,
        // never the path - /staff/setup/{token} carries a secret in its URL.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Only the staff scanner needs a device API (camera), and only from us.
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        return $response;
    }
}
