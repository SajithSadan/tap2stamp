<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\MarketingCampaign;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\RewardGameBuilder;
use App\Services\ShopMenu;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

/*
 * One shop must never read or change another shop's data. An attacker signs
 * up as an owner (or gets a staff device for their own shop) and goes after
 * a victim shop with every id they could guess. Each attempt must fail AND
 * leave the victim's data exactly as it was.
 */

/** The victim shop with one of everything; the attacker owner with their own shop. */
function victimAndAttacker(): array
{
    $victimOwner = User::factory()->create(['role' => UserRole::Owner]);
    $victim = Shop::factory()->create(['user_id' => $victimOwner->id, 'name' => 'Victim Cafe']);
    $victim->forceFill(['features' => ['menu' => true, 'whatsapp' => true]])->save();
    $member = StaffMember::factory()->create(['shop_id' => $victim->id, 'name' => 'Vic', 'pin_hash' => Hash::make('1111')]);
    $device = StaffDevice::factory()->create(['shop_id' => $victim->id, 'token_hash' => hash('sha256', 'victim-token')]);
    app(ShopMenu::class)->replace($victim, [['name' => 'Cakes', 'items' => [['name' => 'Victim Brownie', 'price' => '£3']]]]);
    $item = $victim->menuItems()->select('menu_items.*')->sole();
    $item->forceFill(['image_path' => 'menu-items/victim/photo.webp', 'image_status' => 'found'])->save();
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $victim->id, 'current_stamps' => 3]);
    $campaign = MarketingCampaign::create(['shop_id' => $victim->id, 'audience' => 'all', 'message' => 'Victim offer', 'recipients_count' => 1]);
    $order = Order::factory()->paid()->create(['shop_id' => $victim->id]);

    $attacker = User::factory()->create(['role' => UserRole::Owner]);
    $attackerShop = Shop::factory()->create(['user_id' => $attacker->id, 'name' => 'Attacker Cafe']);
    $attackerShop->forceFill(['features' => ['menu' => true, 'whatsapp' => true]])->save();
    StaffDevice::factory()->withStaff()->create(['shop_id' => $attackerShop->id, 'token_hash' => hash('sha256', 'attacker-token')]);

    return compact('victim', 'victimOwner', 'member', 'device', 'item', 'customer', 'campaign', 'order', 'attacker', 'attackerShop');
}

/** Everything about the victim that an attack could change. */
function victimSnapshot(array $v): array
{
    $v['victim']->refresh();

    return [
        'shop' => $v['victim']->only(['name', 'slug', 'user_id', 'max_stamps', 'reward_title', 'theme', 'blocked_at', 'show_card_link', 'features', 'logo_path', 'banner_path']),
        'member' => StaffMember::find($v['member']->id)?->only(['pin_hash', 'deactivated_at']),
        'device' => StaffDevice::find($v['device']->id)?->only(['token_hash', 'revoked_at']),
        'menu' => MenuItem::find($v['item']->id)?->only(['name', 'price', 'availability', 'image_path']),
        'stamps' => CustomerShopCard::where('shop_id', $v['victim']->id)->value('current_stamps'),
        'campaign' => MarketingCampaign::find($v['campaign']->id)?->only(['sent_count', 'failed_count']),
        'order' => Order::find($v['order']->id)?->only(['status', 'total_pence']),
    ];
}

test("an owner can't touch another shop's staff, devices, campaigns or orders by id", function () {
    $v = victimAndAttacker();
    $before = victimSnapshot($v);
    $this->actingAs($v['attacker']);

    $this->put("/dashboard/staff-members/{$v['member']->id}/pin", ['pin' => '9999', 'pin_confirmation' => '9999'])->assertForbidden();
    $this->delete("/dashboard/staff-members/{$v['member']->id}")->assertForbidden();
    $this->post("/dashboard/staff-devices/{$v['device']->id}/setup-link")->assertForbidden();
    $this->delete("/dashboard/staff-devices/{$v['device']->id}")->assertForbidden();
    $this->postJson("/dashboard/marketing/{$v['campaign']->id}/send")->assertNotFound();
    $this->get("/dashboard/orders/{$v['order']->id}/success")->assertNotFound();

    expect(victimSnapshot($v))->toBe($before);
});

