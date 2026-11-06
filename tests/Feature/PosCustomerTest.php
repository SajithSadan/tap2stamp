<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\PosCustomer;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

function posOnboarding(array $overrides = []): array
{
    return [
        'restaurant_name' => 'Mandhi House',
        'contact_name' => 'Ajmal K',
        'email' => 'ajmal@example.com',
        'phone' => '+91 98765 43210',
        'address_line1' => '12 Beach Road',
        'town' => 'Kozhikode',
        'state' => 'Kerala',
        'postcode' => '673001',
        'country' => 'in',
        'username' => 'MandhiHouse',
        'password' => 'secret123',
        ...$overrides,
    ];
}

function posRegister(array $overrides = []): array
{
    $response = test()->postJson('/api/pos/register', posOnboarding($overrides))->assertCreated();

    return [PosCustomer::findOrFail($response->json('customer.id')), $response->json('token')];
}

test('a restaurant onboards from the billing app and gets a token', function () {
    $response = $this->postJson('/api/pos/register', posOnboarding())->assertCreated()
        ->assertJsonPath('customer.restaurant_name', 'Mandhi House')
        ->assertJsonPath('customer.username', 'mandhihouse')
        ->assertJsonPath('customer.country', 'IN')
        ->assertJsonMissingPath('customer.password')
        ->assertJsonMissingPath('customer.api_token_hash');

    expect($response->json('token'))->toHaveLength(64);

    $customer = PosCustomer::firstOrFail();
    // Readable by us (encrypted, not hashed) - but not stored as typed.
    $raw = DB::table('pos_customers')->value('password');
    expect($raw)->not->toBe('secret123')
        ->and(Crypt::decryptString($raw))->toBe('secret123')
        ->and($customer->password)->toBe('secret123')
        ->and($customer->api_token_hash)->toBe(hash('sha256', $response->json('token')));

    expect(ActivityLog::where('action', 'pos.registered')->first())
        ->actor_type->toBe('pos')
        ->actor_name->toBe('Mandhi House (mandhihouse)');
});

