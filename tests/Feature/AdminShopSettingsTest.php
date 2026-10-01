<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;

function shopSettingsAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

test('an admin can open a selected shop settings page with its configuration', function () {
    $shop = Shop::factory()->create([
        'name' => 'Northside Cafe',
        'google_review_url' => 'https://g.page/r/northside/review',
        'google_review_direct' => true,
        'instagram_url' => 'https://instagram.com/northside',
    ]);

    $this->actingAs(shopSettingsAdmin())
        ->get("/admin/shops/{$shop->id}/settings")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/ShopSettings')
            ->where('shop.id', $shop->id)
            ->where('shop.name', 'Northside Cafe')
            ->where('shop.google_review_direct', true)
            ->where('shop.google_review_url', 'https://g.page/r/northside/review')
            ->where('shop.instagram_url', 'https://instagram.com/northside')
        );
});

test('an admin can update the selected shop settings', function () {
    $shop = Shop::factory()->create();

    $this->actingAs(shopSettingsAdmin())
        ->put("/admin/shops/{$shop->id}/settings", [
            'name' => 'Updated Cafe',
            'max_stamps' => 8,
            'reward_title' => 'Free tea',
            'google_review_url' => 'https://g.page/r/updated/review',
            'google_review_direct' => true,
            'instagram_url' => 'https://instagram.com/updated',
            'wifi_ssid' => 'Guest Wi-Fi',
            'wifi_password' => 'password123',
            'contact_name' => 'Alex Owner',
            'contact_email' => 'alex@example.com',
            'contact_phone' => '+442079460000',
            'address_line1' => '1 High Street',
            'address_line2' => null,
            'town' => 'London',
            'postcode' => 'SW1A 1AA',
            'delivery_address' => null,
        ])
        ->assertRedirect();

    expect($shop->fresh())
        ->name->toBe('Updated Cafe')
        ->max_stamps->toBe(8)
        ->reward_title->toBe('Free tea')
        ->google_review_url->toBe('https://g.page/r/updated/review')
        ->google_review_direct->toBeTrue()
        ->instagram_url->toBe('https://instagram.com/updated')
        ->wifi_ssid->toBe('Guest Wi-Fi')
        ->contact_name->toBe('Alex Owner')
        ->town->toBe('London');
});

test('direct Google mode is disabled if the admin removes its URL', function () {
    $shop = Shop::factory()->create([
        'google_review_url' => 'https://g.page/r/example/review',
        'google_review_direct' => true,
    ]);

    $this->actingAs(shopSettingsAdmin())
        ->put("/admin/shops/{$shop->id}/settings", [
            'name' => $shop->name,
            'max_stamps' => $shop->max_stamps,
            'reward_title' => $shop->reward_title,
            'google_review_url' => '',
            'google_review_direct' => true,
        ])
        ->assertRedirect();

    expect($shop->fresh()->google_review_url)->toBeNull()
        ->and($shop->fresh()->google_review_direct)->toBeFalse();
});

test('only admins can configure a shop', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->get("/admin/shops/{$shop->id}/settings")->assertRedirect('/login');
    $this->actingAs($owner)->get("/admin/shops/{$shop->id}/settings")->assertForbidden();
});
