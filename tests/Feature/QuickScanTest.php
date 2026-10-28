<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\QrCode;
use App\Models\Shop;
use App\Models\StampLog;
use App\Models\User;
use App\Support\Features;
use App\Support\QuickScan;

beforeEach(fn () => config(['loyalty.stamp_cooldown_hours' => 8, 'loyalty.multi_stamp_gap_minutes' => 2, 'loyalty.quick_scan_link_minutes' => 15]));

/** A shop with Quick scan on (unless $on is false) and a customer with a card at it. */
function quickShop(bool $on = true, array $shop = []): array
{
    $shop = Shop::factory()->create(['max_stamps' => 6, 'features' => ['quick_scan' => $on], ...$shop]);
    $customer = Customer::factory()->create();
    $card = CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'current_stamps' => 1, 'last_stamped_at' => null]);

    return [$shop, $customer, $card];
}

function quickStamp(Shop $shop, Customer $customer, ?string $tap = null)
{
    return test()->postJson("/s/{$shop->slug}/card/{$customer->uuid}/quick-stamp", ['tap' => $tap ?? QuickScan::token($shop)]);
}

test('it starts off for every shop', function () {
    expect(Features::default(Features::QUICK_SCAN))->toBeFalse()
        ->and(Shop::factory()->create()->hasFeature(Features::QUICK_SCAN))->toBeFalse();
});

test('a scanned sticker opens the card with a signed link only when quick scan is on', function () {
    [$shop] = quickShop();
    [$offShop] = quickShop(false);
    $on = QrCode::factory()->create(['destination_url' => url("/s/{$shop->slug}"), 'shop_id' => $shop->id]);
    $off = QrCode::factory()->create(['destination_url' => url("/s/{$offShop->slug}"), 'shop_id' => $offShop->id]);
    $menu = QrCode::factory()->create(['destination_url' => url("/menu/{$shop->menu_slug}"), 'shop_id' => $shop->id]);

    $location = $this->get("/qr/{$on->code}")->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    expect(parse_url($location, PHP_URL_PATH))->toBe("/s/{$shop->slug}")
        ->and(QuickScan::valid($shop, $query['tap'] ?? null))->toBeTrue();

    $this->get("/qr/{$off->code}")->assertRedirect(url("/s/{$offShop->slug}"));
    $this->get("/qr/{$menu->code}")->assertRedirect(url("/menu/{$shop->menu_slug}"));
});

test('the Settings counter QR (?scan=1) gets a fresh link, never a lasting one', function () {
    [$shop] = quickShop();
    [$offShop] = quickShop(false);

    $location = $this->get("/s/{$shop->slug}?scan=1")->assertRedirect()->headers->get('Location');
    expect($location)->toContain('tap=');

    $this->get("/s/{$offShop->slug}?scan=1")->assertRedirect(route('card.show', $offShop->slug));
});

test('the card page gets the token only from a valid scan', function () {
    [$shop] = quickShop();
    $token = QuickScan::token($shop);

    $this->get("/s/{$shop->slug}?tap={$token}")->assertInertia(fn ($page) => $page->component('Card')->where('quickScan', $token));
    $this->get("/s/{$shop->slug}")->assertInertia(fn ($page) => $page->where('quickScan', null));
    $this->get("/s/{$shop->slug}?tap=123.abc")->assertInertia(fn ($page) => $page->where('quickScan', null));
    $this->get("/s/{$shop->slug}?tap={$token}&preview=1")->assertInertia(fn ($page) => $page->where('quickScan', null));
});

test('the card page and My Cards say when the footer\'s Scan button shows', function () {
    [$shop, $customer] = quickShop();
    [$offShop] = quickShop(false);
    [$blocked] = quickShop(shop: ['blocked_at' => now()]);
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $offShop->id]);
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $blocked->id]);

    $this->get("/s/{$shop->slug}")->assertInertia(fn ($page) => $page->where('shop.quick_scan', true));
    $this->get("/s/{$offShop->slug}")->assertInertia(fn ($page) => $page->where('shop.quick_scan', false));

    $cards = collect($this->getJson("/my-cards/{$customer->uuid}")->assertOk()->json('cards'))->pluck('quick_scan', 'shop_id');
    expect($cards->all())->toEqual([$shop->id => true, $offShop->id => false, $blocked->id => false]);
});

test('a quick scan stamps once, and a second scan waits like a staff scan', function () {
    [$shop, $customer, $card] = quickShop();

    quickStamp($shop, $customer)->assertOk()->assertJson(['code' => 'stamp_added', 'stamps' => 2]);
    quickStamp($shop, $customer)->assertStatus(409)->assertJsonPath('code', 'cooldown');

    expect($card->fresh()->current_stamps)->toBe(2)
        ->and(StampLog::where('shop_id', $shop->id)->sole()->quick_scan)->toBeTrue();

    $this->travel(9)->hours();
    quickStamp($shop, $customer)->assertOk()->assertJson(['stamps' => 3]);
});

