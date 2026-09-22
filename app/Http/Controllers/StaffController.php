<?php

namespace App\Http\Controllers;

use App\Enums\ActionType;
use App\Models\StampLog;
use App\Services\StampService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The staff scanner's API, all behind AuthenticateStaffDevice - the device
 * (and therefore its shop) is always read from the authenticated request,
 * never trusted from the client body.
 */
class StaffController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $device = $request->attributes->get('staffDevice');

        return response()->json([
            'shop_name' => $device->shop->name,
            'device_name' => $device->name,
        ]);
    }

    public function scan(Request $request, StampService $service): JsonResponse
    {
        $request->validate(['payload' => ['required', 'string']]);

        $device = $request->attributes->get('staffDevice');

        [$status, $body] = $service->scan($device, $request->string('payload')->value());

        return response()->json($body, $status);
    }

    public function summary(Request $request): JsonResponse
    {
        $device = $request->attributes->get('staffDevice');

        $stampsToday = StampLog::where('shop_id', $device->shop_id)
            ->whereDate('created_at', today())
            ->where('action_type', ActionType::StampAdded)
            ->count();

        return response()->json(['stamps_today' => $stampsToday]);
    }
}
