<?php

use App\Models\Shop;
use Illuminate\Database\Migrations\Migration;

/**
 * Orders made before their shop had an address show "No address" for good,
 * since an order keeps the address it was made with. Fill those in from the
 * shop's current address (orders not posted yet only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Shop::whereHas('orders', fn ($q) => $q->whereNull('delivery_address')->whereNull('dispatched_at'))
            ->each(function (Shop $shop) {
                if ($address = $shop->deliveryAddress()) {
                    $shop->orders()->whereNull('delivery_address')->whereNull('dispatched_at')->update(['delivery_address' => $address]);
                }
            });
    }

    public function down(): void
    {
        // Data only - nothing to undo.
    }
};
