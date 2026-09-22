<?php

use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Review;
use App\Models\Shop;

test('a registered customer can submit a rating', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);

    $response = $this->postJson("/s/{$shop->slug}/card/{$customer->uuid}/review", [
        'rating' => 5,
        'comment' => 'Lovely staff!',
    ]);

    $response->assertOk();
    $response->assertJson(['rating' => 5, 'comment' => 'Lovely staff!']);
    expect(Review::count())->toBe(1);
});

test('a rating requires an existing card at this shop', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create(); // never registered at this shop

    $response = $this->postJson("/s/{$shop->slug}/card/{$customer->uuid}/review", ['rating' => 4]);

    $response->assertNotFound();
    expect(Review::count())->toBe(0);
});

test('rating must be between 1 and 5', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);

    $response = $this->postJson("/s/{$shop->slug}/card/{$customer->uuid}/review", ['rating' => 6]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('rating');
});

test('a comment is optional', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);

    $this->postJson("/s/{$shop->slug}/card/{$customer->uuid}/review", ['rating' => 3])
        ->assertOk();
});

test('resubmitting updates the existing review rather than duplicating it', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);

    $this->postJson("/s/{$shop->slug}/card/{$customer->uuid}/review", ['rating' => 2, 'comment' => 'meh']);
    $this->postJson("/s/{$shop->slug}/card/{$customer->uuid}/review", ['rating' => 5, 'comment' => 'actually great']);

    expect(Review::count())->toBe(1);
    expect(Review::first()->rating)->toBe(5);
    expect(Review::first()->comment)->toBe('actually great');
});

test('the card JSON includes an existing review', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);
    Review::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'rating' => 4, 'comment' => 'Great coffee']);

    $response = $this->getJson("/s/{$shop->slug}/card/{$customer->uuid}");

    $response->assertJson(['review' => ['rating' => 4, 'comment' => 'Great coffee']]);
});

test('the card JSON omits review when none exists yet', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);

    $response = $this->getJson("/s/{$shop->slug}/card/{$customer->uuid}");

    $response->assertJsonMissingPath('review');
});
