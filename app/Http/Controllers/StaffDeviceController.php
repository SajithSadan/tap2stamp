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

        return redirect()->route('dashboard.index')->with('staffToken', $token);
    }

    public function destroy(Request $request, StaffDevice $staffDevice): RedirectResponse
    {
        abort_unless($staffDevice->shop_id === $request->user()->shop->id, 403);

        $staffDevice->update(['revoked_at' => now()]);

        return redirect()->route('dashboard.index');
    }
}