test('a quick scan always waits the full time, even with multiple stamps a day', function () {
    [$shop, $customer, $card] = quickShop(shop: ['features' => ['quick_scan' => true, 'multiple_stamps' => true]]);

    quickStamp($shop, $customer)->assertOk();
    $this->travel(10)->minutes();
    quickStamp($shop, $customer)->assertStatus(409)->assertJsonPath('code', 'cooldown');

    expect($card->fresh()->current_stamps)->toBe(2);
});

test('a staff stamp and a quick scan in the same visit still count once', function () {
    [$shop, $customer, $card] = quickShop();
    $card->update(['last_stamped_at' => now()]);

    quickStamp($shop, $customer)->assertStatus(409)->assertJsonPath('code', 'cooldown');
    expect($card->fresh()->current_stamps)->toBe(1);
});

test('a full card is never redeemed by a quick scan', function () {
    [$shop, $customer, $card] = quickShop();
    $card->update(['current_stamps' => 6]);

    quickStamp($shop, $customer)->assertOk()->assertJsonPath('code', 'reward_ready');
    $this->postJson("/s/{$shop->slug}/card/{$customer->uuid}/quick-stamp", ['tap' => QuickScan::token($shop), 'redeem' => true])
        ->assertJsonPath('code', 'reward_ready');

    expect($card->fresh())->current_stamps->toBe(6)->rewards_claimed->toBe(0);
});

test('expired, forged and other shops\' links never stamp', function () {
    [$shop, $customer, $card] = quickShop();
    [$otherShop] = quickShop();
    $old = QuickScan::token($shop);

    $this->travel(16)->minutes();
    quickStamp($shop, $customer, $old)->assertForbidden()->assertJsonPath('code', 'tap_expired');
    quickStamp($shop, $customer, QuickScan::token($otherShop))->assertForbidden();
    quickStamp($shop, $customer, now()->addHour()->getTimestamp().'.'.str_repeat('a', 40))->assertForbidden();
    $this->postJson("/s/{$shop->slug}/card/{$customer->uuid}/quick-stamp")->assertUnprocessable();

    expect($card->fresh()->current_stamps)->toBe(1);
});

test('nothing is stamped with quick scan off, without a card, or for a blocked or paused shop', function () {
    [$offShop, $offCustomer] = quickShop(false);
    quickStamp($offShop, $offCustomer)->assertForbidden()->assertJsonPath('code', 'quick_scan_off');

    [$shop] = quickShop();
    $stranger = Customer::factory()->create();
    quickStamp($shop, $stranger)->assertStatus(409)->assertJsonPath('code', 'no_card');
    expect(CustomerShopCard::where('customer_id', $stranger->id)->exists())->toBeFalse();

    [$blocked, $blockedCustomer] = quickShop(shop: ['blocked_at' => now()]);
    quickStamp($blocked, $blockedCustomer)->assertForbidden()->assertJsonPath('code', 'shop_inactive');

    [$paused, $pausedCustomer] = quickShop(shop: ['scanning_paused_at' => now()]);
    quickStamp($paused, $pausedCustomer)->assertForbidden()->assertJsonPath('code', 'scanning_paused');

    expect(StampLog::count())->toBe(0);
});

test('the customer\'s own stamp is not logged as an owner signed in on the same phone', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    [$shop, $customer] = quickShop(shop: ['user_id' => $owner->id]);

    $this->actingAs($owner);
    quickStamp($shop, $customer)->assertOk();

    expect(ActivityLog::where('action', 'stamp.added')->exists())->toBeFalse()
        ->and(StampLog::sole())->owner_user_id->toBeNull()->staff_member_id->toBeNull();

    $this->get('/dashboard/activity')->assertInertia(fn ($page) => $page->where('activity.data.0.staff_name', 'Quick scan'));
});

test('the admin switches it per shop, and it is listed with the other features', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    [$shop] = quickShop(false);

    $this->actingAs($admin)->get('/admin/settings')->assertInertia(fn ($page) => $page
        ->where('features', fn ($features) => collect($features)->contains(fn ($f) => $f['key'] === 'quick_scan' && $f['default'] === false)));

    $this->put("/admin/shops/{$shop->id}/features", ['features' => ['quick_scan' => 'on']])->assertSessionHasNoErrors();
    expect($shop->fresh()->hasFeature(Features::QUICK_SCAN))->toBeTrue();

    $this->put('/admin/settings/features', ['feature' => 'quick_scan', 'enabled' => true])->assertSessionHasNoErrors();
    expect(Shop::factory()->create()->hasFeature(Features::QUICK_SCAN))->toBeTrue();
});
