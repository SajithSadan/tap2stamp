<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use App\Support\ShopContact;

function contactDetails(array $overrides = []): array
{
    return [
        'contact_name' => 'Jamie Smith',
        'contact_email' => 'Jamie@CoffeeCorner.co.uk',
        'contact_phone' => '07700 900123',
        'address_line1' => '12 High Street',
        'address_line2' => 'Unit 3',
        'town' => 'Leeds',
        'postcode' => 'ls14ap',
        'delivery_same' => true,
        'delivery_address' => '',
        ...$overrides,
    ];
}

function setupPayload(array $overrides = []): array
{
    return [
        'name' => 'The Coffee Corner',
        'slug' => 'the-coffee-corner',
        'max_stamps' => 6,
        'reward_title' => 'Free coffee after 6 stamps',
        ...contactDetails($overrides),
    ];
}

function ownerWithoutShop(): User
{
    return User::factory()->create(['role' => UserRole::Owner]);
}

// --- Shop setup ------------------------------------------------------------

test('shop setup saves the business contact and location, tidied up', function () {
    $owner = ownerWithoutShop();

    $this->actingAs($owner)->post('/onboarding', setupPayload())->assertRedirect('/dashboard');

    expect(Shop::sole())
        ->contact_name->toBe('Jamie Smith')
        ->contact_email->toBe('jamie@coffeecorner.co.uk')
        ->contact_phone->toBe('+447700900123')
        ->address_line1->toBe('12 High Street')
        ->address_line2->toBe('Unit 3')
        ->town->toBe('Leeds')
        ->postcode->toBe('LS1 4AP')
        ->delivery_address->toBeNull(); // same as the shop
});

test('a separate delivery address is kept when "same as the shop" is unticked', function () {
    $this->actingAs(ownerWithoutShop())->post('/onboarding', setupPayload([
        'delivery_same' => false,
        'delivery_address' => "Coffee Corner Ltd\n5 Park Row\nLeeds LS1 5HD",
    ]))->assertRedirect('/dashboard');

    expect(Shop::sole()->delivery_address)->toBe("Coffee Corner Ltd\n5 Park Row\nLeeds LS1 5HD");
});

test('an unticked delivery box needs an address', function () {
    $this->actingAs(ownerWithoutShop())->post('/onboarding', setupPayload(['delivery_same' => false, 'delivery_address' => '']))
        ->assertSessionHasErrors('delivery_address');

    expect(Shop::count())->toBe(0);
});

test('shop setup requires the contact and location details', function (string $field) {
    $this->actingAs(ownerWithoutShop())->post('/onboarding', setupPayload([$field => '']))->assertSessionHasErrors($field);

    expect(Shop::count())->toBe(0);
})->with(['contact_name', 'contact_email', 'contact_phone', 'address_line1', 'town', 'postcode']);

test('address line 2 is optional', function () {
    $this->actingAs(ownerWithoutShop())->post('/onboarding', setupPayload(['address_line2' => '']))->assertRedirect('/dashboard');

    expect(Shop::sole()->address_line2)->toBeNull();
});

// --- Phone and postcode formats ---------------------------------------------

test('UK phone numbers are accepted in every common format and stored as +44', function (string $typed, string $stored) {
    expect(ShopContact::phone($typed))->toBe($stored);

    $this->actingAs(ownerWithoutShop())->post('/onboarding', setupPayload(['contact_phone' => $typed]))->assertSessionHasNoErrors();
    expect(Shop::sole()->contact_phone)->toBe($stored);
})->with([
    'mobile' => ['07700 900123', '+447700900123'],
    'mobile, dashes' => ['07700-900-123', '+447700900123'],
    'London landline' => ['020 7946 0000', '+442079460000'],
    'landline, brackets' => ['(0113) 496 0000', '+441134960000'],
    'international' => ['+44 7700 900123', '+447700900123'],
    'international with (0)' => ['+44 (0)20 7946 0000', '+442079460000'],
    '0044 prefix' => ['0044 7700 900123', '+447700900123'],
    'Indian mobile' => ['+91 98765 43210', '+919876543210'],
]);

