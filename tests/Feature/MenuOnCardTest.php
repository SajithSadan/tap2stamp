<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopMenu;

/** A shop with an owner and a one-item menu, Menu switched on for it. */
function menuCardShop(): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $shop->forceFill(['features' => ['menu' => true]])->save();
    app(ShopMenu::class)->replace($shop, [['name' => 'Cakes', 'items' => [['name' => 'Brownie', 'price' => '£3']]]]);

    return [$shop->fresh(), $owner];
}

test('the card page shows View menu by default when the shop has a menu', function () {
    [$shop] = menuCardShop();

    expect($shop->show_menu_on_card)->toBeTrue();
    $this->get("/s/{$shop->slug}")->assertInertia(fn ($page) => $page
        ->component('Card')
        ->where('shop.menu_url', $shop->menuUrl()));
});

test('no View menu without Menu on, without items customers can see, or with the switch off', function () {
    [$shop] = menuCardShop();
    $menuOff = fn () => $this->get("/s/{$shop->slug}")->assertInertia(fn ($page) => $page->where('shop.menu_url', null));

    $shop->forceFill(['features' => ['menu' => false]])->save();
    $menuOff();

    $shop->forceFill(['features' => ['menu' => true]])->save();
    $shop->menuItems()->update(['availability' => 'hidden']);
    $menuOff();

    $shop->menuItems()->update(['availability' => 'available']);
    $shop->forceFill(['show_menu_on_card' => false])->save();
    $menuOff();
});

test('the owner switches it from their menu editor', function () {
    [$shop, $owner] = menuCardShop();

    $this->actingAs($owner)->get('/dashboard/menu')->assertInertia(fn ($page) => $page->where('showOnCard', true));
    $this->put('/dashboard/menu/card-button', ['enabled' => false])->assertSessionHasNoErrors();

    expect($shop->fresh()->show_menu_on_card)->toBeFalse();
});

test('the admin switches it for any shop; an owner only for their own', function () {
    [$shop, $owner] = menuCardShop();
    [$otherShop, $otherOwner] = menuCardShop();

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->put("/admin/shops/{$shop->id}/menu/card-button", ['enabled' => false])
        ->assertSessionHasNoErrors();
    expect($shop->fresh()->show_menu_on_card)->toBeFalse();

    $this->actingAs($otherOwner)->put("/admin/shops/{$shop->id}/menu/card-button", ['enabled' => true])->assertForbidden();
    $this->put('/dashboard/menu/card-button', ['enabled' => false]);
    expect($shop->fresh()->show_menu_on_card)->toBeFalse()
        ->and($otherShop->fresh()->show_menu_on_card)->toBeFalse();
});

test('with Menu off for the shop the owner cannot switch it', function () {
    [$shop, $owner] = menuCardShop();
    $shop->forceFill(['features' => ['menu' => false]])->save();

    $this->actingAs($owner)->put('/dashboard/menu/card-button', ['enabled' => false])->assertNotFound();
    expect($shop->fresh()->show_menu_on_card)->toBeTrue();
});
