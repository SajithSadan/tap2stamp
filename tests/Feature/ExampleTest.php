<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;

test('the home page shows guests the landing page', function () {
    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Landing'));
});

test('the home page sends signed-in users to their own home', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $newOwner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($admin)->get('/')->assertRedirect('/admin');
    $this->actingAs($owner)->get('/')->assertRedirect('/dashboard');
    $this->actingAs($newOwner)->get('/')->assertRedirect('/onboarding');
});

test('a signed-in admin opening the login page ends up in the admin', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/login')->assertRedirect('/');
    $this->actingAs($admin)->get('/')->assertRedirect('/admin');
});
