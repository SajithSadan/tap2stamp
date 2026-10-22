<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
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

test('admins get the welcome message for the WhatsApp button, with the default video', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page
        ->where('whatsappWelcome', "Thanks for signing up with Tada Tap.\nPlease check this video to get started.\nhttps://www.youtube.com/watch?v=xvprvHHwxyw"));
});

test('owners never get the welcome message', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id, 'industry' => 'cafe']);

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page->where('whatsappWelcome', null));
});

test('the admin can change the welcome message and its video link', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->put('/admin/settings/welcome', [
        'message' => "Welcome aboard!\r\nWatch this first.",
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->get('/admin/settings')->assertInertia(fn ($page) => $page
        ->where('welcome.message', "Welcome aboard!\nWatch this first.")
        ->where('welcome.video_url', 'https://youtu.be/dQw4w9WgXcQ')
        ->where('whatsappWelcome', "Welcome aboard!\nWatch this first.\nhttps://youtu.be/dQw4w9WgXcQ"));

    // No link: just the text.
    $this->actingAs($admin)->put('/admin/settings/welcome', ['message' => 'Hi!', 'video_url' => ''])->assertSessionHasNoErrors();
    $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page->where('whatsappWelcome', 'Hi!'));
});

test('the welcome message is validated and only the admin can change it', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)->put('/admin/settings/welcome', ['message' => '', 'video_url' => 'not a link'])
        ->assertSessionHasErrors(['message', 'video_url']);

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->actingAs($owner)->put('/admin/settings/welcome', ['message' => 'Hijack'])->assertForbidden();
});

test('sending the welcome is logged and the owner keeps the date of the first one', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Ada Admin']);
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'name' => 'Bean There']);

    $this->actingAs($admin)->postJson('/admin/whatsapp-contacts', ['kind' => 'welcome', 'shop_id' => $shop->id])
        ->assertOk()->assertJsonPath('welcome_sent_label', now()->timezone('Europe/London')->format('j M Y'));
    $first = $owner->fresh()->welcome_sent_at;

    $this->travel(3)->days();
    $this->actingAs($admin)->postJson('/admin/whatsapp-contacts', ['kind' => 'welcome', 'shop_id' => $shop->id])->assertOk();

    $logs = ActivityLog::where('action', 'whatsapp.welcome_sent')->get();
    expect($owner->fresh()->welcome_sent_at->equalTo($first))->toBeTrue()
        ->and($logs)->toHaveCount(2)
        ->and($logs->first()->shop_id)->toBe($shop->id)
        ->and($logs->first()->actor_type)->toBe('admin')
        ->and($logs->first()->user_id)->toBe($admin->id)
        ->and($logs->first()->description)->toContain('Bean There');
});

test('opening a chat is logged but is not a welcome', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($admin)->postJson('/admin/whatsapp-contacts', ['kind' => 'chat', 'shop_id' => $shop->id])->assertOk();

    expect(ActivityLog::where('action', 'whatsapp.chat_opened')->where('shop_id', $shop->id)->exists())->toBeTrue()
        ->and($owner->fresh()->welcome_sent_at)->toBeNull();
});

test('an upgrade reminder is logged, and is not a welcome', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($admin)->postJson('/admin/whatsapp-contacts', ['kind' => 'upgrade', 'shop_id' => $shop->id])->assertOk();

    expect(ActivityLog::where('action', 'whatsapp.upgrade_sent')->where('shop_id', $shop->id)->value('description'))
        ->toBe("Sent a WhatsApp upgrade reminder to {$shop->name}")
        ->and($owner->fresh()->welcome_sent_at)->toBeNull();
});

test('an owner who has not set up a shop yet can be welcomed from the Users page', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($admin)->postJson('/admin/whatsapp-contacts', ['kind' => 'welcome', 'user_id' => $owner->id])->assertOk();

    expect($owner->fresh()->welcome_sent_at)->not->toBeNull();
    $this->actingAs($admin)->get('/admin/users')->assertInertia(fn ($page) => $page
        ->where('users', fn ($users) => collect($users)->firstWhere('id', $owner->id)['welcome_sent_label'] !== null));
});

test('the shops grid shows whether each owner was welcomed', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $owner = User::factory()->create(['role' => UserRole::Owner, 'welcome_sent_at' => now()]);
    Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page
        ->where('shops.0.welcome_sent_label', now()->timezone('Europe/London')->format('j M Y')));
});

test('contact reports are validated and admin only', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)->postJson('/admin/whatsapp-contacts', ['kind' => 'spam'])->assertJsonValidationErrors(['kind', 'shop_id']);
    $this->actingAs($admin)->postJson('/admin/whatsapp-contacts', ['kind' => 'welcome', 'shop_id' => 99999])->assertJsonValidationErrors('shop_id');

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner)->postJson('/admin/whatsapp-contacts', ['kind' => 'welcome', 'shop_id' => $shop->id])->assertForbidden();
    expect($owner->fresh()->welcome_sent_at)->toBeNull();
});
