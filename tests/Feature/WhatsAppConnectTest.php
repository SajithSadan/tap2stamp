<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\ShopWhatsAppAccount;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.whatsapp.api_version' => 'v21.0',
        'services.meta.app_id' => '111222333',
        'services.meta.app_secret' => 'meta_app_secret',
        'services.meta.embedded_signup_config_id' => '444555666',
    ]);
});

function waOwner(): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    return [$owner, Shop::factory()->create(['user_id' => $owner->id])];
}

/** Meta answers: the code swaps for a token, and business account 98765 has number 5550001. */
function metaAccepts(array $numbers = [['id' => '5550001', 'display_phone_number' => '+44 7700 900123', 'verified_name' => 'Chicken Corner']]): void
{
    Http::fake([
        'graph.facebook.com/v21.0/oauth/access_token*' => Http::response(['access_token' => 'EAA_shop_token']),
        'graph.facebook.com/v21.0/98765/phone_numbers*' => Http::response(['data' => $numbers]),
        'graph.facebook.com/v21.0/5550001/register' => Http::response(['success' => true]),
        'graph.facebook.com/v21.0/98765/subscribed_apps' => Http::response(['success' => true]),
    ]);
}

function connectPayload(array $overrides = []): array
{
    return ['mode' => 'new', 'code' => 'meta-one-time-code', 'waba_id' => '98765', 'phone_number_id' => '5550001', ...$overrides];
}

// --- Owner: Meta's popup ----------------------------------------------------

test('the owner page offers the popup only when our Meta app is set up, never the secret', function () {
    [$owner] = waOwner();

    $this->actingAs($owner)->get('/dashboard/marketing')
        ->assertInertia(fn ($page) => $page->component('Dashboard/Marketing')
            ->where('whatsappReady', false)
            ->where('whatsappAccount', null)
            ->where('embeddedSignup', ['app_id' => '111222333', 'config_id' => '444555666', 'api_version' => 'v21.0']));

    config(['services.meta.app_secret' => null]);
    $this->actingAs($owner)->get('/dashboard/marketing')
        ->assertInertia(fn ($page) => $page->where('embeddedSignup', null));
});

test('a new number: the code is swapped server-side, the number registered, the token stored encrypted', function () {
    [$owner, $shop] = waOwner();
    metaAccepts();

    $this->actingAs($owner)->post('/dashboard/whatsapp/connection', connectPayload())
        ->assertSessionHasNoErrors()->assertSessionHas('status');

    $account = $shop->fresh()->whatsappAccount;
    expect($account)
        ->mode->toBe('new')
        ->phone_number_id->toBe('5550001')
        ->display_phone_number->toBe('+44 7700 900123')
        ->verified_name->toBe('Chicken Corner')
        ->access_token->toBe('EAA_shop_token')
        ->connected_by->toBe($owner->id);
    // Encrypted at rest.
    expect(DB::table('shop_whatsapp_accounts')->value('access_token'))->not->toContain('EAA_shop_token');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'oauth/access_token')
        && $r['client_secret'] === 'meta_app_secret' && $r['code'] === 'meta-one-time-code');
    Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/v21.0/5550001/register'
        && $r->hasHeader('Authorization', 'Bearer EAA_shop_token'));

    $this->actingAs($owner)->get('/dashboard/marketing')
        ->assertInertia(fn ($page) => $page->where('whatsappReady', true)
            ->where('whatsappAccount.phone', '+44 7700 900123')
            ->missing('whatsappAccount.access_token'));
});

test('the WhatsApp Business app number (existing) is not registered again', function () {
    [$owner, $shop] = waOwner();
    metaAccepts();

    $this->actingAs($owner)->post('/dashboard/whatsapp/connection', connectPayload(['mode' => 'existing']))->assertSessionHasNoErrors();

    expect($shop->fresh()->whatsappAccount->mode)->toBe('existing');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/register'));
});

test('ids from the browser that the token cannot reach are refused', function () {
    [$owner, $shop] = waOwner();
    metaAccepts([['id' => '7770001', 'display_phone_number' => '+44 7700 900777']]);

    $this->actingAs($owner)->post('/dashboard/whatsapp/connection', connectPayload())
        ->assertSessionHasErrors(['whatsapp' => "That number isn't in this WhatsApp Business account."]);

    expect($shop->fresh()->whatsappAccount)->toBeNull();
});

test('a code Meta rejects connects nothing', function () {
    [$owner, $shop] = waOwner();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid code']], 400)]);

    $this->actingAs($owner)->post('/dashboard/whatsapp/connection', connectPayload())
        ->assertSessionHasErrors('whatsapp');

    expect($shop->fresh()->whatsappAccount)->toBeNull();
});

test('one number at a time: another only after Remove', function () {
    [$owner, $shop] = waOwner();
    metaAccepts();

    $this->actingAs($owner)->post('/dashboard/whatsapp/connection', connectPayload())->assertSessionHasNoErrors();
    $this->actingAs($owner)->post('/dashboard/whatsapp/connection', connectPayload())
        ->assertSessionHasErrors(['whatsapp' => 'This shop already has a WhatsApp number. Remove it first to connect another.']);

    $this->actingAs($owner)->delete('/dashboard/whatsapp/connection')->assertSessionHas('status');
    expect($shop->fresh()->whatsappAccount)->toBeNull();

    $this->actingAs($owner)->post('/dashboard/whatsapp/connection', connectPayload(['mode' => 'existing']))->assertSessionHasNoErrors();
    expect(ShopWhatsAppAccount::count())->toBe(1);
});

