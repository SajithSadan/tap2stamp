<?php

namespace App\Http\Middleware;

use App\Models\StaffDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the staff scanner API - not session/CSRF based (see
 * the CSRF-exempt list in bootstrap/app.php). The token itself is never
 * stored; only its sha256 hash is, so a leaked DB doesn't leak usable
 * tokens (same pattern as the token's original creation in
 * StaffDeviceController).
 */
class AuthenticateStaffDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        $device = $token
            ? StaffDevice::where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->first()
            : null;

        if (! $device) {
            return response()->json([
                'status' => 'error',
                'code' => 'unauthenticated',
                'message' => 'This device is not set up or has been revoked.',
            ], 401);
        }

        $device->update(['last_used_at' => now()]);
        $request->attributes->set('staffDevice', $device);

        return $next($request);
    }
}
