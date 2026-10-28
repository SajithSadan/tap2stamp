<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\RewardGame;
use App\Models\Shop;
use App\Models\User;
use App\Support\RewardGames;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** An owner whose shop has Rewards & games switched on. */
function gameOwner(): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $shop->forceFill(['features' => ['games' => true]])->save();

    return [$owner->fresh(), $shop];
}

function gameForm(array $overrides = []): array
{
    return [
        'name' => '  Weekend wheel  ',
        'type' => 'wheel',
        'trigger' => 'every_n_stamps',
        'trigger_value' => 3,
        'starts_on' => '',
        'ends_on' => '',
        'max_plays_per_customer' => '',
        'voucher_days' => 30,
        'prizes' => [
            ['id' => null, 'title' => 'Free coffee', 'detail' => 'Any size', 'kind' => 'item', 'bonus_stamps' => '', 'weight' => 10, 'stock' => 20, 'daily_limit' => ''],
            ['id' => null, 'title' => '+1 stamp', 'detail' => '', 'kind' => 'bonus_stamps', 'bonus_stamps' => 1, 'weight' => 30, 'stock' => '', 'daily_limit' => ''],
            ['id' => null, 'title' => 'Try again', 'detail' => '', 'kind' => 'no_win', 'bonus_stamps' => '', 'weight' => 60, 'stock' => '', 'daily_limit' => ''],
        ],
        ...$overrides,
    ];
}

test('the games page lists the shop\'s games with the builder options', function () {
    [$owner] = gameOwner();

    $this->actingAs($owner)->get('/dashboard/games')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Dashboard/Games')
        ->has('games', 0)
        ->has('options.types', 6)
    );
    $this->actingAs($owner)->get('/dashboard/games/create')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Dashboard/GameEditor')
        ->where('game', null)
    );
});

test('an owner creates a game as a draft, with its prizes in order', function () {
    [$owner, $shop] = gameOwner();

    $this->actingAs($owner)->post('/dashboard/games', gameForm())->assertSessionHasNoErrors();

    $game = $shop->rewardGames()->sole();
    expect($game->only(['name', 'type', 'status', 'trigger', 'trigger_value', 'voucher_days']))->toBe([
        'name' => 'Weekend wheel', 'type' => 'wheel', 'status' => 'draft', 'trigger' => 'every_n_stamps', 'trigger_value' => 3, 'voucher_days' => 30,
    ])
        ->and($game->prizes->pluck('title')->all())->toBe(['Free coffee', '+1 stamp', 'Try again'])
        ->and($game->prizes[0]->only(['detail', 'stock', 'bonus_stamps']))->toBe(['detail' => 'Any size', 'stock' => 20, 'bonus_stamps' => null])
        ->and($game->prizes[1]->bonus_stamps)->toBe(1)
        ->and(ActivityLog::where('action', 'reward_game.created')->exists())->toBeTrue();

    $this->get('/dashboard/games')->assertInertia(fn ($page) => $page->has('games', 1)->where('games.0.trigger_label', 'Every 3rd stamp'));
});

test('editing keeps prizes that come back, drops the rest and adds new ones', function () {
    [$owner, $shop] = gameOwner();
    $this->actingAs($owner)->post('/dashboard/games', gameForm());
    $game = $shop->rewardGames()->sole();
    [$coffee, , $tryAgain] = $game->prizes;
    $coffee->forceFill(['won_count' => 7])->save();

    $form = gameForm(['name' => 'Spring wheel', 'trigger' => 'card_full', 'prizes' => [
        ['id' => $coffee->id, 'title' => 'Free latte', 'kind' => 'item', 'weight' => 5],
        ['id' => null, 'title' => '10% off', 'kind' => 'discount', 'weight' => 15],
        ['id' => $tryAgain->id, 'title' => 'Not this time', 'kind' => 'no_win', 'weight' => 80],
    ]]);
    $this->put("/dashboard/games/{$game->id}", $form)->assertSessionHasNoErrors();

    $game->refresh()->load('prizes');
    expect($game->name)->toBe('Spring wheel')
        ->and($game->trigger_value)->toBeNull()
        ->and($game->prizes->pluck('title')->all())->toBe(['Free latte', '10% off', 'Not this time'])
        ->and($game->prizes[0]->id)->toBe($coffee->id)
        ->and($game->prizes[0]->won_count)->toBe(7);
});

