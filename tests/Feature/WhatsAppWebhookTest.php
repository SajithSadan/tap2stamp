<?php

use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\MarketingCampaign;
use App\Models\MarketingMessage;
use App\Models\Shop;
use App\Models\ShopWhatsAppAccount;
use App\Models\WhatsAppMessage;

beforeEach(function () {
    config([
        'services.meta.app_secret' => 'meta_app_secret',
        'services.whatsapp.webhook_verify_token' => 'verify-me',
        'services.whatsapp.template' => 'shop_offer',
        'services.whatsapp.template_image' => 'shop_offer_image',
    ]);
});

function webhookShop(string $numberId = '5550001', string $wabaId = '98765'): array
{
    $shop = Shop::factory()->create();
    $account = ShopWhatsAppAccount::create([
        'shop_id' => $shop->id, 'mode' => 'new', 'waba_id' => $wabaId, 'phone_number_id' => $numberId, 'access_token' => 'EAA_x',
        'templates' => ['shop_offer' => 'PENDING', 'shop_offer_image' => 'PENDING'],
    ]);

    return [$shop, $account];
}

/** POSTs a webhook as Meta does: JSON signed with our app secret. */
function sendWebhook($test, array $payload, ?string $secret = 'meta_app_secret')
{
    $body = json_encode($payload);

    return $test->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, (string) $secret),
    ], $body);
}

function messagesChange(string $numberId, array $value): array
{
    return ['object' => 'whatsapp_business_account', 'entry' => [['id' => '98765', 'changes' => [[
        'field' => 'messages',
        'value' => ['messaging_product' => 'whatsapp', 'metadata' => ['phone_number_id' => $numberId], ...$value],
    ]]]]];
}

function offerTo(Shop $shop, string $waId): MarketingMessage
{
    $card = CustomerShopCard::factory()->create(['shop_id' => $shop->id, 'customer_id' => Customer::factory()]);
    $campaign = MarketingCampaign::create(['shop_id' => $shop->id, 'audience' => 'all', 'message' => 'Free cookie', 'recipients_count' => 1, 'sent_count' => 1, 'status' => 'sent']);

    return MarketingMessage::create(['marketing_campaign_id' => $campaign->id, 'customer_shop_card_id' => $card->id, 'status' => 'sent', 'wa_message_id' => $waId, 'sent_at' => now()]);
}

test('Meta\'s verify handshake answers the challenge only with our token', function () {
    $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')
        ->assertOk()->assertSee('12345');
    $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')
        ->assertForbidden();
});

test('an unsigned or wrongly signed webhook changes nothing', function () {
    [$shop] = webhookShop();
    $offer = offerTo($shop, 'wamid.1');
    $payload = messagesChange('5550001', ['statuses' => [['id' => 'wamid.1', 'status' => 'read', 'timestamp' => (string) now()->timestamp]]]);

    sendWebhook($this, $payload, 'someone-elses-secret')->assertUnauthorized();
    $this->postJson('/api/whatsapp/webhook', $payload)->assertUnauthorized();

    expect($offer->fresh()->read_at)->toBeNull();
});

test('delivered and read ticks land on the offer, never going back', function () {
    [$shop] = webhookShop();
    $offer = offerTo($shop, 'wamid.1');

    sendWebhook($this, messagesChange('5550001', ['statuses' => [['id' => 'wamid.1', 'status' => 'read', 'timestamp' => (string) now()->timestamp]]]))->assertOk();
    sendWebhook($this, messagesChange('5550001', ['statuses' => [['id' => 'wamid.1', 'status' => 'delivered', 'timestamp' => (string) now()->timestamp]]]))->assertOk();

    expect($offer->fresh())
        ->delivered_at->not->toBeNull()
        ->read_at->not->toBeNull()
        ->status->toBe('sent');
});

test('an offer Meta fails later counts as failed', function () {
    [$shop] = webhookShop();
    $offer = offerTo($shop, 'wamid.1');

    sendWebhook($this, messagesChange('5550001', ['statuses' => [[
        'id' => 'wamid.1', 'status' => 'failed', 'timestamp' => (string) now()->timestamp,
        'errors' => [['code' => 131026, 'title' => 'Message undeliverable']],
    ]]]))->assertOk();

    expect($offer->fresh())->status->toBe('failed')->error->toBe('Message undeliverable');
    expect($offer->campaign->fresh())->sent_count->toBe(0)->failed_count->toBe(1);
});

test('a status for another shop\'s number never touches this shop\'s offer', function () {
    [$shop] = webhookShop('5550001');
    webhookShop('5550002', '11111');
    $offer = offerTo($shop, 'wamid.1');

    sendWebhook($this, messagesChange('5550002', ['statuses' => [['id' => 'wamid.1', 'status' => 'read', 'timestamp' => (string) now()->timestamp]]]))->assertOk();

    expect($offer->fresh()->read_at)->toBeNull();
});

test('a customer\'s message is stored once, matched to their card, and STOP unsubscribes them', function () {
    [$shop] = webhookShop();
    $customer = Customer::factory()->create(['phone' => '+447700900123', 'name' => 'Priya Shah']);
    $card = CustomerShopCard::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'marketing_consent' => true]);

    $payload = messagesChange('5550001', [
        'contacts' => [['wa_id' => '447700900123', 'profile' => ['name' => 'Priya']]],
        'messages' => [['from' => '447700900123', 'id' => 'wamid.in1', 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => 'Is the offer on Sunday too?']]],
    ]);
    sendWebhook($this, $payload)->assertOk();
    sendWebhook($this, $payload)->assertOk(); // Meta resends

    expect(WhatsAppMessage::sole())
        ->shop_id->toBe($shop->id)
        ->customer_shop_card_id->toBe($card->id)
        ->phone->toBe('+447700900123')
        ->name->toBe('Priya')
        ->direction->toBe('in')
        ->body->toBe('Is the offer on Sunday too?');

    sendWebhook($this, messagesChange('5550001', [
        'messages' => [['from' => '447700900123', 'id' => 'wamid.in2', 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => 'STOP']]],
    ]))->assertOk();

    expect($card->fresh())->marketing_consent->toBeFalse()->marketing_opted_out_at->not->toBeNull();
});

test('a message to a number we don\'t know is ignored', function () {
    webhookShop();

    sendWebhook($this, messagesChange('9999999', [
        'messages' => [['from' => '447700900123', 'id' => 'wamid.x', 'type' => 'text', 'text' => ['body' => 'Hi']]],
    ]))->assertOk();

    expect(WhatsAppMessage::count())->toBe(0);
});

test('template approvals update the account; the owner\'s own templates are ignored', function () {
    [, $account] = webhookShop();
    $change = fn ($name, $event) => ['object' => 'whatsapp_business_account', 'entry' => [['id' => '98765', 'changes' => [[
        'field' => 'message_template_status_update',
        'value' => ['event' => $event, 'message_template_name' => $name, 'message_template_language' => 'en_GB'],
    ]]]]];

    sendWebhook($this, $change('shop_offer', 'APPROVED'))->assertOk();
    sendWebhook($this, $change('shop_offer_image', 'REJECTED'))->assertOk();
    sendWebhook($this, $change('their_own_template', 'APPROVED'))->assertOk();

    expect($account->fresh()->templates)->toBe(['shop_offer' => 'APPROVED', 'shop_offer_image' => 'REJECTED']);
});
