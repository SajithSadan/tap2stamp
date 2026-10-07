<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Review;
use App\Models\Shop;
use App\Services\CustomerRegistrar;
use App\Support\Countries;
use App\Support\SignupIcons;
use App\Support\StampIcons;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    /**
     * The page shell. Whether a modal or the card itself shows is decided
     * client-side (localStorage isn't available server-side) - this only
     * sends the public shop info needed either way.
     */
    public function show(Request $request, Shop $shop): Response
    {
        // ?preview=1 (the admin / owner "open customer page" buttons): the
        // page shows a sample card and never reads or saves a customer.
        $preview = $request->boolean('preview');

        return Inertia::render('Card', [
            'preview' => $preview ? $this->previewCard($request, $shop) : null,
            'shop' => [
                'id' => $shop->id,
                'slug' => $shop->slug,
                'name' => $shop->name,
                'reward_title' => $shop->reward_title,
                'max_stamps' => $shop->max_stamps,
                // Public links - safe to show before registration, unlike
                // wifi credentials which stay in cardPayload() below. The
                // Google link sits alongside the in-app rating (RatingTile),
                // for customers who want to review the shop on Google too.
                'instagram_url' => $shop->instagram_url,
                'google_review_url' => $shop->google_review_url,
                'google_review_direct' => $shop->google_review_direct,
                'stamp_icon' => StampIcons::resolve($shop->stamp_icon),
                'signup_icon' => SignupIcons::resolve($shop->signup_icon),
                'banner_url' => $shop->bannerUrl(),
                'logo_url' => $shop->logoUrl(),
                'header_style' => $shop->headerStyle(),
                // The sign-up's phone country picker starts on the shop's own country.
                'country' => $shop->country,
            ],
            // The look the owner picked on /dashboard/theme (or the default).
            'theme' => $shop->appliedTheme(),
            'phoneCountries' => Countries::options(),
        ]);
    }

    /**
     * A sample card in the same shape as cardPayload(). Wi-Fi details are
     * normally only for registered customers, so the preview includes them
     * only for this shop's owner or an admin - anyone can add ?preview=1.
     *
     * @return array<string, mixed>
     */
    private function previewCard(Request $request, Shop $shop): array
    {
        $user = $request->user();
        $canSeeWifi = $user && ($user->isAdmin() || $user->id === $shop->user_id);

        return array_filter([
            'uuid' => 'preview',
            'stamps' => min(3, $shop->max_stamps - 1),
            'max_stamps' => $shop->max_stamps,
            'reward_title' => $shop->reward_title,
            'rewards_claimed' => 0,
            'shop_id' => $shop->id,
            'instagram_url' => $shop->instagram_url,
            'wifi_ssid' => $canSeeWifi ? $shop->wifi_ssid : null,
            'wifi_password' => $canSeeWifi ? $shop->wifi_password : null,
        ], fn ($value) => $value !== null);
    }

    public function register(RegisterCustomerRequest $request, Shop $shop, CustomerRegistrar $registrar): JsonResponse
    {
        $card = $registrar->registerFor(
            $shop,
            $request->string('name')->value(),
            $request->string('phone')->value(),
            $request->boolean('marketing_consent'),
        );

        return response()->json($this->cardPayload($card, $shop));
    }

    public function registerExisting(Request $request, Shop $shop, Customer $customer, CustomerRegistrar $registrar): JsonResponse
    {
        $validated = $request->validate(['marketing_consent' => ['required', 'boolean']]);
        $card = $registrar->registerExistingFor($shop, $customer, $validated['marketing_consent']);

        return response()->json($this->cardPayload($card, $shop));
    }

    /**
     * Read-only card state for an already-known uuid. {customer:uuid} 404s
     * automatically for an unrecognised uuid; firstOrFail() below 404s if
     * that customer has never registered at this particular shop.
     */
    public function cardState(Shop $shop, Customer $customer): JsonResponse
    {
        $card = CustomerShopCard::where('shop_id', $shop->id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $card->setRelation('customer', $customer);

        return response()->json($this->cardPayload($card, $shop));
    }

    /**
     * Wifi/link fields and any existing review are only included when
     * present - Urban Barber has no wifi, for example, and the response
     * should omit those keys entirely rather than send them as null.
     *
     * @return array<string, mixed>
     */
    private function cardPayload(CustomerShopCard $card, Shop $shop): array
    {
        $review = Review::where('customer_id', $card->customer_id)
            ->where('shop_id', $shop->id)
            ->first();

        return array_filter([
            'uuid' => $card->customer->uuid,
            'stamps' => $card->current_stamps,
            'max_stamps' => $shop->max_stamps,
            'reward_title' => $shop->reward_title,
            'rewards_claimed' => $card->rewards_claimed,
            'shop_id' => $shop->id,
            'instagram_url' => $shop->instagram_url,
            'wifi_ssid' => $shop->wifi_ssid,
            'wifi_password' => $shop->wifi_password,
            'review' => $review ? ['rating' => $review->rating, 'comment' => $review->comment] : null,
        ], fn ($value) => $value !== null);
    }
}