test('onboarding needs the details and a free username', function () {
    posRegister();

    $this->postJson('/api/pos/register', posOnboarding(['username' => 'MANDHIHOUSE', 'restaurant_name' => '', 'country' => 'XX', 'password' => '123']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['username', 'restaurant_name', 'country', 'password']);

    $this->postJson('/api/pos/register', posOnboarding(['username' => 'has space']))->assertJsonValidationErrors('username');
});

test('signing in checks the password and replaces the token', function () {
    [$customer, $oldToken] = posRegister();

    $this->postJson('/api/pos/login', ['username' => 'mandhihouse', 'password' => 'wrong'])->assertJsonValidationErrors('username');
    $this->postJson('/api/pos/login', ['username' => 'nobody', 'password' => 'secret123'])->assertJsonValidationErrors('username');

    $token = $this->postJson('/api/pos/login', ['username' => ' MandhiHouse ', 'password' => 'secret123'])->assertOk()
        ->assertJsonPath('customer.id', $customer->id)
        ->json('token');

    expect($token)->not->toBe($oldToken)
        ->and($customer->fresh()->last_login_at)->not->toBeNull();
    $this->getJson('/api/pos/me', ['Authorization' => "Bearer {$oldToken}"])->assertUnauthorized();
    $this->getJson('/api/pos/me', ['Authorization' => "Bearer {$token}"])->assertOk()->assertJsonPath('customer.username', 'mandhihouse');
});

test('the account endpoints need the token', function () {
    $this->getJson('/api/pos/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    $this->putJson('/api/pos/me', ['town' => 'X'])->assertUnauthorized();
    $this->putJson('/api/pos/password', [])->assertUnauthorized();
    $this->getJson('/api/pos/me', ['Authorization' => 'Bearer nope'])->assertUnauthorized();
});

test('editing details changes only what is sent and logs before / after', function () {
    [$customer, $token] = posRegister();

    $this->putJson('/api/pos/me', ['town' => 'Calicut', 'phone' => '+91 90000 00000', 'username' => 'hacker', 'password' => 'x'], ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('customer.town', 'Calicut')
        ->assertJsonPath('customer.restaurant_name', 'Mandhi House');

    $customer->refresh();
    expect($customer->username)->toBe('mandhihouse')->and($customer->password)->toBe('secret123');
    expect(ActivityLog::where('action', 'pos.updated')->first()->changes)
        ->toBe(['phone' => ['+91 98765 43210', '+91 90000 00000'], 'town' => ['Kozhikode', 'Calicut']]);
});

test('changing the password needs the current one and is logged without the passwords', function () {
    [$customer, $token] = posRegister();
    $auth = ['Authorization' => "Bearer {$token}"];

    $this->putJson('/api/pos/password', ['current_password' => 'wrong', 'password' => 'newpass99', 'password_confirmation' => 'newpass99'], $auth)
        ->assertJsonValidationErrors('current_password');
    $this->putJson('/api/pos/password', ['current_password' => 'secret123', 'password' => 'newpass99', 'password_confirmation' => 'nope'], $auth)
        ->assertJsonValidationErrors('password');

    $this->putJson('/api/pos/password', ['current_password' => 'secret123', 'password' => 'newpass99', 'password_confirmation' => 'newpass99'], $auth)->assertOk();

    expect($customer->fresh()->password)->toBe('newpass99');
    $log = ActivityLog::where('action', 'pos.password_changed')->firstOrFail();
    expect($log->changes)->toBe(['password' => ['•••', 'changed']])
        ->and(json_encode($log->toArray()))->not->toContain('newpass99')->not->toContain('secret123');

    $this->postJson('/api/pos/login', ['username' => 'mandhihouse', 'password' => 'secret123'])->assertJsonValidationErrors('username');
    $this->postJson('/api/pos/login', ['username' => 'mandhihouse', 'password' => 'newpass99'])->assertOk();
});

test('the API shares the password only in its encrypted form', function () {
    $response = $this->postJson('/api/pos/register', posOnboarding())->assertCreated();
    $encrypted = $response->json('customer.password_encrypted');

    expect($encrypted)->toBe(DB::table('pos_customers')->value('password'))
        ->and(Crypt::decryptString($encrypted))->toBe('secret123')
        ->and($response->getContent())->not->toContain('secret123');

    $token = $response->json('token');
    $this->putJson('/api/pos/password', ['current_password' => 'secret123', 'password' => 'newpass99', 'password_confirmation' => 'newpass99'], ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('customer.password_encrypted', fn ($value) => Crypt::decryptString($value) === 'newpass99');
});

test('only the admin sees the plain password, one at a time, and each look is logged', function () {
    [$customer] = posRegister();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/admin/pos-customers')->assertInertia(fn ($page) => $page
        ->component('Admin/PosCustomers')
        ->where('customers.0.username', 'mandhihouse')
        ->where('customers.0.place', 'Kozhikode, Kerala')
        ->where('customers.0.country_code', 'IN')
        ->where('customers.0.country', 'India')
        ->missing('customers.0.password'));

    $this->postJson("/admin/pos-customers/{$customer->id}/password")->assertOk()->assertJsonPath('password', 'secret123');
    expect(ActivityLog::where('action', 'pos.password_viewed')->first())
        ->actor_type->toBe('admin')
        ->user_id->toBe($admin->id);

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->actingAs($owner)->get('/admin/pos-customers')->assertForbidden();
    $this->postJson("/admin/pos-customers/{$customer->id}/password")->assertForbidden();
});

test('signing out stops the token working', function () {
    [, $token] = posRegister();

    $this->postJson('/api/pos/logout', [], ['Authorization' => "Bearer {$token}"])->assertOk();
    $this->getJson('/api/pos/me', ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
});

function posActivate(string $token, ?string $key, string $device = 'device-aaaaaaaaaaaaaaaa')
{
    return test()->postJson('/api/pos/activate', ['activation_key' => $key, 'device_id' => $device], ['Authorization' => "Bearer {$token}"]);
}

test('registering makes a one-time activation key that is never sent to the app', function () {
    $response = $this->postJson('/api/pos/register', posOnboarding())->assertCreated()
        ->assertJsonMissingPath('customer.activation_key');
    $customer = PosCustomer::findOrFail($response->json('customer.id'));

    expect($customer->activation_key)->toMatch('/^\d{8}$/')
        ->and(DB::table('pos_customers')->value('activation_key'))->not->toBe($customer->activation_key)
        ->and($customer->activationKeyState())->toBe('active')
        ->and($customer->activation_key_expires_at->isFuture())->toBeTrue();
});

test('the activation key unlocks one PC once, and only that PC afterwards', function () {
    [$customer, $token] = posRegister();
    $key = $customer->activation_key;
    $spoken = PosCustomer::formatActivationKey($key); // "1234-5678", as read out

    posActivate($token, $spoken)->assertOk()->assertJsonPath('activated', true)->assertJsonPath('device_activated', true);
    expect($customer->fresh())->activationKeyState()->toBe('used')
        ->activated_device_id->toBe('device-aaaaaaaaaaaaaaaa');
    expect(ActivityLog::where('action', 'pos.activated')->count())->toBe(1);

    // Reinstalling on the same PC: the same key works again there.
    posActivate($token, $key)->assertOk();
    // Another PC: refused.
    posActivate($token, $key, 'device-bbbbbbbbbbbbbbbb')->assertStatus(422)->assertJsonPath('code', 'used')->assertJsonValidationErrors('activation_key');

    $this->getJson('/api/pos/me', ['Authorization' => "Bearer {$token}", 'X-POS-Device' => 'device-aaaaaaaaaaaaaaaa'])->assertJsonPath('device_activated', true);
    $this->getJson('/api/pos/me', ['Authorization' => "Bearer {$token}", 'X-POS-Device' => 'device-bbbbbbbbbbbbbbbb'])->assertJsonPath('device_activated', false);
});

test('wrong activation keys are counted and lock the key', function () {
    [$customer, $token] = posRegister();
    $wrong = $customer->activation_key === '00000000' ? '11111111' : '00000000';

    posActivate($token, $wrong)->assertStatus(422)->assertJsonPath('code', 'wrong')->assertJsonPath('message', 'That activation key is wrong. 4 tries left.');
    foreach (range(1, 3) as $_) {
        posActivate($token, $wrong)->assertJsonPath('code', 'wrong');
    }
    posActivate($token, $wrong)->assertJsonPath('code', 'locked');
    // Even the right key, once locked.
    posActivate($token, $customer->activation_key)->assertStatus(422)->assertJsonPath('code', 'locked');
});

test('an expired activation key is refused', function () {
    [$customer, $token] = posRegister();
    $this->travel(PosCustomer::ACTIVATION_KEY_HOURS + 1)->hours();

    posActivate($token, $customer->activation_key)->assertStatus(422)->assertJsonPath('code', 'expired');
});

test('activating needs the token and a device id', function () {
    [$customer, $token] = posRegister();

    $this->postJson('/api/pos/activate', ['activation_key' => $customer->activation_key, 'device_id' => 'device-aaaaaaaaaaaaaaaa'])->assertUnauthorized();
    posActivate($token, $customer->activation_key, 'short')->assertJsonValidationErrors('device_id');
});

test('a new key from the admin moves the app to another PC', function () {
    [$customer, $token] = posRegister();
    posActivate($token, $customer->activation_key)->assertOk();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/admin/pos-customers')->assertInertia(fn ($page) => $page
        ->where('customers.0.activation', 'used')
        ->missing('customers.0.activation_key'));

    $this->postJson("/admin/pos-customers/{$customer->id}/activation-key")->assertOk()
        ->assertJsonPath('key', PosCustomer::formatActivationKey($customer->activation_key));
    $new = $this->postJson("/admin/pos-customers/{$customer->id}/activation-key/new")->assertOk()
        ->assertJsonPath('state', 'active')
        ->json('key');
    expect(ActivityLog::where('action', 'pos.activation_key_viewed')->count())->toBe(1)
        ->and(ActivityLog::where('action', 'pos.activation_key_issued')->count())->toBe(1);

    // The old PC still works until the new key is used elsewhere.
    expect($customer->fresh()->isActivatedOn('device-aaaaaaaaaaaaaaaa'))->toBeTrue();
    posActivate($token, $new, 'device-bbbbbbbbbbbbbbbb')->assertOk();
    expect($customer->fresh())
        ->isActivatedOn('device-aaaaaaaaaaaaaaaa')->toBeFalse()
        ->isActivatedOn('device-bbbbbbbbbbbbbbbb')->toBeTrue();

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->actingAs($owner)->postJson("/admin/pos-customers/{$customer->id}/activation-key")->assertForbidden();
    $this->actingAs($owner)->postJson("/admin/pos-customers/{$customer->id}/activation-key/new")->assertForbidden();
});
