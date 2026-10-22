<?php

use App\Enums\ActionType;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\AiUsageLog;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\ShopNote;
use App\Models\ShopTask;
use App\Models\StampLog;
use App\Models\User;
use App\Services\CrmAssistant;
use App\Services\ShopTimeline;
use Illuminate\Support\Facades\Http;

function crmAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Ada']);
}

/** Gemini answers $answer (as its JSON text). */
function fakeGeminiAnswer(array $answer): void
{
    config(['services.gemini.key' => 'test-key']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode($answer)]]]]],
        'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 50, 'totalTokenCount' => 150],
    ])]);
}

// --- Notes, tags, follow-ups ------------------------------------------------------

test('the admin adds and deletes notes on a shop', function () {
    $admin = crmAdmin();
    $shop = Shop::factory()->create();

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/notes", ['body' => '  Called Sam, staff forget to scan. '])->assertSessionHasNoErrors();
    $note = $shop->notes()->first();
    expect($note->body)->toBe('Called Sam, staff forget to scan.')->and($note->user_id)->toBe($admin->id);

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/notes", ['body' => ''])->assertSessionHasErrors('body');

    $this->actingAs($admin)->delete("/admin/shops/{$shop->id}/notes/{$note->id}")->assertRedirect();
    expect(ShopNote::count())->toBe(0);
});

test('a note or follow-up is only reached through its own shop', function () {
    $admin = crmAdmin();
    $shop = Shop::factory()->create();
    $other = Shop::factory()->create();
    $note = $other->notes()->create(['body' => 'Theirs']);
    $task = $other->tasks()->create(['title' => 'Theirs']);

    $this->actingAs($admin)->delete("/admin/shops/{$shop->id}/notes/{$note->id}")->assertNotFound();
    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/tasks/{$task->id}", ['done' => true])->assertNotFound();
    $this->actingAs($admin)->delete("/admin/shops/{$shop->id}/tasks/{$task->id}")->assertNotFound();

    expect($note->fresh())->not->toBeNull()->and($task->fresh()->done_at)->toBeNull();
});

test('tags are tidied: trimmed, no duplicates, at most 8', function () {
    $admin = crmAdmin();
    $shop = Shop::factory()->create();

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/tags", ['tags' => [' Hot  lead ', 'hot lead', 'Price objection', '']])->assertSessionHasNoErrors();
    expect($shop->fresh()->crm_tags)->toBe(['Hot lead', 'Price objection']);

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/tags", ['tags' => []]);
    expect($shop->fresh()->crm_tags)->toBeNull();

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/tags", ['tags' => array_map(fn ($i) => "t{$i}", range(1, 9))])->assertSessionHasErrors('tags');
});

test('follow-ups are added, ticked off, reopened and deleted', function () {
    $admin = crmAdmin();
    $shop = Shop::factory()->create();

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/tasks", ['title' => 'Call about the display', 'due_on' => now()->addDay()->toDateString()])->assertSessionHasNoErrors();
    $task = $shop->tasks()->first();
    expect($task->created_by)->toBe($admin->id)->and($task->toRow()['urgency'])->toBe('soon');

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/tasks/{$task->id}", ['done' => true]);
    expect($task->fresh()->done_at)->not->toBeNull()->and($task->fresh()->done_by)->toBe($admin->id);

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/tasks/{$task->id}", ['done' => false]);
    expect($task->fresh()->done_at)->toBeNull();

    $this->actingAs($admin)->delete("/admin/shops/{$shop->id}/tasks/{$task->id}");
    expect(ShopTask::count())->toBe(0);
});

test('the shop account tab carries notes, follow-ups, tags and the timeline', function () {
    $admin = crmAdmin();
    $shop = Shop::factory()->create(['crm_tags' => ['Hot lead']]);
    $shop->notes()->create(['body' => 'Keen on the display', 'user_id' => $admin->id]);
    $shop->tasks()->create(['title' => 'Send QR']);

    $this->actingAs($admin)->get("/admin/shops/{$shop->id}/settings")->assertInertia(fn ($page) => $page
        ->where('crm.tags', ['Hot lead'])
        ->where('crm.notes.0.body', 'Keen on the display')
        ->where('crm.notes.0.author', 'Ada')
        ->where('crm.tasks.0.title', 'Send QR')
        ->has('crm.timeline'));
});