test("an owner can't change another shop's menu items or steal its photos", function () {
    $v = victimAndAttacker();
    $before = victimSnapshot($v);
    $this->actingAs($v['attacker']);

    $this->putJson('/dashboard/menu/availability', ['item' => $v['item']->id, 'availability' => 'hidden'])->assertNotFound();
    $this->putJson('/dashboard/menu/availability', ['items' => [$v['item']->id], 'availability' => 'hidden'])->assertNotFound();
    $this->postJson('/dashboard/menu/images/upload', ['item' => $v['item']->id])->assertStatus(422); // no photo: rejected before anything
    $this->postJson('/dashboard/menu/images/confirm', ['item' => $v['item']->id, 'token' => null])->assertNotFound();

    // Saving their own menu with the victim's item id and photo path: their menu only, no stolen photo.
    $this->put('/dashboard/menu', ['sections' => [['name' => 'Mine', 'items' => [
        ['id' => $v['item']->id, 'name' => 'Victim Brownie', 'image_path' => 'menu-items/victim/photo.webp'],
    ]]]])->assertSessionHasNoErrors();

    expect(victimSnapshot($v))->toBe($before)
        ->and($v['attackerShop']->menuItems()->select('menu_items.*')->sole()->image_path)->toBeNull();
});

test("an owner's own forms can't move a shop, unblock it or turn on admin-only switches", function () {
    $v = victimAndAttacker();
    $before = victimSnapshot($v);
    $v['attackerShop']->forceFill(['blocked_at' => null, 'show_card_link' => false])->save();
    $this->actingAs($v['attacker']);

    $this->put('/dashboard/settings', [
        'name' => 'Attacker Cafe', 'max_stamps' => 8, 'reward_title' => 'Free coffee',
        // Smuggled fields - none of them are in the form's rules:
        'user_id' => $v['victimOwner']->id, 'id' => $v['victim']->id, 'slug' => $v['victim']->slug,
        'show_card_link' => true, 'features' => ['marketing' => true], 'blocked_at' => null, 'role' => 'admin',
    ])->assertSessionHasNoErrors();

    $mine = $v['attackerShop']->fresh();
    expect($mine->user_id)->toBe($v['attacker']->id)
        ->and($mine->slug)->not->toBe($v['victim']->slug)
        ->and($mine->show_card_link)->toBeFalse()
        ->and($v['attacker']->fresh()->role)->toBe(UserRole::Owner)
        ->and(victimSnapshot($v))->toBe($before);
});

test('an owner gets 403 on every admin page and admin action', function () {
    $v = victimAndAttacker();
    $before = victimSnapshot($v);
    $this->actingAs($v['attacker']);

    // Every admin GET page without a parameter...
    collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'admin') && in_array('GET', $route->methods()) && ! str_contains($route->uri(), '{'))
        ->each(fn ($route) => $this->get('/'.$route->uri())->assertForbidden());

    // ...and the admin actions that would hurt the victim.
    $this->put("/admin/shops/{$v['victim']->id}/settings", ['name' => 'Hacked'])->assertForbidden();
    $this->put("/admin/shops/{$v['victim']->id}/block")->assertForbidden();
    $this->post("/admin/shops/{$v['victim']->id}/view-as-owner")->assertForbidden();
    $this->put("/admin/shops/{$v['victim']->id}/menu", ['sections' => []])->assertForbidden();
    $this->delete("/admin/shops/{$v['victim']->id}/menu")->assertForbidden();
    $this->putJson("/admin/shops/{$v['victim']->id}/menu/availability", ['item' => $v['item']->id, 'availability' => 'hidden'])->assertForbidden();
    $this->put("/admin/shops/{$v['victim']->id}/features", ['features' => ['menu' => 'off']])->assertForbidden();

    expect(victimSnapshot($v))->toBe($before)
        ->and(session('view_as_shop_id'))->toBeNull();
});

test("another shop's staff device can't stamp, look up or change the victim's data", function () {
    $v = victimAndAttacker();
    $before = victimSnapshot($v);
    $auth = ['Authorization' => 'Bearer attacker-token'];

    // The victim's customer QR (SHOP: is the victim's id) scanned on the attacker's device.
    $this->postJson('/api/staff/scan', ['payload' => "TOKEN:{$v['customer']->uuid}|SHOP:{$v['victim']->id}"], $auth)
        ->assertForbidden()->assertJsonPath('code', 'shop_mismatch');
    $this->postJson('/api/staff/scan', ['payload' => "TOKEN:{$v['customer']->uuid}|SHOP:{$v['victim']->id}", 'redeem' => true], $auth)->assertForbidden();

    $this->putJson('/api/staff/menu/availability', ['item' => $v['item']->id, 'availability' => 'hidden'], $auth)->assertNotFound();
    $this->postJson('/api/staff/sign-in', ['staff_member_id' => $v['member']->id, 'pin' => '1111'], $auth)->assertStatus(422);

    $this->getJson('/api/staff/customers?q='.urlencode($v['customer']->name), $auth)->assertOk()->assertJsonCount(0, 'customers');
    $this->getJson('/api/staff/menu', $auth)->assertOk()->assertJsonMissing(['name' => 'Victim Brownie']);

    expect(victimSnapshot($v))->toBe($before);
});

