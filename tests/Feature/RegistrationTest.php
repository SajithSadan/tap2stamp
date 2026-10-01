<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function validRegistration(array $overrides = []): array
{
    return [
        'name' => 'Jamie Smith',
        'email' => 'jamie@example.com',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
        ...$overrides,
    ];
}

function validShop(array $overrides = []): array
{
    return [
        'name' => 'The Coffee Corner',
        'slug' => 'the-coffee-corner',
        'max_stamps' => 6,
        'reward_title' => 'Free coffee after 6 stamps',
        'contact_name' => 'Jamie Smith',
        'contact_email' => 'jamie@coffeecorner.co.uk',
        'contact_phone' => '07700 900123',
        'address_line1' => '12 High Street',
        'address_line2' => '',
        'town' => 'Leeds',
        'postcode' => 'ls1 4ap',
        'delivery_same' => true,
        'delivery_address' => '',
        ...$overrides,
    ];
}

// --- Sign-up --------------------------------------------------------------

test('the register page renders, without the Google button until it is configured', function () {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

    $this->get('/register')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Auth/Register')
        ->where('googleEnabled', false)
    );
});

test('the Google button shows once Google is configured', function () {
    config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

    $this->get('/register')->assertInertia(fn ($page) => $page->where('googleEnabled', true));
    $this->get('/login')->assertInertia(fn ($page) => $page->where('googleEnabled', true));
});

test('someone can sign up as a shop owner and is sent to shop setup', function () {
    $response = $this->post('/register', validRegistration());

    $response->assertRedirect('/onboarding');

    $user = User::where('email', 'jamie@example.com')->sole();
    expect($user->role)->toBe(UserRole::Owner)
        ->and(Hash::check('secret-pass-123', $user->password))->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

test('sign-up can never create an admin', function () {
    $this->post('/register', validRegistration(['role' => 'admin']));

    expect(User::where('email', 'jamie@example.com')->sole()->role)->toBe(UserRole::Owner);
});

test('sign-up validates the email and password', function (array $overrides, string $field) {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post('/register', validRegistration($overrides))->assertSessionHasErrors($field);

    $this->assertGuest();
})->with([
    'email taken' => [['email' => 'taken@example.com'], 'email'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'mismatched confirmation' => [['password_confirmation' => 'something-else'], 'password'],
    'no name' => [['name' => ''], 'name'],
]);

test('a logged-in user cannot open the register page', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->get('/register')->assertRedirect();
});

// --- Shop setup -----------------------------------------------------------

test('an owner without a shop is sent to shop setup from every dashboard page', function (string $url) {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->get($url)->assertRedirect('/onboarding');
})->with(['/dashboard', '/dashboard/customers', '/dashboard/settings', '/dashboard/theme']);

test('logging in without a shop goes to shop setup', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner, 'password' => 'password']);

    $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->assertRedirect('/onboarding');
});

test('the shop setup page renders for an owner without a shop', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner, 'name' => 'Jamie Smith']);

    $this->actingAs($owner)->get('/onboarding')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Onboarding/Shop')
        ->where('ownerName', 'Jamie Smith')
        ->where('ownerEmail', $owner->email)
    );
});

test('an owner can set up their shop, which goes live straight away', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/onboarding', validShop())
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status');

    $shop = Shop::sole();
    expect($shop->user_id)->toBe($owner->id)
        // The card link is made from the name - owners don't pick it.
        ->and($shop->slug)->toBe('the-coffee-corner')
        ->and($shop->show_card_link)->toBeFalse()
        ->and($shop->max_stamps)->toBe(6);

    $this->actingAs($owner)->get('/dashboard')->assertOk();
    $this->get('/s/the-coffee-corner')->assertOk();
});

test('an owner can only ever set up one shop', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->get('/onboarding')->assertRedirect('/dashboard');
    $this->actingAs($owner)->post('/onboarding', validShop())->assertRedirect('/dashboard');

    expect(Shop::count())->toBe(1);
});

