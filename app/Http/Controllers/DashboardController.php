<?php

namespace App\Http\Controllers;

use App\Enums\ActionType;
use App\Http\Requests\UpdateShopSettingsRequest;
use App\Models\StaffDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $shop = $request->user()->shop;

        return Inertia::render('Dashboard/Index', [
            'shop' => [
                'id' => $shop->id,
                'slug' => $shop->slug,
                'name' => $shop->name,
                'max_stamps' => $shop->max_stamps,
                'reward_title' => $shop->reward_title,
                'google_review_url' => $shop->google_review_url,
                'instagram_url' => $shop->instagram_url,
                'wifi_ssid' => $shop->wifi_ssid,
                'wifi_password' => $shop->wifi_password,
            ],
            'stats' => [
                'customer_count' => $shop->cards()->count(),
                'stamps_today' => $shop->stampLogs()
                    ->whereDate('created_at', today())
                    ->where('action_type', ActionType::StampAdded)
                    ->count(),
                'rewards_redeemed' => $shop->stampLogs()->where('action_type', ActionType::RewardRedeemed)->count(),
            ],
            'staffDevices' => $shop->staffDevices()->latest()->get()->map(fn (StaffDevice $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'revoked' => $device->revoked_at !== null,
                'last_used_at' => $device->last_used_at?->diffForHumans(),
                'created_at' => $device->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function updateSettings(UpdateShopSettingsRequest $request): RedirectResponse
    {
        $request->user()->shop->update($request->validated());

        return redirect()->route('dashboard.index');
    }
}
