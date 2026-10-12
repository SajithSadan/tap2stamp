<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use App\Models\User;

/**
 * The three home-screen apps: each its own manifest id (so they install as
 * separate apps) and its own start address.
 */
test('customers, staff and owners each get their own home-screen app', function () {
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id]);

    $owner = json_decode(file_get_contents(public_path('owner.webmanifest')), true);
    $staff = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
    $cards = $this->get("/my-cards/app/{$customer->uuid}.webmanifest")->assertOk()->json();

    expect([$owner['id'], $staff['id'], $cards['id']])->toBe(['/business', '/staff', '/my-cards'])
        ->and($owner['start_url'])->toBe('/login')
        ->and($staff['start_url'])->toBe('/staff')
        ->and($cards['start_url'])->toBe("/my-cards?c={$customer->uuid}");
});

test('the owner app opens on the dashboard when signed in, else the login form', function () {
    $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Login'));

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner)->get('/login')->assertRedirect('/');
    $this->actingAs($owner)->get('/')->assertRedirect('/dashboard');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)->get('/')->assertRedirect($admin->homeUrl());
});

test('"Keep me signed in" sets a remember cookie; unticked, it does not', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner, 'password' => bcrypt('secret-pass-123')]);
    $recaller = auth()->guard('web')->getRecallerName();

    $this->post('/login', ['email' => $owner->email, 'password' => 'secret-pass-123', 'remember' => true])
        ->assertCookie($recaller);
    expect($owner->fresh()->remember_token)->not->toBeNull();

    $this->post('/logout');
    $this->post('/login', ['email' => $owner->email, 'password' => 'secret-pass-123', 'remember' => false])
        ->assertCookieMissing($recaller);
});
