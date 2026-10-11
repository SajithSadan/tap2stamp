<?php

use App\Enums\UserRole;
use App\Models\AiUsageLog;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\User;
use App\Services\MenuImageVerifier;
use App\Services\ShopMenu;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => config([
    'services.gemini.key' => 'gm_secret_key',
    'services.gemini.model' => 'gemini-test-flash',
    'services.gemini.fallback_model' => null,
]));

/** Gemini answers $answer (JSON text) and reports these tokens. */
function geminiUsage(array $answer, int $in = 1200, int $out = 80, int $thoughts = 20, int $status = 200): void
{
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response($status === 200 ? [
        'candidates' => [['content' => ['parts' => [['text' => json_encode($answer)]]]]],
        'usageMetadata' => ['promptTokenCount' => $in, 'candidatesTokenCount' => $out, 'thoughtsTokenCount' => $thoughts, 'totalTokenCount' => $in + $out + $thoughts],
    ] : ['error' => ['message' => 'busy']], $status)]);
}

function usageShop(): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner, 'email' => 'olive@bean.test']);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'name' => 'Bean There']);
    $shop->forceFill(['features' => ['menu' => true]])->save();
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'usage-token')]);
    app(ShopMenu::class)->replace($shop, [['name' => 'Mains', 'items' => [['name' => 'Chicken 65']]]]);

    return [$shop, $owner];
}

test("an owner's stock-out command is recorded with who, which shop and the tokens", function () {
    [$shop, $owner] = usageShop();
    geminiUsage(['action' => 'disable', 'matched_item_ids' => [], 'explanation' => 'None.']);

    $this->actingAs($owner)->postJson('/dashboard/menu/ai-toggle', ['command' => 'chicken out'])->assertOk();

    expect(AiUsageLog::sole())
        ->feature->toBe('stock_command')
        ->shop_id->toBe($shop->id)
        ->actor_type->toBe('owner')
        ->user_id->toBe($owner->id)
        ->actor_name->toBe('olive@bean.test')
        ->model->toBe('gemini-test-flash')
        ->status->toBe('ok')
        ->input_tokens->toBe(1200)
        ->output_tokens->toBe(100) // answer + thinking
        ->total_tokens->toBe(1300);
});

test('a staff member is recorded by name, and a busy model is recorded too', function () {
    [$shop] = usageShop();
    geminiUsage([], status: 503);

    $this->postJson('/api/staff/menu/ai-toggle', ['command' => 'chicken out'], ['Authorization' => 'Bearer usage-token'])->assertStatus(503);

    $log = AiUsageLog::sole();
    expect($log->actor_type)->toBe('staff')
        ->and($log->staff_member_id)->not->toBeNull()
        ->and($log->shop_id)->toBe($shop->id)
        ->and($log->status)->toBe('busy')
        ->and($log->total_tokens)->toBe(0);
});

test("the admin's menu photo import is recorded against the shop they're editing", function () {
    [$shop] = usageShop();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    geminiUsage(['sections' => [['name' => 'Coffee', 'items' => [['name' => 'Latte']]]]], in: 3000, out: 400, thoughts: 0);

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/menu/read", ['files' => [UploadedFile::fake()->image('menu.jpg')]])->assertOk();

    expect(AiUsageLog::sole())
        ->feature->toBe('menu_import')
        ->shop_id->toBe($shop->id)
        ->actor_type->toBe('admin')
        ->total_tokens->toBe(3400);
});

test('a menu photo check is recorded for the signed-in owner\'s shop', function () {
    [$shop, $owner] = usageShop();
    geminiUsage(['match' => true, 'confidence' => 0.9, 'detected' => 'chicken', 'reason' => 'ok'], in: 500, out: 30, thoughts: 0);

    $this->actingAs($owner);
    app(MenuImageVerifier::class)->verify('Chicken 65', 'Mains', null, 'image-bytes', 'image/jpeg');

    expect(AiUsageLog::sole())->feature->toBe('photo_check')->shop_id->toBe($shop->id)->total_tokens->toBe(530);
});

test('the admin AI usage page totals by feature, shop and person', function () {
    [$shop, $owner] = usageShop();
    geminiUsage(['action' => 'disable', 'matched_item_ids' => [], 'explanation' => 'None.']);
    $this->actingAs($owner)->postJson('/dashboard/menu/ai-toggle', ['command' => 'one']);
    $this->postJson('/dashboard/menu/ai-toggle', ['command' => 'two']);
    $this->postJson('/api/staff/menu/ai-toggle', ['command' => 'three'], ['Authorization' => 'Bearer usage-token']);
    AiUsageLog::create(['feature' => 'stock_command', 'shop_id' => $shop->id, 'status' => 'ok', 'total_tokens' => 999, 'created_at' => now()->subDays(40)]);

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get('/admin/ai-usage')
        ->assertInertia(fn ($page) => $page
            ->component('Admin/AiUsage')
            ->where('days', 30)
            ->where('totals.requests', 3) // the 40-day-old one is outside 30 days
            ->where('totals.tokens', 3900)
            ->where('totals.shops', 1)
            ->where('byShop.0.shop_name', 'Bean There')
            ->where('byShop.0.stock_command', 3)
            ->where('byPerson.0.actor_type', 'owner')
            ->where('byPerson.0.requests', 2)
            ->where('byPerson.1.actor_type', 'staff')
            ->has('recent', 3));

    $this->get('/admin/ai-usage?days=0')->assertInertia(fn ($page) => $page->where('totals.requests', 4));
    $this->get('/admin/ai-usage?days=13')->assertInertia(fn ($page) => $page->where('days', 30));
});

test('plan limit meters: this minute, today on Google\'s Pacific clock, and the peaks', function () {
    config(['services.gemini.limits.rpm' => 1000, 'services.gemini.limits.tpm' => 2000000, 'services.gemini.limits.rpd' => 10000]);
    $this->travelTo(Carbon::parse('2026-10-11 10:00', 'America/Los_Angeles'));
    $log = fn ($at, $tokens = 100) => AiUsageLog::create(['feature' => 'stock_command', 'model' => 'gemini-test-flash', 'status' => 'ok', 'input_tokens' => $tokens, 'total_tokens' => $tokens, 'created_at' => $at]);

    foreach ([10, 20, 30] as $s) {
        $log(now()->subSeconds($s), 500);  // this minute: 3 requests, 1,500 input tokens
    }
    $log(now()->subHours(9));              // earlier today (Pacific) - counts for today
    $log(now()->subHours(11));             // yesterday in Pacific time - doesn't
    foreach (range(1, 5) as $i) {
        $log(now()->subDays(6)->setTime(12, 0, $i)); // a busier day: 5 in one minute
    }

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get('/admin/ai-usage')
        ->assertInertia(fn ($page) => $page
            ->where('limits.tier', 'Tier 1')
            ->where('limits.rpd', 10000)
            ->where('limits.models', fn ($models) => collect($models)->firstWhere('model', 'gemini-test-flash') === [
                'model' => 'gemini-test-flash',
                'rpm' => 3,
                'tpm' => 1500,
                'rpd' => 4,
                'peak_rpm' => 5,
                'peak_tpm' => 1500,
                'peak_rpd' => 5,
            ]));
});