test('shop setup validates the stamps and reward', function (array $overrides, string $field) {
    Shop::factory()->create(['slug' => 'taken']);
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/onboarding', validShop($overrides))->assertSessionHasErrors($field);

    expect(Shop::where('user_id', $owner->id)->exists())->toBeFalse();
})->with([
    'too few stamps' => [['max_stamps' => 2], 'max_stamps'],
    'too many stamps' => [['max_stamps' => 21], 'max_stamps'],
    'no reward' => [['reward_title' => ''], 'reward_title'],
]);

test('the business step checks the business details without saving anything', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $business = array_diff_key(validShop(), array_flip(['slug', 'max_stamps', 'reward_title']));

    $this->actingAs($owner)->from('/onboarding')->post('/onboarding/business', $business)
        ->assertRedirect('/onboarding')
        ->assertSessionHasNoErrors();

    expect(Shop::count())->toBe(0);
});

test('the business step rejects bad business details', function (array $overrides, string $field) {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->from('/onboarding')->post('/onboarding/business', validShop($overrides))
        ->assertRedirect('/onboarding')
        ->assertSessionHasErrors($field);
})->with([
    'no name' => [['name' => ''], 'name'],
    'bad phone' => [['contact_phone' => '12345'], 'contact_phone'],
    'no town' => [['town' => ''], 'town'],
]);

test('a passed business step is kept as a draft, so a reload resumes on the loyalty card step', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/onboarding/business', validShop())->assertSessionHasNoErrors();

    $this->actingAs($owner)->get('/onboarding')->assertInertia(fn ($page) => $page
        ->component('Onboarding/Shop')
        ->where('draft.name', 'The Coffee Corner')
        ->where('draft.contact_phone', '+447700900123')
        ->where('draft.postcode', 'LS1 4AP')
        ->missing('draft.slug')
    );
});

test('a failed business step leaves no draft, and creating the shop clears it', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/onboarding/business', validShop(['town' => '']))->assertSessionHasErrors('town');
    $this->actingAs($owner)->get('/onboarding')->assertInertia(fn ($page) => $page->where('draft', null));

    $this->actingAs($owner)->post('/onboarding/business', validShop());
    expect($owner->fresh()->onboarding_draft)->not->toBeNull();

    $this->actingAs($owner)->post('/onboarding', validShop())->assertRedirect('/dashboard');

    expect($owner->fresh()->onboarding_draft)->toBeNull();
});

test('the business draft survives logging out and back in', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/onboarding/business', validShop());
    $this->post('/logout');
    $this->flushSession();

    $this->actingAs($owner->fresh())->get('/onboarding')->assertInertia(fn ($page) => $page
        ->where('draft.name', 'The Coffee Corner')
        ->where('draft.town', 'Leeds')
    );
});

test('the business step does not check the loyalty card fields', function () {
    Shop::factory()->create(['slug' => 'taken']);
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/onboarding/business', validShop(['slug' => 'taken', 'reward_title' => '']))
        ->assertSessionHasNoErrors();
});

test('shop setup accepts any stamp count from 3 to 20', function (int $stamps) {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/onboarding', validShop(['max_stamps' => $stamps]))->assertRedirect('/dashboard');

    expect(Shop::sole()->max_stamps)->toBe($stamps);
})->with([3, 20]);

test('admins and guests cannot use shop setup', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->get('/onboarding')->assertRedirect('/login');
    $this->actingAs($admin)->get('/onboarding')->assertForbidden();
    $this->actingAs($admin)->post('/onboarding', validShop())->assertForbidden();
    $this->actingAs($admin)->post('/onboarding/business', validShop())->assertForbidden();

    expect(Shop::count())->toBe(0);
});

test('a taken card link gets a number, and anything sent as a link is ignored', function () {
    Shop::factory()->create(['slug' => 'the-coffee-corner']);
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/onboarding', validShop(['slug' => 'my-own-pick']))->assertSessionHasNoErrors();

    expect(Shop::where('user_id', $owner->id)->sole()->slug)->toBe('the-coffee-corner-2');
});
