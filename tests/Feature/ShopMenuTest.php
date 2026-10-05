<?php

use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\QrCode;
use App\Models\Shop;
use App\Models\User;
use App\Support\MenuThemes;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    config(['services.gemini.key' => 'gm_secret_key', 'services.gemini.model' => 'gemini-2.5-flash']);
});

function menuAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function sampleMenu(): array
{
    return [
        ['name' => 'Hot drinks', 'items' => [
            ['name' => 'Flat white', 'description' => 'Double shot', 'price' => '£3.40', 'tags' => ['vegetarian']],
            ['name' => 'Tea', 'description' => null, 'price' => null, 'tags' => []],
        ]],
        ['name' => 'Pastries', 'items' => [
            ['name' => 'Croissant', 'description' => null, 'price' => '£2.80', 'tags' => []],
        ]],
    ];
}

function geminiReplies(array $sections): void
{
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode(['sections' => $sections])]]]]],
    ])]);
}

// --- Access ---------------------------------------------------------------

test('only an admin can manage a shop menu', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $this->get("/admin/shops/{$shop->id}/menu")->assertRedirect('/login');
    $this->actingAs($owner)->get("/admin/shops/{$shop->id}/menu")->assertForbidden();
    $this->actingAs($owner)->put("/admin/shops/{$shop->id}/menu", ['sections' => sampleMenu()])->assertForbidden();
    $this->actingAs($owner)->post("/admin/shops/{$shop->id}/menu/read", [])->assertForbidden();

    expect(MenuItem::count())->toBe(0);
});

// --- Editing --------------------------------------------------------------

test('an admin can open the menu editor', function () {
    $shop = Shop::factory()->create();

    $this->actingAs(menuAdmin())->get("/admin/shops/{$shop->id}/menu")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Menu/Edit')
            ->where('shop.id', $shop->id)
            ->where('sections', [])
            ->where('aiEnabled', true)
            ->where('menuUrl', route('menu.show', $shop)));
});

test('saving replaces the whole menu, in order', function () {
    $shop = Shop::factory()->create();
    $admin = menuAdmin();

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/menu", ['sections' => [
        ['name' => 'Old', 'items' => [['name' => 'Gone']]],
    ]])->assertSessionHasNoErrors();

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/menu", ['sections' => sampleMenu()])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(MenuSection::where('shop_id', $shop->id)->orderBy('position')->pluck('name')->all())->toBe(['Hot drinks', 'Pastries'])
        ->and(MenuItem::count())->toBe(3)
        ->and(MenuItem::where('name', 'Gone')->exists())->toBeFalse()
        ->and(MenuItem::where('name', 'Flat white')->first()->tags)->toBe(['vegetarian'])
        ->and(MenuItem::where('name', 'Tea')->first()->tags)->toBeNull();
});

test('a menu needs names, and tags stay short and few', function () {
    $shop = Shop::factory()->create();

    $this->actingAs(menuAdmin())->put("/admin/shops/{$shop->id}/menu", ['sections' => [
        ['name' => '', 'items' => [
            ['name' => '', 'tags' => [str_repeat('x', 25)]],
            ['name' => 'Tea', 'tags' => ['a', 'b', 'c', 'd', 'e', 'f', 'g']],
        ]],
    ]])->assertSessionHasErrors(['sections.0.name', 'sections.0.items.0.name', 'sections.0.items.0.tags.0', 'sections.0.items.1.tags']);

    expect(MenuSection::count())->toBe(0);
});

test('tags are free text, whatever the shop uses, tidied on save', function () {
    $shop = Shop::factory()->create();

    $this->actingAs(menuAdmin())->put("/admin/shops/{$shop->id}/menu", ['sections' => [
        ['name' => 'Mains', 'items' => [['name' => 'Lamb curry', 'tags' => ['Halal', '  Chef’s   special ', 'halal', '']]]],
    ]])->assertSessionHasNoErrors();

    expect(MenuItem::sole()->tags)->toBe(['Halal', 'Chef’s special']);
});