test('a game needs a prize to win, the right number of prizes and the details each kind needs', function (array $overrides, string $error) {
    [$owner, $shop] = gameOwner();

    $this->actingAs($owner)->post('/dashboard/games', gameForm($overrides))->assertSessionHasErrors($error);
    expect($shop->rewardGames()->count())->toBe(0);
})->with([
    'only no-win prizes' => [['prizes' => [['title' => 'No', 'kind' => 'no_win', 'weight' => 1], ['title' => 'Nope', 'kind' => 'no_win', 'weight' => 1]]], 'prizes'],
    'one prize' => [['prizes' => [['title' => 'Coffee', 'kind' => 'item', 'weight' => 1]]], 'prizes'],
    'too many boxes' => [['type' => 'boxes', 'prizes' => array_fill(0, 7, ['title' => 'Coffee', 'kind' => 'item', 'weight' => 1])], 'prizes'],
    'too many ball drop slots' => [['type' => 'plinko', 'prizes' => array_fill(0, 8, ['title' => 'Coffee', 'kind' => 'item', 'weight' => 1])], 'prizes'],
    'bonus without a count' => [['prizes' => [['title' => 'Bonus', 'kind' => 'bonus_stamps', 'weight' => 1], ['title' => 'No', 'kind' => 'no_win', 'weight' => 1]]], 'prizes.0.bonus_stamps'],
    'no stamp count' => [['trigger_value' => ''], 'trigger_value'],
    'unknown game' => [['type' => 'bingo'], 'type'],
    'end before start' => [['starts_on' => '2026-11-10', 'ends_on' => '2026-11-01'], 'ends_on'],
]);

test('every game type can be saved, and has an icon and a preview', function () {
    [$owner, $shop] = gameOwner();

    foreach (array_keys(RewardGames::TYPES) as $type) {
        $this->actingAs($owner)->post('/dashboard/games', gameForm(['type' => $type]))->assertSessionHasNoErrors();
    }
    expect($shop->rewardGames()->pluck('type')->all())->toEqualCanonicalizing(array_keys(RewardGames::TYPES));

    preg_match('/GAME_TYPE_ICONS = \{(.*?)\}/s', file_get_contents(resource_path('js/lib/rewardGames.js')), $icons);
    preg_match('/const GAMES = \{(.*?)\}/s', file_get_contents(resource_path('js/Components/Games/GamePreview.jsx')), $games);
    preg_match_all('/(\w+):/', $icons[1], $iconKeys);
    preg_match_all('/(\w+):/', $games[1], $gameKeys);

    expect($iconKeys[1])->toEqualCanonicalizing(array_keys(RewardGames::TYPES))
        ->and($gameKeys[1])->toEqualCanonicalizing(array_keys(RewardGames::TYPES));
});

test('an owner publishes, pauses and deletes a game', function () {
    [$owner, $shop] = gameOwner();
    $this->actingAs($owner)->post('/dashboard/games', gameForm());
    $game = $shop->rewardGames()->sole();

    $this->put("/dashboard/games/{$game->id}/status", ['status' => 'live'])->assertSessionHas('status');
    expect($game->fresh()->status)->toBe('live');
    $this->put("/dashboard/games/{$game->id}/status", ['status' => 'paused']);
    expect($game->fresh()->status)->toBe('paused');
    $this->put("/dashboard/games/{$game->id}/status", ['status' => 'ended'])->assertSessionHasErrors('status');

    $this->delete("/dashboard/games/{$game->id}")->assertRedirect('/dashboard/games');
    expect(RewardGame::count())->toBe(0)->and(DB::table('reward_game_prizes')->count())->toBe(0);
});

test('an admin viewing as the owner can look but not build without "Allow changes"', function () {
    [, $shop] = gameOwner();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->get('/dashboard/games')->assertOk();
    $this->post('/dashboard/games', gameForm())->assertSessionHasErrors('view_as');

    expect($shop->rewardGames()->count())->toBe(0);
});

test('a scratch card takes its own image, which can be replaced or removed, and goes with the game', function () {
    Storage::fake('uploads');
    [$owner, $shop] = gameOwner();

    $this->actingAs($owner)->post('/dashboard/games', gameForm([
        'type' => 'scratch', 'image' => UploadedFile::fake()->image('my-card.jpg', 900, 600),
    ]))->assertSessionHasNoErrors();
    $game = $shop->rewardGames()->sole();
    $first = $game->image_path;
    expect($first)->toStartWith("reward-games/{$shop->id}/")->not->toContain('my-card');
    Storage::disk('uploads')->assertExists($first);
    $this->get("/dashboard/games/{$game->id}/edit")->assertInertia(fn ($page) => $page->where('game.image_url', Storage::disk('uploads')->url($first)));

    // Replaced (sent as a POST with _method, like the builder's FormData).
    $this->post("/dashboard/games/{$game->id}", [...gameForm(['type' => 'scratch']), '_method' => 'put', 'image' => UploadedFile::fake()->image('two.png', 900, 600)])->assertSessionHasNoErrors();
    $second = $game->fresh()->image_path;
    Storage::disk('uploads')->assertMissing($first);
    Storage::disk('uploads')->assertExists($second);

    // Saving without an image keeps it; "remove" deletes it.
    $this->put("/dashboard/games/{$game->id}", gameForm(['type' => 'scratch']))->assertSessionHasNoErrors();
    expect($game->fresh()->image_path)->toBe($second);
    $this->put("/dashboard/games/{$game->id}", gameForm(['type' => 'scratch', 'remove_image' => true]));
    expect($game->fresh()->image_path)->toBeNull();
    Storage::disk('uploads')->assertMissing($second);

    // Deleting the game deletes its image.
    $this->post("/dashboard/games/{$game->id}", [...gameForm(['type' => 'scratch']), '_method' => 'put', 'image' => UploadedFile::fake()->image('three.jpg', 900, 600)]);
    $third = $game->fresh()->image_path;
    $this->delete("/dashboard/games/{$game->id}");
    Storage::disk('uploads')->assertMissing($third);
});

