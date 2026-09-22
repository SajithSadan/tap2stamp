<?php

use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;

test('an unknown uuid 404s', function () {
    $shop = Shop::factory()->create();

    $this->getJson("/s/{$shop->slug}/card/11111111-1111-1111-1111-111111111111")
        ->assertNotFound();
});

test('a uuid with no card at this shop 404s', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $otherShop->id]);

    $this->getJson("/s/{$shop->slug}/card/{$customer->uuid}")->assertNotFound();
});

test('a known uuid returns the current card state', function () {
    $shop = Shop::factory()->create(['reward_title' => 'Free haircut after 8 visits', 'max_stamps' => 8]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'current_stamps' => 3,
        'rewards_claimed' => 1,
    ]);

    $response = $this->getJson("/s/{$shop->slug}/card/{$customer->uuid}");

    $response->assertOk();
    $response->assertJson([
        'uuid' => $customer->uuid,
        'stamps' => 3,
        'max_stamps' => 8,
        'reward_title' => 'Free haircut after 8 visits',
        'rewards_claimed' => 1,
        'shop_id' => $shop->id,
    ]);
});

test('the card JSON includes instagram and wifi fields when set', function () {
    $shop = Shop::factory()->create([
        'instagram_url' => 'https://instagram.com/demo',
        'wifi_ssid' => 'Demo-Guest',
        'wifi_password' => 'letmein',
    ]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);

    $response = $this->getJson("/s/{$shop->slug}/card/{$customer->uuid}");

    $response->assertJson([
        'instagram_url' => 'https://instagram.com/demo',
        'wifi_ssid' => 'Demo-Guest',
        'wifi_password' => 'letmein',
    ]);
});

test('the card JSON omits wifi fields entirely when the shop has none', function () {
    $shop = Shop::factory()->create([
        'instagram_url' => 'https://instagram.com/urbanbarber',
        'wifi_ssid' => null,
        'wifi_password' => null,
    ]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);

    $response = $this->getJson("/s/{$shop->slug}/card/{$customer->uuid}");

    $response->assertJsonMissingPath('wifi_ssid');
    $response->assertJsonMissingPath('wifi_password');
    $response->assertJson(['instagram_url' => 'https://instagram.com/urbanbarber']);
});