test('invalid phone numbers are rejected', function (string $typed) {
    $this->actingAs(ownerWithoutShop())->post('/onboarding', setupPayload(['contact_phone' => $typed]))->assertSessionHasErrors('contact_phone');
})->with(['12345', 'not a number', '07700 9001', '+1 202 555 0123']);

test('postcodes are upper-cased with the space in the right place', function (string $typed, string $stored) {
    expect(ShopContact::postcode($typed))->toBe($stored);
})->with([
    ['ls14ap', 'LS1 4AP'],
    ['sw1a 1aa', 'SW1A 1AA'],
    ['M1   1AE', 'M1 1AE'],
    ['560001', '560001'], // Indian PIN code
]);

test('invalid postcodes are rejected', function () {
    $this->actingAs(ownerWithoutShop())->post('/onboarding', setupPayload(['postcode' => 'HELLO']))->assertSessionHasErrors('postcode');
});

// --- Owner settings ---------------------------------------------------------

test('the settings page includes the contact details', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id, 'contact_phone' => '+447700900123', 'town' => 'Leeds']);

    $this->actingAs($owner)->get('/dashboard/settings')->assertInertia(fn ($page) => $page
        ->component('Dashboard/Settings')
        ->where('contact.contact_phone', '+447700900123')
        ->where('contact.town', 'Leeds')
    );
});

test('an owner can update their contact details from settings, including a shop made before they existed', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]); // no contact details yet

    $this->actingAs($owner)->put('/dashboard/settings/contact', contactDetails([
        'contact_phone' => '020 7946 0000',
        'delivery_same' => false,
        'delivery_address' => '1 Warehouse Way, Leeds LS2 7AA',
    ]))->assertRedirect('/dashboard/settings')->assertSessionHas('status');

    expect($shop->fresh())
        ->contact_phone->toBe('+442079460000')
        ->postcode->toBe('LS1 4AP')
        ->delivery_address->toBe('1 Warehouse Way, Leeds LS2 7AA');

    // Ticking "same as the shop" again clears the separate address.
    $this->actingAs($owner)->put('/dashboard/settings/contact', contactDetails(['delivery_same' => true, 'delivery_address' => 'left over text']));
    expect($shop->fresh()->delivery_address)->toBeNull();
});

test('contact settings are validated and never touch another shop', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $mine = Shop::factory()->create(['user_id' => $owner->id]);
    $other = Shop::factory()->create(['contact_phone' => '+447700900999']);

    $this->actingAs($owner)->put('/dashboard/settings/contact', contactDetails(['contact_phone' => 'nope']))->assertSessionHasErrors('contact_phone');
    $this->actingAs($owner)->put('/dashboard/settings/contact', contactDetails());

    expect($mine->fresh()->contact_phone)->toBe('+447700900123')
        ->and($other->fresh()->contact_phone)->toBe('+447700900999');
});

test('the main settings form still saves for shops without contact details', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->put('/dashboard/settings', [
        'name' => $shop->name,
        'max_stamps' => $shop->max_stamps,
        'reward_title' => 'Updated reward',
    ])->assertSessionHasNoErrors();

    expect($shop->fresh()->reward_title)->toBe('Updated reward');
});

// --- Admin -----------------------------------------------------------------

test('the admin shops grid shows each shop\'s contact and location', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Shop::factory()->create([
        'contact_name' => 'Jamie Smith',
        'contact_phone' => '+447700900123',
        'contact_email' => 'jamie@coffeecorner.co.uk',
        'address_line1' => '12 High Street',
        'town' => 'Leeds',
        'postcode' => 'LS1 4AP',
        'delivery_address' => '5 Park Row, Leeds',
    ]);

    $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page
        ->where('shops.0.contact_phone', '+447700900123')
        ->where('shops.0.contact_name', 'Jamie Smith')
        ->where('shops.0.town', 'Leeds')
        ->where('shops.0.address', '12 High Street, Leeds, LS1 4AP')
        ->where('shops.0.delivery_address', '5 Park Row, Leeds')
    );
});
