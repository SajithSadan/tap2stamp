<?php

use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use Illuminate\Database\QueryException;

test('a card belongs to a customer and a shop', function () {
    $card = CustomerShopCard::factory()->create();

    expect($card->customer)->toBeInstanceOf(Customer::class);
    expect($card->shop)->toBeInstanceOf(Shop::class);
});

test('a customer can only have one card per shop', function () {
    $customer = Customer::factory()->create();
    $shop = Shop::factory()->create();

    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);

    expect(fn () => CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]))
        ->toThrow(QueryException::class);
});

test('the same customer can have cards at different shops', function () {
    $customer = Customer::factory()->create();
    $shopOne = Shop::factory()->create();
    $shopTwo = Shop::factory()->create();

    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shopOne->id]);
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shopTwo->id]);

    expect($customer->cards)->toHaveCount(2);
});

test('default stamp counts are zero', function () {
    $card = CustomerShopCard::factory()->create();

    expect($card->current_stamps)->toBe(0);
    expect($card->rewards_claimed)->toBe(0);
    expect($card->last_stamped_at)->toBeNull();
});