test('sign-up can never make an admin, and an owner can only ever have one shop', function () {
    $this->post('/register', [
        'name' => 'Sneaky', 'email' => 'sneaky@example.test', 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'admin',
    ]);
    $user = User::where('email', 'sneaky@example.test')->sole();
    expect($user->role)->toBe(UserRole::Owner);

    $v = victimAndAttacker();
    // A complete, valid shop setup - so it's the one-shop rule that stops it, not validation.
    $this->actingAs($v['attacker'])->post('/onboarding', [
        'name' => 'Second shop', 'max_stamps' => 8, 'reward_title' => 'Free coffee', 'industry' => 'cafe', 'country' => 'GB',
        'contact_name' => 'Eve', 'contact_email' => 'eve@example.test', 'contact_phone' => '07700 900123',
        'address_line1' => '1 High Street', 'town' => 'Leeds', 'postcode' => 'LS1 4AP', 'delivery_same' => true,
    ])->assertSessionHasNoErrors()->assertRedirect('/dashboard');
    expect(Shop::where('user_id', $v['attacker']->id)->count())->toBe(1);
});

test("an owner can't open, change, publish or delete another shop's reward game, or steal its prizes", function () {
    $makeOwner = function (string $name) {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $shop = Shop::factory()->create(['user_id' => $owner->id, 'name' => $name]);
        $shop->forceFill(['features' => ['games' => true]])->save();

        return [$owner, $shop];
    };
    [, $victim] = $makeOwner('Victim Cafe');
    [$attacker, $attackerShop] = $makeOwner('Attacker Cafe');
    $game = app(RewardGameBuilder::class)->save($victim, null, ['name' => 'Victim wheel', 'type' => 'wheel', 'trigger' => 'every_n_stamps', 'trigger_value' => 3, 'voucher_days' => 30], [
        ['title' => 'Free coffee', 'kind' => 'item', 'weight' => 10],
        ['title' => 'Try again', 'kind' => 'no_win', 'weight' => 90],
    ]);
    $before = $game->fresh()->load('prizes')->toBuilder();
    $form = ['name' => 'Hacked', 'type' => 'wheel', 'trigger' => 'card_full', 'voucher_days' => 30, 'prizes' => [
        ['title' => 'Free car', 'kind' => 'item', 'weight' => 1],
        ['title' => 'Nothing', 'kind' => 'no_win', 'weight' => 1],
    ]];
    $this->actingAs($attacker);

    $this->get("/dashboard/games/{$game->id}/edit")->assertNotFound();
    $this->put("/dashboard/games/{$game->id}", $form)->assertNotFound();
    $this->put("/dashboard/games/{$game->id}/status", ['status' => 'live'])->assertNotFound();
    $this->delete("/dashboard/games/{$game->id}")->assertNotFound();
    // The victim's prize ids, sent with a new game of the attacker's own, are refused.
    $form['prizes'][0]['id'] = $game->prizes->first()->id;
    $this->post('/dashboard/games', $form)->assertSessionHasErrors('prizes');

    expect($game->fresh()->load('prizes')->toBuilder())->toBe($before)
        ->and($attackerShop->rewardGames()->count())->toBe(0);
});

test('every route that takes a record id is covered by an ownership check', function () {
    // Owner routes with an id in the URL: each one checks the record is the owner's shop's
    // (tested above). A NEW one must be added to the tests above, then to this list.
    $covered = [
        'dashboard/games/{game}', // found through the owner's shop (RewardGameController::find)
        'dashboard/games/{game}/edit',
        'dashboard/games/{game}/status',
        'dashboard/marketing/{campaign}/send',
        'dashboard/orders/{order}/success',
        'dashboard/staff-devices/{staffDevice}',
        'dashboard/staff-devices/{staffDevice}/setup-link',
        'dashboard/staff-members/{staffMember}',
        'dashboard/staff-members/{staffMember}/pin',
        'dashboard/menu/images/review/{token}', // the held photo records its shop (MenuItemImages::reviewImage)
    ];
    $routes = collect(Route::getRoutes()->getRoutes());

    $withIds = $routes->filter(fn ($r) => str_starts_with($r->uri(), 'dashboard') && str_contains($r->uri(), '{'))->map->uri()->unique()->sort()->values();
    expect($withIds->diff($covered)->values()->all())->toBe([]);

    // Admin pages always need the admin role; the staff API always needs a device.
    $routes->filter(fn ($r) => str_starts_with($r->uri(), 'admin'))->each(fn ($r) => expect($r->gatherMiddleware())->toContain('role:admin'));
    $routes->filter(fn ($r) => str_starts_with($r->uri(), 'api/staff'))->each(fn ($r) => expect($r->gatherMiddleware())->toContain('staff.auth'));
    $routes->filter(fn ($r) => str_starts_with($r->uri(), 'dashboard'))->each(fn ($r) => expect($r->gatherMiddleware())->toContain('role:owner'));
});