test('connecting needs our Meta app set up, and well-formed ids', function () {
    [$owner, $shop] = waOwner();
    Http::fake();

    $this->actingAs($owner)->post('/dashboard/whatsapp/connection', connectPayload(['waba_id' => 'abc']))
        ->assertSessionHasErrors('waba_id');
    $this->actingAs($owner)->post('/dashboard/whatsapp/connection', connectPayload(['mode' => 'keys']))
        ->assertSessionHasErrors('mode');

    config(['services.meta.embedded_signup_config_id' => null]);
    $this->actingAs($owner)->post('/dashboard/whatsapp/connection', connectPayload())
        ->assertSessionHasErrors(['whatsapp' => "Connecting WhatsApp isn't switched on yet."]);

    expect($shop->fresh()->whatsappAccount)->toBeNull();
    Http::assertNothingSent();
});

test('an admin viewing as the owner cannot connect or remove the owner\'s number', function () {
    [, $shop] = waOwner();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    metaAccepts();

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->put('/admin/view-as-owner/editing', ['editing' => true]);

    $this->post('/dashboard/whatsapp/connection', connectPayload())->assertSessionHasErrors('view_as');
    expect($shop->fresh()->whatsappAccount)->toBeNull();
});

// --- Owner: their own Meta keys ---------------------------------------------

function keysPayload(array $overrides = []): array
{
    return ['waba_id' => '98765', 'phone_number_id' => '5550001', 'access_token' => 'EAA_permanent_system_user_token', ...$overrides];
}

test('the owner connects with the keys from their own Meta app, checked with Meta first', function () {
    [$owner, $shop] = waOwner();
    metaAccepts();

    $this->actingAs($owner)->post('/dashboard/whatsapp/connection/keys', keysPayload())->assertSessionHasNoErrors();

    expect($shop->fresh()->whatsappAccount)
        ->mode->toBe('keys')
        ->access_token->toBe('EAA_permanent_system_user_token')
        ->display_phone_number->toBe('+44 7700 900123')
        ->connected_by->toBe($owner->id);
    // No code swap and no registering: the number is already set up in their Meta app.
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/register') || str_contains($r->url(), 'oauth'));
});

test('keys work even before our Meta app is set up', function () {
    [$owner, $shop] = waOwner();
    config(['services.meta.app_id' => null, 'services.meta.app_secret' => null]);
    metaAccepts();

    $this->actingAs($owner)->post('/dashboard/whatsapp/connection/keys', keysPayload())->assertSessionHasNoErrors();
    expect($shop->fresh()->whatsappAccount)->not->toBeNull();
});

test('wrong keys are refused and nothing is saved', function () {
    [$owner, $shop] = waOwner();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 401)]);

    $this->actingAs($owner)->post('/dashboard/whatsapp/connection/keys', keysPayload())
        ->assertSessionHasErrors(['access_token' => "Meta didn't accept those details. Check the business account and token."]);
    $this->actingAs($owner)->post('/dashboard/whatsapp/connection/keys', keysPayload(['phone_number_id' => '+447700900123']))
        ->assertSessionHasErrors('phone_number_id');

    expect($shop->fresh()->whatsappAccount)->toBeNull();
});

test('an admin viewing as the owner cannot enter keys either', function () {
    [, $shop] = waOwner();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    metaAccepts();

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->put('/admin/view-as-owner/editing', ['editing' => true]);

    $this->post('/dashboard/whatsapp/connection/keys', keysPayload())->assertSessionHasErrors('view_as');
    expect($shop->fresh()->whatsappAccount)->toBeNull();
});

// --- Admin: see and remove only ---------------------------------------------

test('the admin sees the shop\'s number and can remove it, but never connects one', function () {
    [$owner, $shop] = waOwner();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    metaAccepts();
    $this->actingAs($owner)->post('/dashboard/whatsapp/connection/keys', keysPayload());

    $this->actingAs($admin)->get("/admin/shops/{$shop->id}/settings")
        ->assertInertia(fn ($page) => $page->component('Admin/ShopSettings')
            ->where('whatsappAccount.phone_number_id', '5550001')
            ->where('whatsappAccount.mode', 'keys')
            ->missing('whatsappAccount.access_token'));

    $this->actingAs($admin)->delete("/admin/shops/{$shop->id}/whatsapp")->assertSessionHas('status');
    expect($shop->fresh()->whatsappAccount)->toBeNull();

    // There's no admin connect route at all.
    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/whatsapp", keysPayload())->assertStatus(405);
    expect($shop->fresh()->whatsappAccount)->toBeNull();
});

test('an owner cannot remove another shop\'s number through the admin route', function () {
    [$owner] = waOwner();
    [, $otherShop] = waOwner();
    ShopWhatsAppAccount::create(['shop_id' => $otherShop->id, 'mode' => 'keys', 'waba_id' => '98765', 'phone_number_id' => '5550001', 'access_token' => 'EAA_other']);

    $this->actingAs($owner)->delete("/admin/shops/{$otherShop->id}/whatsapp")->assertForbidden();
    expect($otherShop->fresh()->whatsappAccount)->not->toBeNull();
});