test('a scratch card image must be a photo, big enough and not too large', function (UploadedFile $file) {
    Storage::fake('uploads');
    [$owner, $shop] = gameOwner();

    $this->actingAs($owner)->post('/dashboard/games', gameForm(['type' => 'scratch', 'image' => $file]))->assertSessionHasErrors('image');
    expect($shop->rewardGames()->count())->toBe(0);
})->with([
    'svg' => fn () => UploadedFile::fake()->create('card.svg', 10, 'image/svg+xml'),
    'too small' => fn () => UploadedFile::fake()->image('tiny.jpg', 100, 100),
    'too big' => fn () => UploadedFile::fake()->image('huge.jpg', 900, 600)->size(5000),
]);

test('a game has an optional headline for customers', function () {
    [$owner, $shop] = gameOwner();

    $this->actingAs($owner)->post('/dashboard/games', gameForm(['headline' => '  Spin for a treat!  ']))->assertSessionHasNoErrors();
    $game = $shop->rewardGames()->sole();
    expect($game->headline)->toBe('Spin for a treat!');

    $this->put("/dashboard/games/{$game->id}", gameForm(['headline' => '']))->assertSessionHasNoErrors();
    expect($game->fresh()->headline)->toBeNull();
    $this->put("/dashboard/games/{$game->id}", gameForm(['headline' => str_repeat('x', 61)]))->assertSessionHasErrors('headline');
});

test('an owner limits plays per customer per day and ends the game for the day after a win', function () {
    [$owner, $shop] = gameOwner();

    $this->actingAs($owner)->post('/dashboard/games', gameForm([
        'max_plays_per_customer' => 3, 'plays_period' => 'day', 'after_win' => 'day',
    ]))->assertSessionHasNoErrors();
    $game = $shop->rewardGames()->sole();
    expect($game->only(['max_plays_per_customer', 'plays_period', 'after_win']))
        ->toBe(['max_plays_per_customer' => 3, 'plays_period' => 'day', 'after_win' => 'day']);

    // No limit = no period; nonsense is refused.
    $this->put("/dashboard/games/{$game->id}", gameForm(['max_plays_per_customer' => '', 'plays_period' => 'week']))->assertSessionHasNoErrors();
    expect($game->fresh()->plays_period)->toBeNull()->and($game->fresh()->after_win)->toBe('continue');
    $this->put("/dashboard/games/{$game->id}", gameForm(['after_win' => 'forever']))->assertSessionHasErrors('after_win');
    $this->put("/dashboard/games/{$game->id}", gameForm(['max_plays_per_customer' => 2, 'plays_period' => 'hour']))->assertSessionHasErrors('plays_period');
});

test('canPlay follows the play limit and the after-win rule, in UK days', function () {
    $game = new RewardGame(['max_plays_per_customer' => 3, 'plays_period' => 'day', 'after_win' => 'continue']);
    $now = Carbon::parse('2026-11-04 15:00', 'Europe/London');

    expect($game->canPlay(2, null, $now))->toBeTrue()
        ->and($game->canPlay(3, null, $now))->toBeFalse()
        ->and($game->playsWindowStart($now)->format('Y-m-d H:i'))->toBe('2026-11-04 00:00');

    // Done for the day: a win this morning blocks today, not tomorrow.
    $game->after_win = 'day';
    expect($game->canPlay(1, $now->copy()->setTime(9, 0), $now))->toBeFalse()
        ->and($game->canPlay(0, $now->copy()->subDay(), $now))->toBeTrue();

    // Done with this game: any earlier win blocks for good.
    $game->after_win = 'game';
    expect($game->canPlay(0, $now->copy()->subMonth(), $now))->toBeFalse()
        ->and($game->canPlay(0, null, $now))->toBeTrue();

    // No limit, "in total" = all time.
    $open = new RewardGame(['max_plays_per_customer' => null, 'plays_period' => 'total', 'after_win' => 'continue']);
    expect($open->canPlay(500, $now, $now))->toBeTrue()->and($open->playsWindowStart($now))->toBeNull();
});
