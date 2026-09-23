<?php

use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;

function registerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jamie Smith',
        'phone' => '07911123456',
    ], $overrides);
}

test('an unknown shop slug 404s', function () {
    $this->get('/s/does-not-exist')->assertNotFound();
});

test('the card page renders with the shop props', function () {
    $shop = Shop::factory()->create([
        'slug' => 'artisan-cafe',
        'name' => 'Artisan Cafe',
        'instagram_url' => 'https://instagram.com/demo',
    ]);

    $response = $this->get("/s/{$shop->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Card')
        ->where('shop.slug', 'artisan-cafe')
        ->where('shop.name', 'Artisan Cafe')
        ->where('shop.instagram_url', 'https://instagram.com/demo')
        ->missing('shop.google_review_url')
    );
});

test('registration requires a name', function () {
    $shop = Shop::factory()->create();

    $response = $this->postJson("/s/{$shop->slug}/register", registerPayload(['name' => '']));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('name');
});

test('registration rejects an invalid phone number', function () {
    $shop = Shop::factory()->create();

    $response = $this->postJson("/s/{$shop->slug}/register", registerPayload(['phone' => '12345']));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('phone');
});

test('phone numbers are normalised to +447XXXXXXXXX regardless of input format', function (string $input) {
    $shop = Shop::factory()->create();

    $this->postJson("/s/{$shop->slug}/register", registerPayload(['phone' => $input]))->assertOk();

    expect(Customer::first()->phone)->toBe('+447911123456');
})->with([
    '07911123456',
    '447911123456',
    '+447911123456',
]);

test('registering returns the uuid and card state', function () {
    $shop = Shop::factory()->create(['reward_title' => 'Free coffee after 6 stamps', 'max_stamps' => 6]);

    $response = $this->postJson("/s/{$shop->slug}/register", registerPayload());

    $response->assertOk();
    $response->assertJson([
        'stamps' => 0,
        'max_stamps' => 6,
        'reward_title' => 'Free coffee after 6 stamps',
        'rewards_claimed' => 0,
        'shop_id' => $shop->id,
    ]);
    $response->assertJsonStructure(['uuid']);
});

test('registering twice with the same phone returns the same customer and card', function () {
    $shop = Shop::factory()->create();

    $first = $this->postJson("/s/{$shop->slug}/register", registerPayload(['name' => 'Jamie Smith']));
    $second = $this->postJson("/s/{$shop->slug}/register", registerPayload(['name' => 'Someone Else']));

    expect(Customer::count())->toBe(1);
    expect(CustomerShopCard::count())->toBe(1);
    expect($first->json('uuid'))->toBe($second->json('uuid'));
    expect(Customer::first()->name)->toBe('Jamie Smith');
});

test('the same customer registering at a second shop gets a separate card', function () {
    $shopOne = Shop::factory()->create();
    $shopTwo = Shop::factory()->create();

    $this->postJson("/s/{$shopOne->slug}/register", registerPayload());
    $this->postJson("/s/{$shopTwo->slug}/register", registerPayload());

    expect(Customer::count())->toBe(1);
    expect(CustomerShopCard::count())->toBe(2);
});

test('a customer can register without marketing consent', function () {
    $shop = Shop::factory()->create();

    $this->postJson("/s/{$shop->slug}/register", registerPayload())->assertOk();

    $card = CustomerShopCard::where('shop_id', $shop->id)->firstOrFail();
    expect($card->marketing_consent)->toBeFalse();
    expect($card->marketing_consent_at)->toBeNull();
});

test('a customer can register with marketing consent, recorded with a timestamp', function () {
    $shop = Shop::factory()->create();

    $this->postJson("/s/{$shop->slug}/register", registerPayload(['marketing_consent' => true]))->assertOk();

    $card = CustomerShopCard::where('shop_id', $shop->id)->firstOrFail();
    expect($card->marketing_consent)->toBeTrue();
    expect($card->marketing_consent_at)->not->toBeNull();
});

test('marketing consent is per shop, not platform-wide', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $this->postJson("/s/{$shopA->slug}/register", registerPayload(['marketing_consent' => true]))->assertOk();
    $this->postJson("/s/{$shopB->slug}/register", registerPayload())->assertOk();

    expect(CustomerShopCard::where('shop_id', $shopA->id)->first()->marketing_consent)->toBeTrue();
    expect(CustomerShopCard::where('shop_id', $shopB->id)->first()->marketing_consent)->toBeFalse();
});

test('re-registering with the box unticked does not withdraw earlier consent', function () {
    $shop = Shop::factory()->create();

    $this->postJson("/s/{$shop->slug}/register", registerPayload(['marketing_consent' => true]))->assertOk();
    $consentedAt = CustomerShopCard::first()->marketing_consent_at;

    $this->postJson("/s/{$shop->slug}/register", registerPayload(['marketing_consent' => false]))->assertOk();

    $card = CustomerShopCard::first();
    expect($card->marketing_consent)->toBeTrue();
    expect($card->marketing_consent_at->equalTo($consentedAt))->toBeTrue();
});

test('marketing consent must be a boolean', function () {
    $shop = Shop::factory()->create();

    $this->postJson("/s/{$shop->slug}/register", registerPayload(['marketing_consent' => 'please']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('marketing_consent');
});

test('an Indian mobile number is accepted', function () {
    $shop = Shop::factory()->create();

    $this->postJson("/s/{$shop->slug}/register", registerPayload(['phone' => '+919876543210']))->assertOk();

    expect(Customer::first()->phone)->toBe('+919876543210');
});

test('an invalid Indian mobile number is rejected', function (string $phone) {
    $shop = Shop::factory()->create();

    $this->postJson("/s/{$shop->slug}/register", registerPayload(['phone' => $phone]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
})->with([
    'starts with 5' => '+915876543210',
    'too short' => '+91987654321',
    'too long' => '+9198765432100',
]);
