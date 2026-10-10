<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Support\StampIcons;
use Illuminate\Http\JsonResponse;

class MyCardsController extends Controller
{
    public function index(Customer $customer): JsonResponse
    {
        $cards = CustomerShopCard::with('shop')
            ->where('customer_id', $customer->id)
            ->get()
            ->map(fn (CustomerShopCard $card) => [
                'shop_id' => $card->shop_id,
                'shop_slug' => $card->shop->slug,
                'shop_name' => $card->shop->name,
                'reward_title' => $card->shop->reward_title,
                'stamp_icon' => StampIcons::resolve($card->shop->stamp_icon),
                'stamps' => $card->current_stamps,
                'max_stamps' => $card->shop->max_stamps,
                'rewards_claimed' => $card->rewards_claimed,
                // Blocked by the admin: listed (greyed, no QR) so the customer knows why, after the working ones.
                'active' => ! $card->shop->isBlocked(),
                // Each card wears its own shop's look, like on the shop's card page.
                'theme' => $card->shop->appliedTheme(),
            ])
            ->sortByDesc('active')
            ->values();

        return response()->json(['cards' => $cards]);
    }

    /**
     * The customer's own "My Cards" home-screen app (Add to Home Screen /
     * Install). Its start_url carries their card id because an iPhone
     * home-screen app does NOT share Safari's storage - on first launch
     * /my-cards saves it (MyCards.jsx). Safari also clears a site's storage
     * after ~7 days unused; the installed app is exempt and can always
     * recover from this link.
     */
    public function manifest(Customer $customer): JsonResponse
    {
        return response()->json([
            'id' => '/my-cards',
            'name' => 'TaDa Tap - My Cards',
            'short_name' => 'My Cards',
            'description' => 'Your loyalty cards and stamps.',
            'start_url' => '/my-cards?c='.$customer->uuid,
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#0f2a46',
            'theme_color' => '#0f2a46',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
