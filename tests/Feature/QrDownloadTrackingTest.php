<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\QrCode;
use App\Models\Shop;
use App\Models\User;

/** @return array{0: User, 1: Shop} */
function qrOwner(string $country = 'IN'): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'country' => $country]);

    return [$owner, $shop];
}

test('the first visit to the QR codes page is recorded once', function () {
    [$owner, $shop] = qrOwner();

    $this->actingAs($owner)->get('/dashboard/qr-codes')->assertOk();
    $first = $shop->fresh()->qr_page_viewed_at;
    expect($first)->not->toBeNull();

    $this->travel(2)->days();
    $this->actingAs($owner)->get('/dashboard/qr-codes')->assertOk();

    expect($shop->fresh()->qr_page_viewed_at->equalTo($first))->toBeTrue()
        ->and(ActivityLog::where('action', 'qr.page_opened')->where('shop_id', $shop->id)->count())->toBe(1);
});

test('a download is counted and logged with the code and format', function () {
    [$owner, $shop] = qrOwner();
    $qr = QrCode::factory()->mapped()->create(['shop_id' => $shop->id]);

    $this->actingAs($owner)->postJson('/dashboard/qr-codes/downloaded', [
        'source' => 'qr_codes', 'format' => 'png', 'qr_code_ids' => [$qr->id], 'ok' => true,
    ])->assertOk();
    $this->actingAs($owner)->postJson('/dashboard/qr-codes/downloaded', [
        'source' => 'qr_codes', 'format' => 'pdf', 'qr_code_ids' => [$qr->id], 'ok' => true,
    ])->assertOk();

    $shop->refresh();
    expect($shop->qr_downloads)->toBe(2)
        ->and($shop->qr_downloaded_at)->not->toBeNull()
        ->and($shop->qr_download_failures)->toBe(0);

    $log = ActivityLog::where('action', 'qr.downloaded')->latest('id')->first();
    expect($log->shop_id)->toBe($shop->id)
        ->and($log->actor_type)->toBe('owner')
        ->and($log->description)->toContain($qr->code)->toContain('PDF');
});

test('a failed download is counted separately and does not count as downloaded', function () {
    [$owner, $shop] = qrOwner();
    $qr = QrCode::factory()->mapped()->create(['shop_id' => $shop->id]);

    $this->actingAs($owner)->postJson('/dashboard/qr-codes/downloaded', [
        'source' => 'qr_codes', 'format' => 'pdf', 'qr_code_ids' => [$qr->id], 'ok' => false, 'error' => 'Out of memory',
    ])->assertOk();

    $shop->refresh();
    expect($shop->qr_download_failures)->toBe(1)
        ->and($shop->qr_downloads)->toBe(0)
        ->and($shop->qr_downloaded_at)->toBeNull()
        ->and(ActivityLog::where('action', 'qr.download_failed')->value('description'))->toContain('Out of memory');
});

test('the counter QR on Settings is counted without a code', function () {
    [$owner, $shop] = qrOwner('GB');
    $shop->update(['show_card_link' => true]);

    $this->actingAs($owner)->postJson('/dashboard/qr-codes/downloaded', [
        'source' => 'settings', 'format' => 'png', 'ok' => true,
    ])->assertOk();

    expect($shop->fresh()->qr_downloads)->toBe(1)
        ->and(ActivityLog::where('action', 'qr.downloaded')->value('description'))->toContain('Settings');
});

test('another shop\'s code is refused and nothing is recorded', function () {
    [$owner, $shop] = qrOwner();
    [, $other] = qrOwner();
    $mine = QrCode::factory()->mapped()->create(['shop_id' => $shop->id]);
    $theirs = QrCode::factory()->mapped()->create(['shop_id' => $other->id]);

    $this->actingAs($owner)->postJson('/dashboard/qr-codes/downloaded', [
        'source' => 'qr_codes', 'format' => 'pdf', 'qr_code_ids' => [$mine->id, $theirs->id], 'ok' => true,
    ])->assertNotFound();

    expect($shop->fresh()->qr_downloads)->toBe(0)
        ->and($other->fresh()->qr_downloads)->toBe(0)
        ->and(ActivityLog::where('action', 'qr.downloaded')->exists())->toBeFalse();
});

test('the input is validated', function () {
    [$owner] = qrOwner();

    $this->actingAs($owner)->postJson('/dashboard/qr-codes/downloaded', [
        'source' => 'elsewhere', 'format' => 'gif', 'ok' => 'maybe',
    ])->assertJsonValidationErrors(['source', 'format', 'ok']);

    $this->actingAs($owner)->postJson('/dashboard/qr-codes/downloaded', [
        'source' => 'qr_codes', 'format' => 'png', 'ok' => true,
    ])->assertJsonValidationErrors(['qr_code_ids']);
});

test('an admin viewing as the owner is not counted', function () {
    [, $shop] = qrOwner();
    $qr = QrCode::factory()->mapped()->create(['shop_id' => $shop->id]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->put('/admin/view-as-owner/editing', ['editing' => true]);

    $this->get('/dashboard/qr-codes')->assertOk();
    // Allowed through (editing is on), just not counted.
    $this->postJson('/dashboard/qr-codes/downloaded', [
        'source' => 'qr_codes', 'format' => 'png', 'qr_code_ids' => [$qr->id], 'ok' => true,
    ])->assertOk();

    $shop->refresh();
    expect($shop->qr_page_viewed_at)->toBeNull()
        ->and($shop->qr_downloads)->toBe(0)
        ->and(ActivityLog::whereIn('action', ['qr.page_opened', 'qr.downloaded'])->exists())->toBeFalse();
});

test('the admin shops grid shows where each owner is with their QR', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $notOpened = Shop::factory()->create(['country' => 'IN', 'name' => 'A', 'created_at' => now()->subDays(4)]);
    $opened = Shop::factory()->create(['country' => 'IN', 'name' => 'B', 'created_at' => now()->subDays(3), 'qr_page_viewed_at' => now()]);
    $failed = Shop::factory()->create(['country' => 'IN', 'name' => 'C', 'created_at' => now()->subDays(2), 'qr_page_viewed_at' => now(), 'qr_download_failures' => 2]);
    $done = Shop::factory()->create(['country' => 'IN', 'name' => 'D', 'created_at' => now()->subDay(), 'qr_page_viewed_at' => now(), 'qr_downloaded_at' => now(), 'qr_downloads' => 3]);
    Shop::factory()->create(['country' => 'GB', 'name' => 'E']); // uses the counter display

    $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page
        ->component('Admin/Index')
        ->where('shops.0.qr', null)
        ->where('shops.1.qr.state', 'downloaded')
        ->where('shops.1.qr.downloads', 3)
        ->where('shops.2.qr.state', 'failed')
        ->where('shops.3.qr.state', 'opened')
        ->where('shops.4.qr.state', 'not_opened')
    );
});
