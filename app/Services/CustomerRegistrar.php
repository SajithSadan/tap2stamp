<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;

class CustomerRegistrar
{
    /**
     * Finds the customer by phone (creating one if this number hasn't been
     * seen before) and ensures a card exists for the given shop. An
     * existing customer's name is left untouched — only a brand-new
     * customer gets the submitted name.
     */
    public function registerFor(Shop $shop, string $name, string $phone): CustomerShopCard
    {
        $customer = Customer::firstOrCreate(
            ['phone' => $phone],
            ['name' => $name]
        );

        // The extra attributes here aren't just decoration: firstOrCreate()
        // only hydrates the in-memory model from what it explicitly passes
        // in, not from the migration's DB-level ->default(0) - without this,
        // a freshly created card's current_stamps/rewards_claimed would be
        // null in PHP (even though the actual DB row correctly has 0) until
        // the model was re-fetched.
        $card = CustomerShopCard::firstOrCreate(
            ['customer_id' => $customer->id, 'shop_id' => $shop->id],
            ['current_stamps' => 0, 'rewards_claimed' => 0]
        );

        $card->setRelation('customer', $customer);

        return $card;
    }
}