test('clearing deletes the menu of that shop only', function () {
    $shop = Shop::factory()->create();
    $other = Shop::factory()->create();
    $admin = menuAdmin();
    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/menu", ['sections' => sampleMenu()]);
    $this->actingAs($admin)->put("/admin/shops/{$other->id}/menu", ['sections' => sampleMenu()]);

    $this->actingAs($admin)->delete("/admin/shops/{$shop->id}/menu")->assertRedirect();

    expect($shop->menuItems()->count())->toBe(0)
        ->and($other->menuItems()->count())->toBe(3);
});

// --- Reading with Gemini --------------------------------------------------

test('Gemini reads an uploaded menu into the editor shape without saving it', function () {
    $shop = Shop::factory()->create();
    geminiReplies([
        ['name' => 'Coffee', 'items' => [
            ['name' => 'Latte', 'description' => '', 'price' => '£3.50', 'tags' => ['Vegan', ' vegan ', 'New', 42]],
            ['name' => '   ', 'price' => '£1'],
        ]],
        ['name' => 'Empty', 'items' => []],
    ]);

    $this->actingAs(menuAdmin())
        ->post("/admin/shops/{$shop->id}/menu/read", ['files' => [UploadedFile::fake()->image('menu.jpg')]])
        ->assertOk()
        ->assertExactJson(['sections' => [
            ['name' => 'Coffee', 'items' => [
                ['name' => 'Latte', 'description' => null, 'price' => '£3.50', 'tags' => ['Vegan', 'New']],
            ]],
        ]])
        ->assertDontSee('gm_secret_key');

    Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', 'gm_secret_key')
        && str_contains($request->url(), 'models/gemini-2.5-flash:generateContent')
        && $request['contents'][0]['parts'][0]['inline_data']['mime_type'] === 'image/jpeg'
        && $request['generationConfig']['responseMimeType'] === 'application/json');

    expect(MenuItem::count())->toBe(0);
});

test('a picture with no menu in it is a friendly 422', function () {
    $shop = Shop::factory()->create();
    geminiReplies([]);

    $this->actingAs(menuAdmin())
        ->post("/admin/shops/{$shop->id}/menu/read", ['files' => [UploadedFile::fake()->image('cat.jpg')]])
        ->assertStatus(422)
        ->assertJsonStructure(['message']);
});

test('a Gemini outage is a 503, never the raw error', function () {
    $shop = Shop::factory()->create();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 403)]);

    $this->actingAs(menuAdmin())
        ->post("/admin/shops/{$shop->id}/menu/read", ['files' => [UploadedFile::fake()->image('menu.jpg')]])
        ->assertStatus(503)
        ->assertDontSee('API key not valid');
});

test('a busy Gemini model is retried, then the fallback model reads the menu', function () {
    Sleep::fake();
    config(['services.gemini.model' => 'gemini-busy', 'services.gemini.fallback_model' => 'gemini-spare']);
    $shop = Shop::factory()->create();
    Http::fake([
        '*/models/gemini-busy:*' => Http::response(['error' => ['message' => 'This model is currently experiencing high demand.']], 503),
        '*/models/gemini-spare:*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['sections' => [['name' => 'Tea', 'items' => [['name' => 'Earl Grey']]]]])]]]]],
        ]),
    ]);

    $this->actingAs(menuAdmin())
        ->post("/admin/shops/{$shop->id}/menu/read", ['files' => [UploadedFile::fake()->image('menu.jpg')]])
        ->assertOk()
        ->assertJsonPath('sections.0.items.0.name', 'Earl Grey');

    Http::assertSentCount(3); // busy, busy again, spare
});

test('when every model is busy the admin is told to try again shortly', function () {
    Sleep::fake();
    $shop = Shop::factory()->create();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'high demand']], 503)]);

    $this->actingAs(menuAdmin())
        ->post("/admin/shops/{$shop->id}/menu/read", ['files' => [UploadedFile::fake()->image('menu.jpg')]])
        ->assertStatus(503)
        ->assertJsonPath('message', 'Gemini is very busy right now. Try again in a minute.');
});

