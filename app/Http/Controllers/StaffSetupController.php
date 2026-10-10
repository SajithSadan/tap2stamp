<?php

namespace App\Http\Controllers;

use App\Models\StaffDevice;
use Inertia\Inertia;
use Inertia\Response;

class StaffSetupController extends Controller
{
    /**
     * Onboards a staff phone: this route IS the login step (there's no
     * session yet), so unlike everything behind AuthenticateStaffDevice,
     * the token here is validated by hashing the URL param directly. The
     * page itself stores it into localStorage client-side once rendered.
     */
    public function show(string $token): Response
    {
        $device = StaffDevice::where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->with('shop')
            ->first();

        // A blocked shop's device can't be set up (the link stays valid for later).
        $blocked = $device?->shop->isBlocked() ?? false;

        return Inertia::render('Staff/Setup', [
            'valid' => $device !== null && ! $blocked,
            'blocked' => $blocked,
            'token' => $device && ! $blocked ? $token : null,
            'shopName' => $device?->shop->name,
            'deviceName' => $device?->name,
        ]);
    }
}