test('the shops grid shows tags and open follow-ups', function () {
    $admin = crmAdmin();
    $shop = Shop::factory()->create(['crm_tags' => ['Price objection']]);
    $shop->tasks()->create(['title' => 'Call']);
    $shop->tasks()->create(['title' => 'Done one'])->forceFill(['done_at' => now()])->save();

    $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page
        ->where('shops.0.tags', ['Price objection'])
        ->where('shops.0.open_tasks', 1));
});

test('the shops grid notes dialog lists, adds and deletes notes as JSON', function () {
    $admin = crmAdmin();
    $shop = Shop::factory()->create();
    $shop->notes()->create(['body' => 'First call', 'user_id' => $admin->id]);

    $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page->where('shops.0.notes', 1));

    $this->actingAs($admin)->getJson("/admin/shops/{$shop->id}/notes")
        ->assertOk()
        ->assertJsonPath('notes.0.body', 'First call')
        ->assertJsonPath('notes.0.author', 'Ada');

    $this->actingAs($admin)->postJson("/admin/shops/{$shop->id}/notes", ['body' => ' Sent the QR sticker '])
        ->assertCreated()
        ->assertJsonCount(2, 'notes');
    expect($shop->notes()->latest('id')->first()->body)->toBe('Sent the QR sticker');

    $this->actingAs($admin)->postJson("/admin/shops/{$shop->id}/notes", ['body' => ''])->assertJsonValidationErrors('body');

    $note = $shop->notes()->first();
    $this->actingAs($admin)->deleteJson("/admin/shops/{$shop->id}/notes/{$note->id}")->assertOk()->assertJsonCount(1, 'notes');
});

test('the notes list is admin only and per shop', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $shop->notes()->create(['body' => 'Admin only']);
    $other = Shop::factory()->create();

    $this->actingAs($owner)->getJson("/admin/shops/{$shop->id}/notes")->assertForbidden();
    $this->actingAs(crmAdmin())->getJson("/admin/shops/{$other->id}/notes")->assertOk()->assertJsonCount(0, 'notes');
});

// --- Timeline ------------------------------------------------------------------------

test('the timeline has milestones, notes, follow-ups and activity, but not every stamp', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    StampLog::factory()->create(['shop_id' => $shop->id, 'action_type' => ActionType::StampAdded, 'created_at' => now()->subDays(2)]);
    ActivityLog::create(['actor_type' => 'staff', 'actor_name' => 'Sam', 'shop_id' => $shop->id, 'action' => 'stamp.added', 'description' => 'Stamped Jo']);
    ActivityLog::create(['actor_type' => 'owner', 'actor_name' => 'o', 'shop_id' => $shop->id, 'action' => 'qr.downloaded', 'description' => 'Downloaded QR code ABC123 as PNG']);
    $shop->notes()->create(['body' => 'Called them']);
    $shop->tasks()->create(['title' => 'Send QR']);

    $titles = collect(app(ShopTimeline::class)->for($shop))->pluck('title');

    expect($titles)->toContain('Shop set up', 'Owner signed up', 'First stamp given', 'Note', 'Follow-up added: Send QR', 'Downloaded QR code ABC123 as PNG')
        ->and($titles)->not->toContain('Stamped Jo');
});

// --- Follow-ups page --------------------------------------------------------------------

test('the follow-ups page splits due follow-ups from the rest and lists trials ending', function () {
    $admin = crmAdmin();
    $shop = Shop::factory()->create(['name' => 'Bean There', 'trial_ends_at' => now()->addDays(2)]);
    Shop::factory()->create(['trial_ends_at' => now()->addDays(3), 'crm_stage' => 'paid']); // paid: not listed
    Shop::factory()->create(['trial_ends_at' => now()->addDays(20)]); // far off: not listed
    $shop->tasks()->create(['title' => 'Overdue call', 'due_on' => now()->subDay()]);
    $shop->tasks()->create(['title' => 'Later', 'due_on' => now()->addDays(10)]);
    $shop->tasks()->create(['title' => 'Done'])->forceFill(['done_at' => now()])->save();

    $this->actingAs($admin)->get('/admin/follow-ups')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/FollowUps')
        ->has('today', 1)
        ->where('today.0.title', 'Overdue call')
        ->where('today.0.urgency', 'overdue')
        ->where('today.0.shop_name', 'Bean There')
        ->has('upcoming', 1)
        ->has('trials', 1)
        ->where('trials.0.trial_days_left', 2));
});

// --- AI: briefing + call prep ---------------------------------------------------------