test('only menu photos and PDFs are sent to Gemini', function () {
    $shop = Shop::factory()->create();
    Http::fake();

    $this->actingAs(menuAdmin())
        ->postJson("/admin/shops/{$shop->id}/menu/read", ['files' => [UploadedFile::fake()->create('menu.svg', 10, 'image/svg+xml')]])
        ->assertJsonValidationErrors('files.0');

    Http::assertNothingSent();
});

test('without a Gemini key the reader is off', function () {
    config(['services.gemini.key' => null]);
    $shop = Shop::factory()->create();

    $this->actingAs(menuAdmin())->get("/admin/shops/{$shop->id}/menu")
        ->assertInertia(fn ($page) => $page->where('aiEnabled', false));
    $this->actingAs(menuAdmin())
        ->post("/admin/shops/{$shop->id}/menu/read", ['files' => [UploadedFile::fake()->image('menu.jpg')]])
        ->assertNotFound();
});

test('the shop settings page shows how many menu items the shop has', function () {
    $shop = Shop::factory()->create();
    $admin = menuAdmin();
    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/menu", ['sections' => sampleMenu()]);

    $this->actingAs($admin)->get("/admin/shops/{$shop->id}/settings")
        ->assertInertia(fn ($page) => $page->component('Admin/ShopSettings')
            ->where('menuItemsCount', 3)
            ->where('menuUrl', route('menu.show', $shop)));
});

// --- Public page & QR stickers --------------------------------------------

test('anyone can open a shop menu by its id', function () {
    $shop = Shop::factory()->create();
    $this->actingAs(menuAdmin())->put("/admin/shops/{$shop->id}/menu", ['sections' => sampleMenu()]);
    auth()->logout();

    $this->get("/menu/{$shop->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Menu')
            ->where('shop.name', $shop->name)
            ->missing('shop.slug')
            ->has('sections', 2)
            ->where('sections.0.items.0.name', 'Flat white')
            ->has('theme'));

    $this->get('/menu/999999')->assertNotFound();
});

test('the admin picks a menu theme from the catalog, and the menu page wears it', function () {
    $shop = Shop::factory()->create();
    $admin = menuAdmin();

    $this->actingAs($admin)->get("/admin/shops/{$shop->id}/menu")
        ->assertInertia(fn ($page) => $page->where('currentTheme', MenuThemes::DEFAULT)
            ->has('themes', count(MenuThemes::all())));

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/menu/theme", ['theme' => 'bistro'])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($shop->fresh()->menu_theme)->toBe('bistro');

    $this->get("/menu/{$shop->id}")
        ->assertInertia(fn ($page) => $page->where('theme.key', 'bistro')->where('theme.layout', 'classic'));
});

test('only catalog menu themes can be chosen, and only by an admin', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs(menuAdmin())->put("/admin/shops/{$shop->id}/menu/theme", ['theme' => 'neon-nonsense'])
        ->assertSessionHasErrors('theme');
    $this->actingAs($owner)->put("/admin/shops/{$shop->id}/menu/theme", ['theme' => 'bistro'])
        ->assertForbidden();

    expect($shop->fresh()->menu_theme)->toBeNull();
});

test('every menu theme has the colours, fonts and a layout the page needs', function () {
    foreach (MenuThemes::all() as $theme) {
        expect($theme)->toHaveKeys(['name', 'layout', 'google_fonts', 'heading_font', 'body_font', 'page_bg', 'card_bg', 'deep', 'text', 'muted', 'border', 'accent', 'accent_text', 'radius'])
            ->and(MenuThemes::LAYOUTS)->toContain($theme['layout']);
    }
});

test('a QR sticker mapped to a shop menu is assigned to that shop', function () {
    $shop = Shop::factory()->create();
    $qr = QrCode::factory()->create();

    $this->actingAs(menuAdmin())
        ->put("/admin/qr-codes/{$qr->id}", ['destination_url' => "http://localhost/menu/{$shop->id}"])
        ->assertSessionHasNoErrors();

    expect($qr->fresh()->shop_id)->toBe($shop->id);
});
