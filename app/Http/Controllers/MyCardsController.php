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
            ])
            ->values();

        return response()->json(['cards' => $cards]);
    }
}
