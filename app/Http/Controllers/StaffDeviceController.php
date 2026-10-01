<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffDeviceRequest;
use App\Models\StaffDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StaffDeviceController extends Controller
{
    public function store(StoreStaffDeviceRequest $request): RedirectResponse
    {
        // The plain token is only ever available here, flashed once - only
        // its hash is persisted (see the staff_devices migration).
        $token = Str::random(64);

        $request->user()->shop->staffDevices()->create([
            'name' => $request->string('name')->value(),
            'token_hash' => hash('sha256', $token),
        ]);

        return redirect()->route('dashboard.staff')
            ->with('staffToken', $token)
            ->with('staffDeviceName', $request->string('name')->value());
    }

    public function setupLink(Request $request, StaffDevice $staffDevice): RedirectResponse
    {
        abort_unless($staffDevice->shop_id === $request->user()->shop->id, 403);
        abort_if($staffDevice->revoked_at, 404);

        // The previous token cannot be recovered (only its hash is stored),
        // so issuing a new setup link rotates the device credential and signs
        // out whoever was using the old one.
        $token = Str::random(64);
        $staffDevice->update([
            'token_hash' => hash('sha256', $token),
            'staff_member_id' => null,
            'staff_signed_in_at' => null,
        ]);

        return redirect()->route('dashboard.staff')
            ->with('staffToken', $token)
            ->with('staffDeviceName', $staffDevice->name);
    }

    public function destroy(Request $request, StaffDevice $staffDevice): RedirectResponse
    {
        abort_unless($staffDevice->shop_id === $request->user()->shop->id, 403);

        $staffDevice->update(['revoked_at' => now()]);

        return redirect()->route('dashboard.staff');
    }
}
