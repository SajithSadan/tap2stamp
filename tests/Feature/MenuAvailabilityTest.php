<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\MenuItem;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\User;
use App\Services\ShopMenu;

/** A shop (with an owner and a signed-in staff device) and a saved two-section menu. */
function availabilityShop(string $token = 'menu-token'): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', $token)]);
    app(ShopMenu::class)->replace($shop, [
        ['name' => 'Cakes', 'items' => [['name' => 'Brownie', 'price' => '£3'], ['name' => 'Carrot cake', 'price' => '£4']]],
        ['name' => 'Specials', 'items' => [['name' => 'Soup of the day', 'price' => '£5']]],
    ]);

    return [$shop, $owner];
}

function availabilityItem(Shop $shop, string $name): MenuItem
{
    return $shop->menuItems()->where('menu_items.name', $name)->select('menu_items.*')->sole();
}

test('items start available', function () {
    [$shop] = availabilityShop();

    expect($shop->menuItems()->pluck('availability')->unique()->all())->toBe(['available']);
});

test('the owner marks an item not available or hidden, straight away and logged', function () {
    [$shop, $owner] = availabilityShop();
    $brownie = availabilityItem($shop, 'Brownie');

    $this->actingAs($owner)->putJson('/dashboard/menu/availability', ['item' => $brownie->id, 'availability' => 'unavailable'])
        ->assertOk()->assertJson(['id' => $brownie->id, 'availability' => 'unavailable']);

    expect($brownie->fresh()->availability)->toBe('unavailable')
        ->and(ActivityLog::where('action', 'menu.availability')->sole()->description)->toBe('Marked Brownie as not available');

    $this->putJson('/dashboard/menu/availability', ['item' => $brownie->id, 'availability' => 'sold'])->assertJsonValidationErrors('availability');
});

test("an owner can't change another shop's items, and the admin can change any shop's", function () {
    [$shop] = availabilityShop();
    [$otherShop, $otherOwner] = availabilityShop('other-token');
    $brownie = availabilityItem($shop, 'Brownie');

    $this->actingAs($otherOwner)->putJson('/dashboard/menu/availability', ['item' => $brownie->id, 'availability' => 'hidden'])->assertNotFound();

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->putJson("/admin/shops/{$shop->id}/menu/availability", ['item' => $brownie->id, 'availability' => 'hidden'])
        ->assertOk();
    expect($brownie->fresh()->availability)->toBe('hidden');
});

test('the public menu greys out unavailable items and leaves hidden ones out', function () {
    [$shop] = availabilityShop();
    availabilityItem($shop, 'Brownie')->update(['availability' => 'unavailable']);
    availabilityItem($shop, 'Soup of the day')->update(['availability' => 'hidden']);

    $this->get("/menu/{$shop->menu_slug}")->assertInertia(fn ($page) => $page
        ->component('Menu')
        ->has('sections', 1) // Specials had only the hidden soup
        ->where('sections.0.items.0.name', 'Brownie')
        ->where('sections.0.items.0.availability', 'unavailable')
        ->has('sections.0.items', 2));
});

test('saving the whole menu keeps each item\'s availability (never taken from the editor)', function () {
    [$shop, $owner] = availabilityShop();
    $brownie = availabilityItem($shop, 'Brownie');
    $brownie->update(['availability' => 'unavailable']);

    // A stale editor still thinks it's available, and renames the cake.
    $this->actingAs($owner)->put('/dashboard/menu', ['sections' => [
        ['name' => 'Cakes', 'items' => [
            ['id' => $brownie->id, 'name' => 'Chocolate brownie', 'price' => '£3', 'availability' => 'available'],
            ['name' => 'Carrot cake', 'price' => '£4'],
            ['name' => 'Lemon tart', 'price' => '£4'],
        ]],
    ]])->assertSessionHasNoErrors();

    expect(availabilityItem($shop, 'Chocolate brownie')->availability)->toBe('unavailable')
        ->and(availabilityItem($shop, 'Lemon tart')->availability)->toBe('available');
});

test('signed-in staff see the menu and change availability for their own shop', function () {
    [$shop] = availabilityShop();
    [$otherShop] = availabilityShop('other-token');
    $auth = ['Authorization' => 'Bearer menu-token'];
    $brownie = availabilityItem($shop, 'Brownie');

    $this->getJson('/api/staff/me', $auth)->assertJsonPath('menu_enabled', true);
    $this->getJson('/api/staff/menu', $auth)
        ->assertOk()
        ->assertJsonPath('sections.0.items.0', ['id' => $brownie->id, 'name' => 'Brownie', 'price' => '£3', 'availability' => 'available']);

    $this->putJson('/api/staff/menu/availability', ['item' => $brownie->id, 'availability' => 'unavailable'], $auth)->assertOk();
    expect($brownie->fresh()->availability)->toBe('unavailable')
        ->and(ActivityLog::where('action', 'menu.availability')->sole()->actor_type)->toBe('staff');

    $otherItem = availabilityItem($otherShop, 'Brownie');
    $this->putJson('/api/staff/menu/availability', ['item' => $otherItem->id, 'availability' => 'hidden'], $auth)->assertNotFound();
    expect($otherItem->fresh()->availability)->toBe('available');
});

test('with Menu switched off for the shop, staff get no Menu tab', function () {
    [$shop] = availabilityShop();
    $shop->forceFill(['features' => ['menu' => false]])->save();
    $auth = ['Authorization' => 'Bearer menu-token'];

    $this->getJson('/api/staff/me', $auth)->assertJsonPath('menu_enabled', false);
    $this->getJson('/api/staff/menu', $auth)->assertNotFound();
});
