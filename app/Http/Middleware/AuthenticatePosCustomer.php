<?php

namespace App\Http\Middleware;

use App\Models\PosCustomer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the billing desktop app (/api/pos/*). Only the
 * token's sha256 is stored, like staff devices. Sets the `posCustomer`
 * request attribute.
 */
class AuthenticatePosCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $customer = $token ? PosCustomer::where('api_token_hash', hash('sha256', $token))->first() : null;

        if (! $customer) {
            return response()->json([
                'status' => 'error',
                'code' => 'unauthenticated',
                'message' => 'Please sign in again.',
            ], 401);
        }

        $request->attributes->set('posCustomer', $customer);

        return $next($request);
    }
}
