<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\MenuItem;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\User;
use App\Services\ShopMenu;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => config([
    'services.gemini.key' => 'gm_secret_key',
    'services.gemini.model' => 'gemini-test-flash',
    'services.gemini.fallback_model' => 'gemini-test-fallback',
]));

/** A shop with an owner, a signed-in staff device and a menu with descriptions and prices. */
function aiShop(string $token = 'ai-token'): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $shop->forceFill(['features' => ['menu' => true]])->save();
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', $token)]);
    app(ShopMenu::class)->replace($shop, [
        ['name' => 'Mains', 'items' => [
            ['name' => 'Chicken Biryani', 'description' => 'Secret spice mix', 'price' => '£9'],
            ['name' => 'Chicken 65', 'price' => '£7'],
            ['name' => 'Beef Roast', 'price' => '£8'],
        ]],
    ]);

    return [$shop, $owner];
}

function aiIds(Shop $shop, string ...$names): array
{
    return $shop->menuItems()->whereIn('menu_items.name', $names)->pluck('menu_items.id')->sort()->values()->all();
}

/** Gemini answers with this JSON. */
function geminiSays(array $answer, int $status = 200): void
{
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(
        $status === 200 ? ['candidates' => [['content' => ['parts' => [['text' => json_encode($answer)]]]]]] : ['error' => ['message' => 'busy']],
        $status,
    )]);
}

test('a command returns the matched items to confirm and changes nothing', function () {
    [$shop, $owner] = aiShop();
    $chicken = aiIds($shop, 'Chicken Biryani', 'Chicken 65');
    geminiSays(['action' => 'disable', 'matched_item_ids' => $chicken, 'explanation' => 'Disabled 2 chicken items.']);

    $this->actingAs($owner)->postJson('/dashboard/menu/ai-toggle', ['command' => 'Chicken theernnu'])
        ->assertOk()
        ->assertJsonPath('availability', 'unavailable')
        ->assertJsonPath('explanation', 'Disabled 2 chicken items.')
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('items.0.name', 'Chicken Biryani');

    expect($shop->menuItems()->pluck('availability')->unique()->all())->toBe(['available']);
});

test('Gemini only gets ids and names, the command, a system instruction and a JSON schema', function () {
    [$shop, $owner] = aiShop();
    geminiSays(['action' => 'disable', 'matched_item_ids' => [], 'explanation' => 'Nothing matches.']);

    $this->actingAs($owner)->postJson('/dashboard/menu/ai-toggle', ['command' => 'Fish kazhinju'])->assertOk()->assertJsonCount(0, 'items');

    Http::assertSent(function (Request $request) {
        $prompt = $request['contents'][0]['parts'][0]['text'];

        return str_contains($request->url(), 'gemini-test-flash:generateContent')
            && $request->header('x-goog-api-key')[0] === 'gm_secret_key'
            && str_contains($prompt, '"name":"Chicken Biryani"')
            && str_contains($prompt, 'Command: Fish kazhinju')
            && ! str_contains($prompt, 'Secret spice') && ! str_contains($prompt, '£9')
            && str_contains($request['systemInstruction']['parts'][0]['text'], 'Malayalam, or Manglish')
            && $request['generationConfig']['responseMimeType'] === 'application/json'
            && $request['generationConfig']['responseSchema']['required'] === ['action', 'matched_item_ids', 'explanation'];
    });
});

test("ids that aren't this shop's are dropped, and enable means available", function () {
    [$shop, $owner] = aiShop();
    [$other] = aiShop('other-token');
    $beef = aiIds($shop, 'Beef Roast');
    geminiSays(['action' => 'enable', 'matched_item_ids' => [...$beef, ...aiIds($other, 'Beef Roast'), 999999], 'explanation' => 'Beef is back.']);

    $this->actingAs($owner)->postJson('/dashboard/menu/ai-toggle', ['command' => 'Beef vannu'])
        ->assertOk()
        ->assertJsonPath('availability', 'available')
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.id', $beef[0]);
});

test('confirming applies the batch in one logged change; a foreign id fails the whole batch', function () {
    [$shop, $owner] = aiShop();
    [$other] = aiShop('other-token');
    $chicken = aiIds($shop, 'Chicken Biryani', 'Chicken 65');

    $this->actingAs($owner)->putJson('/dashboard/menu/availability', ['items' => $chicken, 'availability' => 'unavailable'])
        ->assertOk()->assertJsonCount(2, 'items');

    expect(MenuItem::whereIn('id', $chicken)->pluck('availability')->unique()->all())->toBe(['unavailable'])
        ->and(ActivityLog::where('action', 'menu.availability')->sole()->description)->toBe('Marked 2 items (Chicken Biryani, Chicken 65) as not available');

    $beef = aiIds($shop, 'Beef Roast');
    $this->putJson('/dashboard/menu/availability', ['items' => [...$beef, ...aiIds($other, 'Beef Roast')], 'availability' => 'unavailable'])->assertNotFound();
    expect(MenuItem::find($beef[0])->availability)->toBe('available');
});

test('staff use it from the app; the admin for any shop', function () {
    [$shop] = aiShop();
    $chicken = aiIds($shop, 'Chicken 65');
    geminiSays(['action' => 'disable', 'matched_item_ids' => $chicken, 'explanation' => 'Done.']);
    $auth = ['Authorization' => 'Bearer ai-token'];

    $this->getJson('/api/staff/me', $auth)->assertJsonPath('menu_ai', true);
    $this->postJson('/api/staff/menu/ai-toggle', ['command' => 'chicken 65 out'], $auth)->assertOk()->assertJsonCount(1, 'items');
    $this->putJson('/api/staff/menu/availability', ['items' => $chicken, 'availability' => 'unavailable'], $auth)->assertOk();
    expect(ActivityLog::where('action', 'menu.availability')->sole()->actor_type)->toBe('staff');

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->postJson("/admin/shops/{$shop->id}/menu/ai-toggle", ['command' => 'chicken 65 out'])
        ->assertOk();
});

test('a busy Gemini tries the fallback model, then says try again', function () {
    [, $owner] = aiShop();
    geminiSays([], 503);

    $this->actingAs($owner)->postJson('/dashboard/menu/ai-toggle', ['command' => 'chicken out'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'The AI is busy right now - try again in a minute, or switch items by hand.');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'gemini-test-fallback'));
});

test('without a Gemini key it is off', function () {
    config(['services.gemini.key' => null]);
    [, $owner] = aiShop();

    $this->actingAs($owner)->postJson('/dashboard/menu/ai-toggle', ['command' => 'chicken out'])->assertNotFound();
    $this->getJson('/api/staff/me', ['Authorization' => 'Bearer ai-token'])->assertJsonPath('menu_ai', false);
});
