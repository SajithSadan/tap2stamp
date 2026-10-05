<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\MarketingCampaign;
use App\Models\MarketingMessage;
use App\Models\Shop;
use App\Models\User;
use App\Services\WhatsAppGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'services.whatsapp.token' => 'wa_secret_token',
        'services.whatsapp.phone_number_id' => '123456',
        'services.whatsapp.template' => 'shop_offer',
        'services.whatsapp.template_language' => 'en_GB',
        'services.whatsapp.api_version' => 'v21.0',
        'loyalty.marketing_batch_size' => 2,
    ]);
});

function marketingOwner(): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'name' => 'Bean There']);

    return [$owner, $shop];
}

function optedIn(Shop $shop, array $attributes = [], array $customer = []): CustomerShopCard
{
    return CustomerShopCard::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => Customer::factory()->create(['name' => 'Priya Shah', ...$customer]),
        'marketing_consent' => true,
        'marketing_consent_at' => now(),
        ...$attributes,
    ]);
}

function whatsAppAccepts(): void
{
    Http::fake(['graph.facebook.com/*' => Http::sequence()->whenEmpty(Http::response(['messages' => [['id' => 'wamid.ok']]]))]);
}

// --- Page ---------------------------------------------------------------

test('the owner sees how many customers opted in, per audience', function () {
    [$owner, $shop] = marketingOwner();
    optedIn($shop, ['last_stamped_at' => now()->subDays(2)]);
    optedIn($shop, ['last_stamped_at' => now()->subDays(60)]);
    CustomerShopCard::factory()->create(['shop_id' => $shop->id]); // not opted in
    optedIn(Shop::factory()->create()); // another shop

    $this->actingAs($owner)->get('/dashboard/marketing')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard/Marketing')
            ->where('audiences', ['all' => 2, 'active' => 1, 'lapsed' => 1])
            ->where('customersCount', 3)
            ->where('whatsappReady', true)
            ->where('templateBody', WhatsAppGateway::TEMPLATE_BODY));
});

// --- Sending ------------------------------------------------------------

test('a campaign goes to opted-in customers only, in batches, with an unsubscribe link', function () {
    [$owner, $shop] = marketingOwner();
    $cards = collect([optedIn($shop, customer: ['phone' => '+447700900123']), optedIn($shop), optedIn($shop)]);
    CustomerShopCard::factory()->create(['shop_id' => $shop->id]);
    whatsAppAccepts();

    $this->actingAs($owner)->post('/dashboard/marketing', [
        'audience' => 'all',
        'message' => "Half-price cakes\nafter 3pm today!",
    ])->assertSessionHasNoErrors();

    $campaign = MarketingCampaign::sole();
    expect($campaign->recipients_count)->toBe(3)
        ->and($campaign->message)->toBe('Half-price cakes after 3pm today!');

    $this->actingAs($owner)->postJson("/dashboard/marketing/{$campaign->id}/send")
        ->assertOk()->assertJson(['sent' => 2, 'done' => false]);
    $this->actingAs($owner)->postJson("/dashboard/marketing/{$campaign->id}/send")
        ->assertOk()->assertJson(['sent' => 3, 'failed' => 0, 'done' => true]);

    expect($campaign->fresh()->status)->toBe('sent');
    Http::assertSentCount(3);

    $token = $cards->first()->fresh()->marketing_unsubscribe_token;
    Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v21.0/123456/messages'
        && $request->hasHeader('Authorization', 'Bearer wa_secret_token')
        && $request['to'] === '447700900123'
        && $request['template']['name'] === 'shop_offer'
        && $request['template']['components'][0]['parameters'][0]['text'] === 'Priya'
        && $request['template']['components'][0]['parameters'][1]['text'] === 'Bean There'
        && $request['template']['components'][1]['sub_type'] === 'url'
        && $request['template']['components'][1]['parameters'][0]['text'] === $token);
});

test('a poster goes as the image header, using the image template', function () {
    Storage::fake('uploads');
    config(['services.whatsapp.template_image' => 'shop_offer_image']);
    [$owner, $shop] = marketingOwner();
    optedIn($shop);
    whatsAppAccepts();

    $this->actingAs($owner)->post('/dashboard/marketing', [
        'audience' => 'all',
        'message' => 'Our autumn menu is here!',
        'poster' => UploadedFile::fake()->image('autumn.jpg', 1080, 1350),
    ])->assertSessionHasNoErrors();

    $campaign = MarketingCampaign::sole();
    Storage::disk('uploads')->assertExists($campaign->image_path);
    expect($campaign->image_path)->toStartWith("marketing/{$shop->id}/")
        ->and($campaign->posterUrl())->toStartWith('http');

    $this->actingAs($owner)->postJson("/dashboard/marketing/{$campaign->id}/send")->assertJson(['sent' => 1]);

    Http::assertSent(fn (Request $request) => $request['template']['name'] === 'shop_offer_image'
        && $request['template']['components'][0]['type'] === 'header'
        && $request['template']['components'][0]['parameters'][0]['image']['link'] === $campaign->posterUrl()
        && $request['template']['components'][1]['type'] === 'body');
});

test('without a poster the plain template has no header', function () {
    [$owner, $shop] = marketingOwner();
    optedIn($shop);
    whatsAppAccepts();

    $this->actingAs($owner)->post('/dashboard/marketing', ['audience' => 'all', 'message' => 'Free cookie with any coffee.']);
    $this->actingAs($owner)->postJson('/dashboard/marketing/'.MarketingCampaign::sole()->id.'/send');

    Http::assertSent(fn (Request $request) => $request['template']['name'] === 'shop_offer'
        && collect($request['template']['components'])->pluck('type')->all() === ['body', 'button']);
});

