<?php

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;

function settingsAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function withGoogleKeys(): void
{
    config(['services.google.client_id' => '1234567890abcdef.apps.googleusercontent.com', 'services.google.client_secret' => 'secret']);
}

test('only the admin can open or change settings', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id]);

    $this->get('/admin/settings')->assertRedirect('/login');
    $this->actingAs($owner)->get('/admin/settings')->assertForbidden();
    $this->actingAs($owner)->put('/admin/settings/google', ['enabled' => false])->assertForbidden();

    expect(Setting::count())->toBe(0);
});

test('the settings page shows the Google status and setup details', function () {
    withGoogleKeys();
    User::factory()->create(['role' => UserRole::Owner, 'google_id' => 'g-1', 'password' => null]);
    User::factory()->create(['role' => UserRole::Owner, 'google_id' => 'g-2']); // has a password too

    $this->actingAs(settingsAdmin())->get('/admin/settings')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/Settings')
        ->where('google.enabled', true)
        ->where('google.configured', true)
        ->where('google.client_id_hint', '1234567890ab…')
        ->where('google.redirect_uri', url('/auth/google/callback'))
        ->where('google.google_only_owners', 1)
    );
});

test('Google is on by default once the keys are set', function () {
    withGoogleKeys();

    $this->get('/register')->assertInertia(fn ($page) => $page->where('googleEnabled', true));
    $this->get('/auth/google/redirect')->assertRedirect();
});

test('turning Google off hides the button and closes the Google routes', function () {
    withGoogleKeys();

    $this->actingAs(settingsAdmin())->put('/admin/settings/google', ['enabled' => false])->assertSessionHas('status');
    expect(Setting::get(Setting::GOOGLE_AUTH))->toBeFalse();

    auth()->logout();
    $this->get('/register')->assertInertia(fn ($page) => $page->where('googleEnabled', false));
    $this->get('/login')->assertInertia(fn ($page) => $page->where('googleEnabled', false));
    $this->get('/auth/google/redirect')->assertNotFound();
    $this->get('/auth/google/callback')->assertNotFound();
});

test('turning Google back on restores it', function () {
    withGoogleKeys();
    Setting::set(Setting::GOOGLE_AUTH, false);

    $this->actingAs(settingsAdmin())->put('/admin/settings/google', ['enabled' => true])->assertSessionHasNoErrors();

    expect(Setting::get(Setting::GOOGLE_AUTH))->toBeTrue();
});

test('Google cannot be turned on without the keys', function () {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
    Setting::set(Setting::GOOGLE_AUTH, false);

    $this->actingAs(settingsAdmin())->put('/admin/settings/google', ['enabled' => true])->assertSessionHasErrors('enabled');

    expect(Setting::get(Setting::GOOGLE_AUTH))->toBeFalse();
});

test('the switch needs a true or false value', function () {
    $this->actingAs(settingsAdmin())->put('/admin/settings/google', ['enabled' => 'maybe'])->assertSessionHasErrors('enabled');
});

test('the admin can set the sidebar colours, shared with every dashboard page', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)->put('/admin/settings/sidebar', ['bg' => '#1a2b3c', 'text' => '#fafafa'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Setting::get(Setting::SIDEBAR_COLORS))->toBe(['bg' => '#1A2B3C', 'text' => '#FAFAFA']);

    $this->get('/admin/settings')->assertInertia(fn ($page) => $page
        ->where('sidebar', ['bg' => '#1A2B3C', 'text' => '#FAFAFA'])
        ->where('sidebarColors', ['bg' => '#1A2B3C', 'text' => '#FAFAFA']));
});

test('one sidebar colour can stay on the default', function () {
    $this->actingAs(settingsAdmin())->put('/admin/settings/sidebar', ['bg' => null, 'text' => '#FFEEDD'])->assertSessionHasNoErrors();

    expect(Setting::get(Setting::SIDEBAR_COLORS))->toBe(['bg' => null, 'text' => '#FFEEDD']);
});

test('clearing both sidebar colours goes back to the default look', function () {
    Setting::set(Setting::SIDEBAR_COLORS, ['bg' => '#111111', 'text' => '#EEEEEE']);

    $this->actingAs(settingsAdmin())->put('/admin/settings/sidebar', ['bg' => null, 'text' => null])->assertSessionHasNoErrors();

    expect(Setting::get(Setting::SIDEBAR_COLORS))->toBeNull();
});

test('sidebar colours must be hex codes', function () {
    $this->actingAs(settingsAdmin())->put('/admin/settings/sidebar', ['bg' => 'red', 'text' => '#12345'])->assertSessionHasErrors(['bg', 'text']);

    expect(Setting::get(Setting::SIDEBAR_COLORS))->toBeNull();
});

test('an owner cannot change the sidebar colours', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->put('/admin/settings/sidebar', ['bg' => '#000000'])->assertForbidden();

    expect(Setting::get(Setting::SIDEBAR_COLORS))->toBeNull();
});
