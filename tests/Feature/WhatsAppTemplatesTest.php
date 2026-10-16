<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\MarketingCampaign;
use App\Models\Shop;
use App\Models\ShopWhatsAppAccount;
use App\Models\User;
use App\Services\WhatsAppGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'app.url' => 'https://tadatap.test',
        'services.whatsapp.api_version' => 'v21.0',
        'services.whatsapp.template' => 'shop_offer',
        'services.whatsapp.template_image' => 'shop_offer_image',
        'services.whatsapp.template_language' => 'en_GB',
    ]);
});

function templatesShop(?array $templates = null): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $account = ShopWhatsAppAccount::create([
        'shop_id' => $shop->id, 'mode' => 'new', 'waba_id' => '98765', 'phone_number_id' => '5550001',
        'access_token' => 'EAA_shop_token', 'templates' => $templates,
    ]);
    CustomerShopCard::factory()->create([
        'shop_id' => $shop->id, 'customer_id' => Customer::factory(), 'marketing_consent' => true, 'marketing_consent_at' => now(),
    ]);

    return [$owner, $shop, $account];
}

/** Meta: neither template on the account yet; creating answers PENDING; the sample image uploads. */
function metaTemplatesMissing(): void
{
    Http::fake([
        'graph.facebook.com/v21.0/98765/message_templates?*' => Http::response(['data' => []]),
        'graph.facebook.com/v21.0/98765/message_templates' => Http::response(['id' => '777', 'status' => 'PENDING', 'category' => 'MARKETING']),
        'graph.facebook.com/v21.0/app' => Http::response(['id' => '111222333']),
        'graph.facebook.com/v21.0/111222333/uploads*' => Http::response(['id' => 'upload:abc']),
        'graph.facebook.com/v21.0/upload:abc' => Http::response(['h' => 'sample-handle']),
    ]);
}

test('both offer templates are created on the shop\'s account, with our exact wording', function () {
    [$owner, , $account] = templatesShop();
    metaTemplatesMissing();

    $this->actingAs($owner)->post('/dashboard/whatsapp/templates')->assertSessionHas('status');

    expect($account->fresh()->templates)->toBe(['shop_offer' => 'PENDING', 'shop_offer_image' => 'PENDING']);

    Http::assertSent(fn (Request $r) => $r->method() === 'POST'
        && $r->url() === 'https://graph.facebook.com/v21.0/98765/message_templates'
        && $r['name'] === 'shop_offer' && $r['category'] === 'MARKETING' && $r['language'] === 'en_GB'
        && $r['components'][0]['text'] === WhatsAppGateway::TEMPLATE_BODY
        && $r['components'][1]['buttons'][0]['url'] === 'https://tadatap.test/u/{{1}}'
        && $r->hasHeader('Authorization', 'Bearer EAA_shop_token'));
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && ($r['name'] ?? null) === 'shop_offer_image'
        && $r['components'][0] === ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => ['sample-handle']]]);
});

test('templates already on the account are read, not created again', function () {
    [$owner, , $account] = templatesShop();
    Http::fake(['graph.facebook.com/v21.0/98765/message_templates?*' => fn (Request $r) => Http::response(['data' => [
        ['name' => $r['name'], 'status' => 'APPROVED', 'language' => 'en_GB'],
    ]])]);

    $this->actingAs($owner)->post('/dashboard/whatsapp/templates');

    expect($account->fresh()->templates)->toBe(['shop_offer' => 'APPROVED', 'shop_offer_image' => 'APPROVED']);
    Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
});

test('connecting a number puts the templates on it straight away', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    config(['services.meta.app_id' => '111222333', 'services.meta.app_secret' => 'secret', 'services.meta.embedded_signup_config_id' => '444']);
    metaTemplatesMissing();
    Http::fake([
        'graph.facebook.com/v21.0/oauth/access_token*' => Http::response(['access_token' => 'EAA_shop_token']),
        'graph.facebook.com/v21.0/98765/phone_numbers*' => Http::response(['data' => [['id' => '5550001', 'display_phone_number' => '+44 7700 900123']]]),
        'graph.facebook.com/*' => Http::response(['success' => true]),
    ]);

    $this->actingAs($owner)->post('/dashboard/whatsapp/connection', ['mode' => 'existing', 'code' => 'c', 'waba_id' => '98765', 'phone_number_id' => '5550001'])
        ->assertSessionHasNoErrors();

    expect($shop->fresh()->whatsappAccount->templates)->toBe(['shop_offer' => 'PENDING', 'shop_offer_image' => 'PENDING']);
});

test('nothing is sent until Meta approves the template; a poster needs its own', function () {
    Storage::fake('uploads');
    [$owner, , $account] = templatesShop(['shop_offer' => 'PENDING', 'shop_offer_image' => 'PENDING']);
    $account->update(['templates_checked_at' => now()]); // just checked: no call to Meta
    Http::fake();

    $this->actingAs($owner)->post('/dashboard/marketing', ['audience' => 'all', 'message' => 'Free cookie with any coffee.'])
        ->assertSessionHasErrors(['message' => "Meta hasn't approved your offer message yet. It usually takes a few minutes, sometimes up to a day."]);

    $account->update(['templates' => ['shop_offer' => 'APPROVED', 'shop_offer_image' => 'REJECTED']]);
    $this->actingAs($owner)->post('/dashboard/marketing', [
        'audience' => 'all', 'message' => 'Free cookie with any coffee.', 'poster' => UploadedFile::fake()->image('p.jpg'),
    ])->assertSessionHasErrors(['message' => "Meta hasn't approved offers with a poster yet. Send it without the poster, or try again later."]);

    expect(MarketingCampaign::count())->toBe(0);
    Http::assertNothingSent();

    $this->actingAs($owner)->post('/dashboard/marketing', ['audience' => 'all', 'message' => 'Free cookie with any coffee.'])
        ->assertSessionHasNoErrors();
    expect(MarketingCampaign::count())->toBe(1);
});

test('the page asks Meta about a waiting template, but not on every load', function () {
    [$owner, , $account] = templatesShop(['shop_offer' => 'PENDING', 'shop_offer_image' => 'PENDING']);
    Http::fake(['graph.facebook.com/v21.0/98765/message_templates?*' => fn (Request $r) => Http::response(['data' => [
        ['name' => $r['name'], 'status' => 'APPROVED', 'language' => 'en_GB'],
    ]])]);

    $this->actingAs($owner)->get('/dashboard/marketing')
        ->assertInertia(fn ($page) => $page->component('Dashboard/Marketing')
            ->where('templatesReady', ['offer' => true, 'poster' => true])
            ->where('whatsappAccount.templates', ['offer' => 'APPROVED', 'poster' => 'APPROVED']));
    Http::assertSentCount(2);

    // All approved: no more checks.
    $this->actingAs($owner)->get('/dashboard/marketing');
    Http::assertSentCount(2);
});
