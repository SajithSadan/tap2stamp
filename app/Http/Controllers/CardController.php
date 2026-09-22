<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Review;
use App\Models\Shop;
use App\Services\CustomerRegistrar;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    /**
     * The page shell. Whether a modal or the card itself shows is decided
     * client-side (localStorage isn't available server-side) - this only
     * sends the public shop info needed either way.
     */
    public function show(Shop $shop): Response
    {
        return Inertia::render('Card', [
            'shop' => [
                'id' => $shop->id,
                'slug' => $shop->slug,
                'name' => $shop->name,
                'reward_title' => $shop->reward_title,
                'max_stamps' => $shop->max_stamps,
                // Public marketing link - safe to show before registration,
                // unlike wifi credentials which stay in cardPayload() below.
                // No google_review_url here: reviews are collected in-app
                // and saved to our own DB, never posted externally.
                'instagram_url' => $shop->instagram_url,
            ],
        ]);
    }

    public function register(RegisterCustomerRequest $request, Shop $shop, CustomerRegistrar $registrar): JsonResponse
    {
        $card = $registrar->registerFor($shop, $request->string('name')->value(), $request->string('phone')->value());

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
