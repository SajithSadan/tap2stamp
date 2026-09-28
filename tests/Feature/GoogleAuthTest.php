<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;

beforeEach(function () {
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-client-secret',
    ]);
});

/** Makes the next Google callback return this account, without calling Google. */
function fakeGoogle(array $overrides = []): void
{
    $data = [
        'id' => 'google-123',
        'name' => 'Jamie Smith',
        'email' => 'jamie@example.com',
        'email_verified' => true,
        ...$overrides,
    ];

    $user = (new GoogleUser)
        ->setRaw($data)
        ->map(['id' => $data['id'], 'name' => $data['name'], 'email' => $data['email']]);

    Socialite::shouldReceive('driver->user')->andReturn($user);
}

test('the Google routes are hidden until Google is configured', function () {
    config(['services.google.client_id' => null]);

    $this->get('/auth/google/redirect')->assertNotFound();
    $this->get('/auth/google/callback')->assertNotFound();
});

test('the redirect sends the browser to Google', function () {
    $response = $this->get('/auth/google/redirect');

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toStartWith('https://accounts.google.com/')
        ->toContain('client_id=test-client-id')
        ->toContain(urlencode(url('/auth/google/callback')));
});

test('a new Google account becomes a shop owner and goes to shop setup', function () {
    fakeGoogle();

    $this->get('/auth/google/callback')->assertRedirect('/onboarding');

    $user = User::sole();
    expect($user)
        ->role->toBe(UserRole::Owner)
        ->google_id->toBe('google-123')
        ->name->toBe('Jamie Smith')
        ->password->toBeNull()
        ->email_verified_at->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('a returning Google owner with a shop goes straight to the dashboard', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner, 'google_id' => 'google-123', 'email' => 'old@example.com']);
    Shop::factory()->create(['user_id' => $owner->id]);
    fakeGoogle();

    $this->get('/auth/google/callback')->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($owner);
    expect(User::count())->toBe(1);
});

test('Google is linked to an owner who signed up with the same email', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner, 'email' => 'jamie@example.com', 'google_id' => null]);
    fakeGoogle(['email' => 'Jamie@Example.com']);

    $this->get('/auth/google/callback');

    $this->assertAuthenticatedAs($owner);
    expect($owner->fresh()->google_id)->toBe('google-123')
        ->and(User::count())->toBe(1);
});

test('Google is never linked to an admin account', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin, 'email' => 'jamie@example.com']);
    fakeGoogle();

    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($admin->fresh()->google_id)->toBeNull();
});

test('a Google account without a verified email is refused', function () {
    fakeGoogle(['email_verified' => false]);

    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('a failed or forged Google callback goes back to login', function () {
    Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

    $this->get('/auth/google/callback?state=forged&code=x')->assertRedirect('/login')->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a Google-only owner cannot log in with a password', function () {
    User::factory()->create(['role' => UserRole::Owner, 'email' => 'jamie@example.com', 'password' => null, 'google_id' => 'google-123']);

    $this->post('/login', ['email' => 'jamie@example.com', 'password' => 'anything'])->assertSessionHasErrors('email');

    $this->assertGuest();
});