test('the rules pick shops for the briefing, most urgent first', function () {
    $declining = Shop::factory()->create(['created_at' => now()->subDays(40)]);
    foreach ([15, 16, 17, 18, 19, 20] as $daysAgo) {
        StampLog::factory()->create(['shop_id' => $declining->id, 'action_type' => ActionType::StampAdded, 'created_at' => now()->subDays($daysAgo)]);
    }
    $ending = Shop::factory()->create(['created_at' => now()->subDays(28), 'trial_ends_at' => now()->addDays(2)]);
    $old = Shop::factory()->create(['created_at' => now()->subDays(5), 'trial_ends_at' => now()->addDays(25)]);
    Shop::factory()->create(['created_at' => now()->subDay()]); // brand new: not yet
    Shop::factory()->create(['created_at' => now()->subDays(10), 'crm_stage' => 'lost']); // lost: never

    $flags = app(CrmAssistant::class)->candidates()->map(fn ($c) => [$c['shop']->id, $c['flag']])->all();

    expect($flags)->toBe([[$declining->id, 'declining'], [$ending->id, 'trial_ending'], [$old->id, 'never_started']]);
});

test('the weekly briefing keeps only the shops it was sent, and is stored and logged', function () {
    $admin = crmAdmin();
    $shop = Shop::factory()->create(['name' => 'Bean There', 'created_at' => now()->subDays(5), 'trial_ends_at' => now()->addDays(25)]);
    fakeGeminiAnswer(['summary' => 'One shop to set up.', 'items' => [
        ['shop_id' => $shop->id, 'priority' => 'high', 'why' => 'Signed up 5 days ago, no stamps.', 'action' => 'Offer a setup call.'],
        ['shop_id' => 99999, 'priority' => 'high', 'why' => 'Made up', 'action' => 'Nothing'],
    ]]);

    $this->actingAs($admin)->post('/admin/follow-ups/briefing')->assertSessionHasNoErrors();

    $briefing = Setting::get(Setting::CRM_BRIEFING);
    expect($briefing['summary'])->toBe('One shop to set up.')
        ->and($briefing['items'])->toHaveCount(1)
        ->and($briefing['items'][0])->toMatchArray(['shop_id' => $shop->id, 'shop_name' => 'Bean There', 'flag' => 'never_started', 'action' => 'Offer a setup call.'])
        ->and(AiUsageLog::where('feature', 'crm_briefing')->count())->toBe(1);

    // Gemini got figures, never customers' names / phones (none exist here) - and the shop's name.
    Http::assertSent(fn ($request) => str_contains($request->body(), 'Bean There'));

    $this->actingAs($admin)->get('/admin/follow-ups')->assertInertia(fn ($page) => $page->where('briefing.items.0.shop_id', $shop->id));
});

test('with nothing to follow up the briefing says so, and without a key it is off', function () {
    $admin = crmAdmin();
    fakeGeminiAnswer(['summary' => '', 'items' => []]);
    $this->actingAs($admin)->post('/admin/follow-ups/briefing')->assertSessionHasErrors('briefing');

    config(['services.gemini.key' => null]);
    $this->actingAs($admin)->post('/admin/follow-ups/briefing')->assertNotFound();
});

test('"Brief me" prepares the admin for a call with one shop', function () {
    $admin = crmAdmin();
    $shop = Shop::factory()->create();
    $shop->notes()->create(['body' => 'Worried about price']);
    fakeGeminiAnswer([
        'summary' => 'New cafe, no stamps yet.',
        'talking_points' => ['Ask who scans', 'Offer the QR'],
        'likely_objections' => ['Too busy - it takes 2 seconds a customer'],
        'next_step' => 'Book a 10-minute setup call.',
    ]);

    $this->actingAs($admin)->postJson("/admin/shops/{$shop->id}/brief")->assertOk()
        ->assertJsonPath('next_step', 'Book a 10-minute setup call.')
        ->assertJsonCount(2, 'talking_points');

    Http::assertSent(fn ($request) => str_contains($request->body(), 'Worried about price'));
    expect(AiUsageLog::where('feature', 'call_prep')->where('shop_id', $shop->id)->exists())->toBeTrue();
});

test('the CRM is admin only', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->get('/admin/follow-ups')->assertForbidden();
    $this->actingAs($owner)->post("/admin/shops/{$shop->id}/notes", ['body' => 'x'])->assertForbidden();
    $this->actingAs($owner)->post("/admin/shops/{$shop->id}/tasks", ['title' => 'x'])->assertForbidden();
    $this->actingAs($owner)->postJson("/admin/shops/{$shop->id}/brief")->assertForbidden();

    expect(ShopNote::count() + ShopTask::count())->toBe(0);
});