test('a poster must be a JPG or PNG up to 5 MB', function () {
    Storage::fake('uploads');
    [$owner, $shop] = marketingOwner();
    optedIn($shop);

    $this->actingAs($owner)->post('/dashboard/marketing', [
        'audience' => 'all',
        'message' => 'Free cookie with any coffee.',
        'poster' => UploadedFile::fake()->create('poster.gif', 100, 'image/gif'),
    ])->assertSessionHasErrors('poster');

    $this->actingAs($owner)->post('/dashboard/marketing', [
        'audience' => 'all',
        'message' => 'Free cookie with any coffee.',
        'poster' => UploadedFile::fake()->image('huge.jpg')->size(6000),
    ])->assertSessionHasErrors('poster');

    expect(MarketingCampaign::count())->toBe(0);
});

test('a failed message is recorded, the rest still go', function () {
    [$owner, $shop] = marketingOwner();
    optedIn($shop);
    optedIn($shop);
    Http::fake(['graph.facebook.com/*' => Http::sequence()
        ->push(['error' => ['message' => 'Recipient is not a WhatsApp user']], 400)
        ->push(['messages' => [['id' => 'wamid.ok']]])]);

    $this->actingAs($owner)->post('/dashboard/marketing', ['audience' => 'all', 'message' => 'Free cookie with any coffee.']);
    $campaign = MarketingCampaign::sole();

    $this->actingAs($owner)->postJson("/dashboard/marketing/{$campaign->id}/send")
        ->assertJson(['sent' => 1, 'failed' => 1, 'done' => true]);

    expect(MarketingMessage::where('status', 'failed')->value('error'))->toBe('Recipient is not a WhatsApp user');
});

test('someone who unsubscribes after the campaign starts is skipped', function () {
    [$owner, $shop] = marketingOwner();
    $card = optedIn($shop);
    whatsAppAccepts();

    $this->actingAs($owner)->post('/dashboard/marketing', ['audience' => 'all', 'message' => 'Free cookie with any coffee.']);
    $card->update(['marketing_consent' => false]);

    $this->actingAs($owner)->postJson('/dashboard/marketing/'.MarketingCampaign::sole()->id.'/send')
        ->assertJson(['sent' => 0, 'failed' => 1, 'done' => true]);

    Http::assertNothingSent();
});

test('one campaign a day, and nothing without WhatsApp set up or anyone to send to', function () {
    [$owner, $shop] = marketingOwner();
    whatsAppAccepts();

    $this->actingAs($owner)->post('/dashboard/marketing', ['audience' => 'all', 'message' => 'Free cookie with any coffee.'])
        ->assertSessionHasErrors(['message' => 'Nobody in that group has opted in yet.']);

    optedIn($shop);
    $this->actingAs($owner)->post('/dashboard/marketing', ['audience' => 'all', 'message' => 'Free cookie with any coffee.'])
        ->assertSessionHasNoErrors();
    $this->actingAs($owner)->post('/dashboard/marketing', ['audience' => 'all', 'message' => 'Another one, same day.'])
        ->assertSessionHasErrors('message');

    $this->travel(25)->hours();
    config(['services.whatsapp.token' => null]);
    $this->actingAs($owner)->post('/dashboard/marketing', ['audience' => 'all', 'message' => 'Free cookie with any coffee.'])
        ->assertSessionHasErrors(['message' => "WhatsApp sending isn't switched on yet."]);

    expect(MarketingCampaign::count())->toBe(1);
});

test('an owner cannot send another shop\'s campaign', function () {
    [$owner] = marketingOwner();
    [, $otherShop] = marketingOwner();
    optedIn($otherShop);
    $campaign = MarketingCampaign::create(['shop_id' => $otherShop->id, 'audience' => 'all', 'message' => 'Not yours', 'recipients_count' => 1]);
    Http::fake();

    $this->actingAs($owner)->postJson("/dashboard/marketing/{$campaign->id}/send")->assertNotFound();

    Http::assertNothingSent();
});

test('an admin viewing as the owner cannot send, even with changes allowed', function () {
    [$owner, $shop] = marketingOwner();
    optedIn($shop);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Http::fake();

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->put('/admin/view-as-owner/editing', ['editing' => true]);

    $this->post('/dashboard/marketing', ['audience' => 'all', 'message' => 'Free cookie with any coffee.'])
        ->assertSessionHasErrors('view_as');

    expect(MarketingCampaign::count())->toBe(0);
});

// --- Unsubscribe --------------------------------------------------------

test('the unsubscribe link only asks; the button withdraws consent for that shop', function () {
    [, $shop] = marketingOwner();
    $card = optedIn($shop);
    $token = $card->unsubscribeToken();

    $this->get("/u/{$token}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Unsubscribe')
            ->where('shopName', 'Bean There')
            ->where('subscribed', true));
    expect($card->fresh()->marketing_consent)->toBeTrue();

    $this->post("/u/{$token}")->assertRedirect("/u/{$token}");

    expect($card->fresh())
        ->marketing_consent->toBeFalse()
        ->marketing_opted_out_at->not->toBeNull();

    $this->get('/u/'.str_repeat('a', 32))->assertNotFound();
});
