<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use App\Support\WhatsApp;

test('numbers are turned into wa.me digits', function (?string $code, ?string $number, ?string $expected) {
    expect(WhatsApp::number($code, $number))->toBe($expected);
})->with([
    'code + number' => ['44', '7700900123', '447700900123'],
    'code + number with its 0' => ['44', '07700 900123', '447700900123'],
    'india' => ['91', '98765 43210', '919876543210'],
    'old shop: whole number with +' => [null, '+44 7700 900123', '447700900123'],
    'old shop: 00 prefix' => [null, '0091 9876543210', '919876543210'],
    'old shop: UK number' => [null, '07700 900123', '447700900123'],
    'no number' => ['44', null, null],
    'too short' => ['44', '123', null],
]);

test('the admin shops grid, users page and shop settings carry the WhatsApp number', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'contact_phone_code' => '44', 'contact_phone' => '7700900123']);
    $draftOwner = User::factory()->create(['role' => UserRole::Owner, 'onboarding_draft' => [
        'name' => 'Chai Corner', 'contact_name' => 'Asha', 'contact_phone_code' => '91', 'contact_phone' => '9876543210',
    ]]);

    $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page
        ->component('Admin/Index')
        ->where('shops.0.contact_whatsapp', '447700900123'));

    $this->get('/admin/users')->assertInertia(fn ($page) => $page
        ->component('Admin/Users')
        ->where('users', fn ($users) => collect($users)->firstWhere('id', $owner->id)['shop']['whatsapp'] === '447700900123'
            && collect($users)->firstWhere('id', $draftOwner->id)['draft']['whatsapp'] === '919876543210'));

    $this->get("/admin/shops/{$shop->id}/settings")->assertInertia(fn ($page) => $page
        ->component('Admin/ShopSettings')
        ->where('shop.whatsapp', '447700900123'));
});
