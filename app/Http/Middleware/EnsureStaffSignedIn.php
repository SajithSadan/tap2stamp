<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after AuthenticateStaffDevice: the device is approved, but a staff
 * member must also be signed in on it (name + PIN) before it can stamp or
 * look customers up. 403, not 401 - the device token itself is still fine,
 * so the scanner must not forget it.
 */
class EnsureStaffSignedIn
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = $request->attributes->get('staffDevice')->activeStaffMember();

        if (! $member) {
            return response()->json([
                'status' => 'error',
                'code' => 'staff_signed_out',
                'message' => 'Sign in with your PIN to continue.',
            ], 403);
        }

        $request->attributes->set('staffMember', $member);

        return $next($request);
    }
}
