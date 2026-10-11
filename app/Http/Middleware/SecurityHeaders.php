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
    /**
     * The only pages search engines may list: the landing page and shops'
     * public menus. Everything else (sign-in / sign-up, customer cards,
     * signed-in areas, JSON) says noindex - more reliable than robots.txt,
     * which only stops crawling, not a linked URL being listed.
     */
    public const INDEXABLE_ROUTES = ['home', 'menu.show'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        // Cross-origin requests (e.g. Google Fonts) only ever see our origin,
        // never the path - /staff/setup/{token} carries a secret in its URL.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Camera for the scanners, microphone for the menu's voice command (AiStockCommand) - only from us.
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=()');

        // Only production's public pages may be indexed; staging / local never.
        if (! app()->isProduction() || ! $request->routeIs(...self::INDEXABLE_ROUTES)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
