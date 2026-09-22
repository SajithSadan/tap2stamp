<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Review;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;

/**
 * Collects an in-app star rating + optional comment, saved to our own
 * database only. Never posted anywhere public or to Google - visible to
 * the shop owner only, once Stage 4's dashboard exists.
 */
class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Shop $shop, Customer $customer): JsonResponse
    {
        // A review requires an existing card - you have to have registered
        // at this shop before rating it.
        CustomerShopCard::where('shop_id', $shop->id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $review = Review::updateOrCreate(
            ['customer_id' => $customer->id, 'shop_id' => $shop->id],
            $request->validated()
        );

        return response()->json([
            'rating' => $review->rating,
            'comment' => $review->comment,
        ]);
    }
}
