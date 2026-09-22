<?php

use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;

test('the my-cards page renders', function () {
    $this->get('/my-cards')->assertOk()->assertInertia(fn ($page) => $page->component('MyCards'));
});

test('an unknown uuid 404s', function () {
    $this->getJson('/my-cards/11111111-1111-1111-1111-111111111111')->assertNotFound();
});

test('it returns every shop card for the customer', function () {
    $customer = Customer::factory()->create();
    $shopOne = Shop::factory()->create(['name' => 'Artisan Cafe', 'max_stamps' => 6]);
    $shopTwo = Shop::factory()->create(['name' => 'Urban Barber', 'max_stamps' => 8]);

    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shopOne->id, 'current_stamps' => 3]);
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shopTwo->id, 'current_stamps' => 1]);

    $response = $this->getJson("/my-cards/{$customer->uuid}");

    $response->assertOk();
    $response->assertJsonCount(2, 'cards');
    $response->assertJsonFragment(['shop_name' => 'Artisan Cafe', 'stamps' => 3, 'max_stamps' => 6]);
    $response->assertJsonFragment(['shop_name' => 'Urban Barber', 'stamps' => 1, 'max_stamps' => 8]);
});

test('a card from another customer is never included', function () {
    $customer = Customer::factory()->create();
    $otherCustomer = Customer::factory()->create();
    $shop = Shop::factory()->create();

    CustomerShopCard::factory()->create(['customer_id' => $otherCustomer->id, 'shop_id' => $shop->id]);

    $response = $this->getJson("/my-cards/{$customer->uuid}");

    $response->assertOk();
    $response->assertJsonCount(0, 'cards');
});
