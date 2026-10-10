<?php

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\QrCode;
use App\Models\QrDesign;
use App\Models\Shop;
use App\Models\User;
use App\Services\StripeGateway;
use App\Support\ShopContact;
use App\Support\ThemeCatalog;

/** A shop's country decides ordering (UK only) vs downloading its assigned QR codes (elsewhere). */
function countryOwner(string $country = 'GB'): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    return [$owner, Shop::factory()->create(['user_id' => $owner->id, 'country' => $country])];
}

function overseasSetup(array $overrides = []): array
{
    return [
        'name' => 'Corner Cafe',
        'max_stamps' => 8,
        'reward_title' => 'Free coffee',
        'country' => 'US',
        'contact_name' => 'Sam Lee',
        'contact_email' => 'sam@corner.cafe',
        'contact_phone' => '+1 (212) 555-0147',
        'address_line1' => '5 Main Street',
        'town' => 'New York',
        'postcode' => '',
        'delivery_same' => true,
        ...$overrides,
    ];
}

function plainDesign(): QrDesign
{
    return QrDesign::create([
        'name' => 'Table card', 'image_path' => 'qr-designs/x.png', 'image_width' => 600, 'image_height' => 600,
        'qr_x' => 0.2, 'qr_y' => 0.2, 'qr_size' => 0.6, 'width_mm' => 60,
    ]);
}

// --- Sign-up -----------------------------------------------------------------

test('shop setup collects the country; the phone is saved as code + number and the postcode is optional', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/onboarding', overseasSetup())->assertRedirect('/dashboard');

    expect(Shop::sole())
        ->country->toBe('US')
        ->contact_phone_code->toBe('1')
        ->contact_phone->toBe('2125550147')
        ->contactPhone()->toBe('+1 2125550147')
        ->postcode->toBe('');
});

test('with no code picked the phone gets the country code of the shop; a picked one is kept', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/onboarding/business', overseasSetup(['country' => 'AE', 'contact_phone' => '050 123 4567']))->assertSessionHasNoErrors();
    expect($owner->fresh()->onboarding_draft)->contact_phone_code->toBe('971')->contact_phone->toBe('501234567');

    $this->actingAs($owner)->post('/onboarding/business', overseasSetup(['country' => 'AE', 'contact_phone_code' => '44', 'contact_phone' => '07700 900123']))->assertSessionHasNoErrors();
    expect($owner->fresh()->onboarding_draft)->contact_phone_code->toBe('44')->contact_phone->toBe('7700900123');
});

test('UK and India keep their exact phone and postcode rules', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/onboarding', overseasSetup(['country' => 'GB', 'contact_phone' => '212 555', 'postcode' => '']))
        ->assertSessionHasErrors(['contact_phone', 'postcode']);

    $this->actingAs($owner)->post('/onboarding', overseasSetup(['country' => 'IN', 'contact_phone' => '98765 43210', 'postcode' => '560001', 'state' => 'Karnataka']))
        ->assertRedirect('/dashboard');
    expect(Shop::sole())->country->toBe('IN')->contact_phone_code->toBe('91')->contact_phone->toBe('9876543210');
});

test('an unknown country is refused', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/onboarding', overseasSetup(['country' => 'ZZ']))->assertSessionHasErrors('country');
});

test('a missing country means the UK', function () {
    expect(ShopContact::country(null))->toBe('GB')->and(ShopContact::country(' us '))->toBe('US');
});

test('the owner can change the country on Settings', function () {
    [$owner, $shop] = countryOwner();

    $this->actingAs($owner)->put('/dashboard/settings/contact', [
        ...overseasSetup(['country' => 'AE', 'contact_phone' => '00971 50 123 4567']),
    ])->assertSessionHasNoErrors();

    expect($shop->fresh())->country->toBe('AE')->contact_phone_code->toBe('971')->contact_phone->toBe('501234567');
});

// --- Ordering vs QR codes ------------------------------------------------------

test('UK owners see Orders; overseas owners see QR codes instead', function () {
    [$uk] = countryOwner('GB');
    [$us] = countryOwner('US');
    $labels = fn (User $user) => collect($this->actingAs($user)->get('/dashboard')->viewData('page')['props']['navigation']['main'])->pluck('label');

    expect($labels($uk))->toContain('Orders')->not->toContain('QR codes')
        ->and($labels($us))->toContain('QR codes')->not->toContain('Orders');
});

