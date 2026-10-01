<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.findaddress.key' => 'fa_secret_key']);
});

function lookupUser(): User
{
    return User::factory()->create(['role' => UserRole::Owner]);
}

function findAddressReplies(array $body, int $status = 200): void
{
    Http::fake(['findaddress.io/*' => Http::response($body, $status)]);
}

test('a signed-in user can look up an address, and the key stays server-side', function () {
    findAddressReplies([
        'result' => 'Success',
        'expandedAddress' => [
            'house' => '10', 'street' => 'Downing street', 'locality' => '',
            'town' => 'London', 'district' => 'Greater london', 'county' => 'London', 'pCode' => 'SW1A 2AA',
        ],
        'statusCode' => '200',
    ]);

    $this->actingAs(lookupUser())
        ->getJson('/address-lookup?postcode=sw1a2aa&house=10')
        ->assertOk()
        ->assertExactJson([
            'status' => 'found',
            'address' => [
                'address_line1' => '10 Downing Street',
                'address_line2' => '',
                'town' => 'London',
                'postcode' => 'SW1A 2AA',
            ],
        ])
        ->assertDontSee('fa_secret_key');

    Http::assertSent(fn (Request $request) => $request->hasHeader('x-api-key', 'fa_secret_key')
        && $request['postcode'] === 'SW1A2AA'
        && $request['house'] === '10');
});

test('a house name stays on its own line', function () {
    findAddressReplies([
        'result' => 'Success',
        'expandedAddress' => ['house' => 'Rose Cottage', 'street' => 'Mill lane', 'locality' => 'Headingley', 'town' => 'Leeds', 'pCode' => 'LS6 1AA'],
    ]);

    $this->actingAs(lookupUser())
        ->getJson('/address-lookup?postcode=LS6 1AA&house=Rose Cottage')
        ->assertJsonPath('address.address_line1', 'Rose Cottage, Mill Lane')
        ->assertJsonPath('address.address_line2', 'Headingley');
});

test('a partial match keeps the full postcode the user typed', function () {
    findAddressReplies([
        'result' => 'Partial match',
        'expandedAddress' => ['house' => '10', 'street' => '', 'locality' => '', 'town' => 'London', 'pCode' => 'SW1A'],
    ]);

    $this->actingAs(lookupUser())
        ->getJson('/address-lookup?postcode=SW1A 2AA&house=10')
        ->assertOk()
        ->assertJsonPath('status', 'partial')
        ->assertJsonPath('address.address_line1', '10')
        ->assertJsonPath('address.postcode', 'SW1A 2AA');
});

test('an unknown postcode is a 404 the user can fix', function () {
    findAddressReplies(['result' => 'Failure', 'errorMsg' => 'Postal Code Not Valid', 'statusCode' => '400'], 400);

    $this->actingAs(lookupUser())
        ->getJson('/address-lookup?postcode=ZZ1 1ZZ&house=1')
        ->assertNotFound()
        ->assertJsonPath('status', 'not_found');
});

test('provider problems (key, credits) become a 503 without leaking details', function () {
    findAddressReplies(['result' => 'Failure', 'errorMsg' => 'You have exceeded your daily limit', 'statusCode' => '429'], 429);

    $this->actingAs(lookupUser())
        ->getJson('/address-lookup?postcode=SW1A 2AA&house=10')
        ->assertStatus(503)
        ->assertJsonPath('status', 'unavailable')
        ->assertDontSee('daily limit');
});

test('found addresses are cached, so a repeat lookup is free', function () {
    findAddressReplies(['result' => 'Success', 'expandedAddress' => ['house' => '1', 'street' => 'High street', 'town' => 'Leeds', 'pCode' => 'LS1 4AP']]);
    $user = lookupUser();

    $this->actingAs($user)->getJson('/address-lookup?postcode=LS1 4AP&house=1')->assertOk();
    $this->actingAs($user)->getJson('/address-lookup?postcode=ls14ap&house=1')->assertOk();

    Http::assertSentCount(1);
});

test('the lookup checks its input before spending a credit', function (array $query, string $field) {
    Http::fake();

    $this->actingAs(lookupUser())
        ->getJson('/address-lookup?'.http_build_query($query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    Http::assertNothingSent();
})->with([
    [['postcode' => 'SW1A 2AA'], 'house'],
    [['house' => '10'], 'postcode'],
    [['house' => '10', 'postcode' => 'not a postcode'], 'postcode'],
]);

test('guests cannot use the lookup', function () {
    Http::fake();

    $this->getJson('/address-lookup?postcode=SW1A 2AA&house=10')->assertUnauthorized();

    Http::assertNothingSent();
});

test('without an API key the lookup is off', function () {
    config(['services.findaddress.key' => null]);

    $this->actingAs(lookupUser())->getJson('/address-lookup?postcode=SW1A 2AA&house=10')->assertNotFound();
    $this->actingAs(lookupUser())->get('/onboarding')->assertInertia(fn ($page) => $page->where('addressLookup', false));
});

test('pages tell signed-in users when the lookup is available', function () {
    $this->actingAs(lookupUser())->get('/onboarding')->assertInertia(fn ($page) => $page->where('addressLookup', true));
});

test('admins can use the lookup on a shop\'s settings page', function () {
    findAddressReplies(['result' => 'Success', 'expandedAddress' => ['house' => '1', 'street' => 'High street', 'town' => 'Leeds', 'pCode' => 'LS1 4AP']]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $shop = Shop::factory()->create();

    $this->actingAs($admin)->get("/admin/shops/{$shop->id}/settings")->assertInertia(fn ($page) => $page->where('addressLookup', true));
    $this->actingAs($admin)->getJson('/address-lookup?postcode=LS1 4AP&house=1')->assertOk()->assertJsonPath('address.address_line1', '1 High Street');
});
