<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\MarketingCampaign;
use App\Models\MarketingMessage;
use App\Models\Shop;
use App\Models\ShopWhatsAppAccount;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.whatsapp.api_version' => 'v21.0']);
});

function inboxShop(): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    ShopWhatsAppAccount::create([
        'shop_id' => $shop->id, 'mode' => 'existing', 'waba_id' => '98765', 'phone_number_id' => '5550001', 'access_token' => 'EAA_shop',
        'templates' => ['shop_offer' => 'APPROVED', 'shop_offer_image' => 'APPROVED'], 'templates_checked_at' => now(),
    ]);

    return [$owner, $shop];
}

function customerSays(Shop $shop, string $phone, string $body, $at = null): WhatsAppMessage
{
    return WhatsAppMessage::create([
        'shop_id' => $shop->id, 'phone' => $phone, 'direction' => 'in', 'body' => $body,
        'wa_message_id' => 'wamid.'.uniqid(), 'created_at' => $at ?? now(),
    ]);
}

test('the owner sees their chats with unread counts, and opening one marks it seen', function () {
    [$owner, $shop] = inboxShop();
    $customer = Customer::factory()->create(['phone' => '+447700900123', 'name' => 'Priya Shah']);
    $card = CustomerShopCard::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'marketing_consent' => true]);
    $campaign = MarketingCampaign::create(['shop_id' => $shop->id, 'audience' => 'all', 'message' => 'Half-price cakes today', 'recipients_count' => 1, 'status' => 'sent']);
    MarketingMessage::create(['marketing_campaign_id' => $campaign->id, 'customer_shop_card_id' => $card->id, 'status' => 'sent', 'sent_at' => now()->subHour(), 'delivered_at' => now()]);
    customerSays($shop, '+447700900123', 'Sunday too?');
    customerSays(Shop::factory()->create(), '+447700900999', 'Another shop\'s chat');

    $this->actingAs($owner)->get('/dashboard/marketing')
        ->assertInertia(fn ($page) => $page->component('Dashboard/Marketing')
            ->where('unreadChats', 1)
            ->has('chats', 1)
            ->where('chats.0.name', 'Priya Shah')
            ->where('chats.0.unread', 1)
            ->where('chats.0.last', 'Sunday too?'));

    $this->actingAs($owner)->getJson('/dashboard/whatsapp/chat?phone=%2B447700900123')
        ->assertOk()
        ->assertJsonPath('name', 'Priya Shah')
        ->assertJsonPath('can_reply', true)
        ->assertJsonPath('messages.0.kind', 'offer')
        ->assertJsonPath('messages.0.status', 'delivered')
        ->assertJsonPath('messages.1.body', 'Sunday too?');

    expect(WhatsAppMessage::where('shop_id', $shop->id)->whereNull('seen_at')->count())->toBe(0);
});

test('another shop\'s chat shows nothing', function () {
    [$owner] = inboxShop();
    customerSays(Shop::factory()->create(), '+447700900999', 'Not yours');

    $this->actingAs($owner)->getJson('/dashboard/whatsapp/chat?phone=%2B447700900999')
        ->assertOk()->assertJsonCount(0, 'messages')->assertJsonPath('can_reply', false);
});

test('the owner replies from the shop\'s number within 24 hours', function () {
    [$owner, $shop] = inboxShop();
    customerSays($shop, '+447700900123', 'Sunday too?');
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.reply']]])]);

    $this->actingAs($owner)->postJson('/dashboard/whatsapp/chat', ['phone' => '+447700900123', 'body' => 'Yes, all weekend!'])
        ->assertOk()
        ->assertJsonPath('messages.1.body', 'Yes, all weekend!')
        ->assertJsonPath('messages.1.status', 'sent');

    Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/v21.0/5550001/messages'
        && $r['to'] === '447700900123' && $r['type'] === 'text' && $r['text']['body'] === 'Yes, all weekend!'
        && $r->hasHeader('Authorization', 'Bearer EAA_shop'));
    expect(WhatsAppMessage::where('direction', 'out')->sole())
        ->wa_message_id->toBe('wamid.reply')->user_id->toBe($owner->id);
});

test('no reply after 24 hours, or to someone who never wrote', function () {
    [$owner, $shop] = inboxShop();
    customerSays($shop, '+447700900123', 'Old message', now()->subHours(25));
    Http::fake();

    $this->actingAs($owner)->postJson('/dashboard/whatsapp/chat', ['phone' => '+447700900123', 'body' => 'Hello'])
        ->assertJsonValidationErrors(['body' => 'WhatsApp only allows a reply within 24 hours of their last message.']);
    $this->actingAs($owner)->postJson('/dashboard/whatsapp/chat', ['phone' => '+447700900555', 'body' => 'Hello'])
        ->assertJsonValidationErrors('body');

    Http::assertNothingSent();
});

test('an admin viewing as the owner can read a chat but not reply, and doesn\'t mark it seen', function () {
    [, $shop] = inboxShop();
    customerSays($shop, '+447700900123', 'Sunday too?');
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Http::fake();

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->put('/admin/view-as-owner/editing', ['editing' => true]);

    $this->getJson('/dashboard/whatsapp/chat?phone=%2B447700900123')->assertOk()->assertJsonPath('messages.0.body', 'Sunday too?');
    $this->postJson('/dashboard/whatsapp/chat', ['phone' => '+447700900123', 'body' => 'Hi'])->assertForbidden();

    expect(WhatsAppMessage::whereNull('seen_at')->count())->toBe(1);
    Http::assertNothingSent();
});
