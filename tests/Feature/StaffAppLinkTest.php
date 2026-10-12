<?php

use App\Models\Shop;
use App\Models\StaffDevice;
use App\Services\StaffAppLinks;

/** An approved device for a shop, signed in with the bearer token 'app-token'. */
function appLinkDevice(): StaffDevice
{
    return StaffDevice::factory()->create(['shop_id' => Shop::factory()->create()->id, 'token_hash' => hash('sha256', 'app-token')]);
}

function appLinkCode(): string
{
    return test()->postJson('/api/staff/app-link', [], ['Authorization' => 'Bearer app-token'])->assertOk()->json('code');
}

test('an approved device gets a one-time link to add the app to the home screen', function () {
    appLinkDevice();

    $response = $this->postJson('/api/staff/app-link', [], ['Authorization' => 'Bearer app-token'])->assertOk();

    expect($response->json('code'))->toMatch('/^[A-Za-z0-9]{40}$/')
        ->and($response->json('url'))->toBe('/staff?link='.$response->json('code'))
        // The link never carries the device token itself.
        ->and($response->json('url'))->not->toContain('app-token');
});

test('the link needs an approved device', function () {
    $this->postJson('/api/staff/app-link')->assertUnauthorized();
    $this->postJson('/api/staff/app-link', [], ['Authorization' => 'Bearer nope'])->assertUnauthorized();
});

test('the installed app swaps the code for the device token, once', function () {
    appLinkDevice();
    $code = appLinkCode();

    $this->postJson('/staff/app/redeem', ['code' => $code])->assertOk()->assertJson(['token' => 'app-token']);
    $this->postJson('/staff/app/redeem', ['code' => $code])->assertNotFound()->assertJson(['code' => 'link_expired']);
});

test('a code stops working when the device is revoked or the code is too old', function () {
    $device = appLinkDevice();
    $revokedCode = appLinkCode();
    $device->update(['revoked_at' => now()]);
    $this->postJson('/staff/app/redeem', ['code' => $revokedCode])->assertNotFound();

    $device->update(['revoked_at' => null]);
    $oldCode = appLinkCode();
    $this->travel(StaffAppLinks::LIFETIME_HOURS + 1)->hours();
    $this->postJson('/staff/app/redeem', ['code' => $oldCode])->assertNotFound();
});

test('made-up codes get nothing', function () {
    appLinkDevice();

    $this->postJson('/staff/app/redeem', ['code' => str_repeat('a', 40)])->assertNotFound();
    $this->postJson('/staff/app/redeem', ['code' => '../../etc'])->assertNotFound();
    $this->postJson('/staff/app/redeem', [])->assertUnprocessable();
});

test('the install manifest starts the app at the link carrying the code', function () {
    $code = str_repeat('Ab1', 13).'X';

    $manifest = $this->get("/staff/app/{$code}.webmanifest")->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->json();

    expect($manifest['start_url'])->toBe("/staff?link={$code}")
        ->and($manifest['scope'])->toBe('/staff')
        ->and($manifest['name'])->toBe('Tada Tap Staff Scanner');

    $this->get('/staff/app/not-a-code.webmanifest')->assertNotFound();
});