test('overseas shops get no order offer and cannot order', function () {
    [$owner] = countryOwner('US');
    // The counter display is inserted by its migration.
    $product = Product::featured() ?? Product::create(['name' => 'Counter display', 'price_pence' => 4000, 'is_active' => true, 'is_featured' => true]);
    $this->mock(StripeGateway::class, fn ($m) => $m->shouldReceive('configured')->andReturn(true));

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page->where('orderOffer', null)->where('shop.can_order', false));
    $this->actingAs($owner)->get('/dashboard/orders')->assertRedirect('/dashboard/qr-codes');
    $this->actingAs($owner)->post('/dashboard/orders', ['product_id' => $product->id, 'quantity' => 1])->assertNotFound();
    $this->actingAs($owner)->postJson('/dashboard/orders/coupon', ['product_id' => $product->id, 'quantity' => 1, 'code' => 'X'])->assertNotFound();
});

test('the QR codes page lists only this shop\'s codes, in the design the admin picked', function () {
    [$owner, $shop] = countryOwner('US');
    [, $other] = countryOwner('US');
    $design = plainDesign();
    $shop->update(['qr_design_id' => $design->id]);
    $mine = QrCode::factory()->mapped()->create(['shop_id' => $shop->id, 'destination_url' => url("/s/{$shop->slug}")]);
    QrCode::factory()->mapped()->create(['shop_id' => $other->id]);

    $this->actingAs($owner)->get('/dashboard/qr-codes')->assertInertia(fn ($page) => $page
        ->component('Dashboard/QrCodes')
        ->has('codes', 1)
        ->where('codes.0.code', $mine->code)
        ->where('codes.0.scan_url', $mine->scanUrl())
        ->where('codes.0.opens', 'Your loyalty card')
        ->where('design.id', $design->id)
        ->where('design.name', 'Table card')
    );
});

test('with no design picked (and no default) the QR codes come plain, and with none assigned one is issued', function () {
    [$owner] = countryOwner('US');

    // Overseas shops no longer wait for the admin: their first visit issues one (OverseasQrTest).
    $this->actingAs($owner)->get('/dashboard/qr-codes')->assertInertia(fn ($page) => $page
        ->has('codes', 1)
        ->where('codes.0.opens', 'Your loyalty card')
        ->where('design', null)
    );
});

// --- Admin -----------------------------------------------------------------------

test('the admin sets a shop\'s country and its QR download design', function () {
    [, $shop] = countryOwner('GB');
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $design = plainDesign();

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/settings", [
        'name' => $shop->name,
        'max_stamps' => 8,
        'reward_title' => 'Free coffee',
        'country' => 'FR',
        'contact_phone' => '+33 1 23 45 67 89',
        'postcode' => '75001',
        'qr_design_id' => $design->id,
    ])->assertSessionHasNoErrors();

    expect($shop->fresh())
        ->country->toBe('FR')
        ->contact_phone_code->toBe('33')
        ->contact_phone->toBe('123456789')
        ->qr_design_id->toBe($design->id)
        ->canOrderProducts()->toBeFalse();
});

test('the admin form keeps the shop\'s own country rules when no country is sent', function () {
    [, $shop] = countryOwner('IN');
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/settings", [
        'name' => $shop->name, 'max_stamps' => 8, 'reward_title' => 'Free coffee', 'contact_phone' => '98765 43210',
    ])->assertSessionHasNoErrors();

    expect($shop->fresh())->country->toBe('IN')->contact_phone_code->toBe('91')->contact_phone->toBe('9876543210');
});

test('the admin can add an overseas shop', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post('/admin/shops', [
        'owner_email' => 'owner@example.com',
        'owner_password' => 'password123',
        'shop_name' => 'Dubai Beans',
        'shop_slug' => 'dubai-beans',
        'shop_max_stamps' => 8,
        'shop_reward_title' => 'Free coffee',
        'shop_stamp_icon' => 'check',
        'shop_theme' => ThemeCatalog::DEFAULT,
        'shop_country' => 'AE',
    ])->assertSessionHasNoErrors();

    expect(Shop::sole()->country)->toBe('AE');
});
