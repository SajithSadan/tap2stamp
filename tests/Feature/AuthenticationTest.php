<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;

test('the login page renders', function () {
    $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

test('an owner can log in and is redirected to the dashboard', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner, 'password' => 'password']);
    Shop::factory()->create(['user_id' => $owner->id]);

    $response = $this->post('/login', ['email' => $owner->email, 'password' => 'password']);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($owner);
});

test('an admin can log in and is redirected to the admin panel', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin, 'password' => 'password']);

    $response = $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

    $response->assertRedirect('/admin');
    $this->assertAuthenticatedAs($admin);
});

test('an invalid password is rejected', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner, 'password' => 'password']);

    $response = $this->post('/login', ['email' => $owner->email, 'password' => 'wrong-password']);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('a logged-in user can log out', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});
