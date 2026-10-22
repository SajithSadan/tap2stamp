<?php

namespace App\Http\Controllers;

use App\Enums\ActionType;
use App\Http\Controllers\Admin\ShopMenuController;
use App\Http\Requests\StaffSignInRequest;
use App\Models\CustomerShopCard;
use App\Models\StaffMember;
use App\Models\StampLog;
use App\Services\ActivityLogger;
use App\Services\MenuCommandParser;
use App\Services\ShopMenu;
use App\Services\StampService;
use App\Support\Features;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * The staff dashboard's API, all behind AuthenticateStaffDevice - the device
 * (and therefore its shop) is always read from the authenticated request,
 * never trusted from the client body. Everything past sign-in additionally
 * needs a staff member signed in on the device (EnsureStaffSignedIn).
 */
class StaffController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $device = $request->attributes->get('staffDevice');
        $member = $device->activeStaffMember();

        return response()->json([
            'shop_name' => $device->shop->name,
            'device_name' => $device->name,
            'staff' => $member ? ['id' => $member->id, 'name' => $member->name] : null,
            // Names only, for the "who's working?" picker on the PIN screen.
            'staff_members' => $device->shop->staffMembers()->active()->orderBy('name')->get(['id', 'name']),
            // The Menu tab (mark items not available / hidden) - only with Menu on and a menu to show.
            'menu_enabled' => $device->shop->hasFeature(Features::MENU) && $device->shop->menuItems()->exists(),
            // The Menu tab's "tell the AI" bar (needs a Gemini key).
            'menu_ai' => app(MenuCommandParser::class)->configured(),
            // Trial / paid year over: the Scan tab shows this instead of the camera (StampService refuses too).
            'scan_paused' => $device->shop->scanningPaused(),
        ]);
    }

    /** The shop's menu for the Menu tab: names, prices and availability only. */
    public function menu(Request $request, ShopMenu $menu): JsonResponse
    {
        $shop = $request->attributes->get('staffDevice')->shop;
        abort_unless($shop->hasFeature(Features::MENU), 404);

        return response()->json([
            'sections' => collect($menu->sections($shop))->map(fn (array $section) => [
                'name' => $section['name'],
                'items' => collect($section['items'])->map(fn (array $item) => collect($item)->only(['id', 'name', 'price', 'availability'])),
            ]),
        ]);
    }

    /** {command} → matched items to confirm (MenuCommandParser); changes nothing. */
    public function menuCommand(Request $request, MenuCommandParser $parser): JsonResponse
    {
        $shop = $request->attributes->get('staffDevice')->shop;
        abort_unless($shop->hasFeature(Features::MENU), 404);

        return ShopMenuController::parseCommand($request, $shop, $parser);
    }

    /** {item | items, availability} for this device's shop, saved straight away. */
    public function menuAvailability(Request $request, ShopMenu $menu): JsonResponse
    {
        $shop = $request->attributes->get('staffDevice')->shop;
        abort_unless($shop->hasFeature(Features::MENU), 404);

        return ShopMenuController::saveAvailability($request, $shop, $menu);
    }

    public function signIn(StaffSignInRequest $request): JsonResponse
    {
        $device = $request->attributes->get('staffDevice');

        $member = StaffMember::active()
            ->where('shop_id', $device->shop_id)
            ->find($request->integer('staff_member_id'));

        if (! $member || ! Hash::check($request->string('pin')->value(), $member->pin_hash)) {
            return response()->json([
                'status' => 'error',
                'code' => 'invalid_pin',
                'message' => "That PIN doesn't match. Try again.",
            ], 422);
        }

        $device->signIn($member);
        ActivityLogger::record('staff.signed_in', "Signed in on {$device->name}", $device->shop_id, $device, null, ActivityLogger::staff($member));

        return response()->json(['staff' => ['id' => $member->id, 'name' => $member->name]]);
    }

    public function signOut(Request $request): JsonResponse
    {
        $device = $request->attributes->get('staffDevice');
        if ($member = $device->activeStaffMember()) {
            ActivityLogger::record('staff.signed_out', "Signed out on {$device->name}", $device->shop_id, $device, null, ActivityLogger::staff($member));
        }
        $device->signOut();

        return response()->json(['status' => 'ok']);
    }

    public function scan(Request $request, StampService $service): JsonResponse
    {
        // redeem: the "Mark reward as given" confirmation for a full card (StampService::scan).
        $request->validate(['payload' => ['required', 'string'], 'redeem' => ['sometimes', 'boolean']]);

        [$status, $body] = $service->scan(
            $request->attributes->get('staffDevice')->shop,
            $request->string('payload')->value(),
            $request->attributes->get('staffMember'),
            redeem: $request->boolean('redeem'),
        );

        return response()->json($body, $status);
    }

    public function summary(Request $request): JsonResponse
    {
        $device = $request->attributes->get('staffDevice');
        $member = $request->attributes->get('staffMember');

        $today = StampLog::where('shop_id', $device->shop_id)->whereDate('created_at', today());

        $count = fn (ActionType $action, bool $mine) => (clone $today)
            ->where('action_type', $action)
            ->when($mine, fn ($q) => $q->where('staff_member_id', $member->id))
            ->count();

        return response()->json([
            'stamps_today' => $count(ActionType::StampAdded, false),
            'redeemed_today' => $count(ActionType::RewardRedeemed, false),
            'my_stamps_today' => $count(ActionType::StampAdded, true),
            'my_redeemed_today' => $count(ActionType::RewardRedeemed, true),
        ]);
    }

    /**
     * Read-only lookup for a customer who can't show their QR (e.g. forgot
     * their phone). Only this shop's cards, only the last 3 phone digits -
     * enough to tell two "Jamie"s apart without exposing the number.
     */
    public function customers(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:50']]);

        $device = $request->attributes->get('staffDevice');
        $query = trim($request->string('q')->value());
        $digits = preg_replace('/\D/', '', $query);

        $cards = CustomerShopCard::where('shop_id', $device->shop_id)
            ->whereHas('customer', function ($q) use ($query, $digits) {
                strlen($digits) >= 3
                    ? $q->where('phone', 'like', '%'.$digits.'%')
                    : $q->where('name', 'like', '%'.$query.'%');
            })
            ->with('customer:id,name,phone')
            ->orderByDesc('last_stamped_at')
            ->limit(10)
            ->get();

        $maxStamps = $device->shop->max_stamps;

        return response()->json([
            'customers' => $cards->map(fn (CustomerShopCard $card) => [
                'id' => $card->id,
                'name' => $card->customer->name,
                'phone_ending' => substr($card->customer->phone, -3),
                'stamps' => $card->current_stamps,
                'max_stamps' => $maxStamps,
                'reward_ready' => $card->current_stamps >= $maxStamps,
                'rewards_claimed' => $card->rewards_claimed,
                'last_visit' => $card->last_stamped_at?->diffForHumans(),
            ]),
        ]);
    }
}
