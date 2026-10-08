<?php

use App\Enums\UserRole;
use App\Models\QrBatch;
use App\Models\QrCode;
use App\Models\QrDesign;
use App\Models\Shop;
use App\Models\User;
use App\Services\QrCodeGenerator;
use App\Support\Countries;

/** @return array{0: User, 1: Shop} */
function overseasOwner(string $country = Countries::INDIA, array $shop = []): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    return [$owner, Shop::factory()->create(['user_id' => $owner->id, 'country' => $country, ...$shop])];
}

function qrDesign(string $name, bool $default = false): QrDesign
{
    $design = QrDesign::create([
        'name' => $name, 'image_path' => "designs/{$name}.png", 'image_width' => 600, 'image_height' => 600,
        'qr_x' => 0.2, 'qr_y' => 0.2, 'qr_size' => 0.5, 'width_mm' => 60,
    ]);
    $design->forceFill(['is_default' => $default])->save();

    return $design;
}

/* ---------- Automatic QR for shops outside the UK ---------- */

test('an overseas owner gets a QR code the first time they open the page, mapped to their card', function () {
    [$owner, $shop] = overseasOwner();

    $this->actingAs($owner)->get('/dashboard/qr-codes')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Dashboard/QrCodes')
        ->has('codes', 1));

    $qr = QrCode::sole();
    expect($qr->shop_id)->toBe($shop->id)
        ->and($qr->batch->name)->toBe(QrCodeGenerator::OVERSEAS_BATCH)
        ->and($qr->destination_url)->toBe(route('card.show', $shop))
        ->and($qr->mapped_at)->not->toBeNull()
        ->and($qr->serial)->toBe(1);

    // Scanning it opens the loyalty card.
    auth()->logout();
    $this->get("/qr/{$qr->code}")->assertRedirect(route('card.show', $shop));
});

test('opening the page again never issues a second code', function () {
    [$owner] = overseasOwner();

    $this->actingAs($owner)->get('/dashboard/qr-codes');
    $this->get('/dashboard/qr-codes')->assertInertia(fn ($page) => $page->has('codes', 1));

    expect(QrCode::count())->toBe(1);
});

test('overseas shops share one batch, numbered in turn', function () {
    [$first] = overseasOwner();
    [$second] = overseasOwner(Countries::INDIA);

    $this->actingAs($first)->get('/dashboard/qr-codes');
    $this->actingAs($second)->get('/dashboard/qr-codes');

    expect(QrBatch::count())->toBe(1)
        ->and(QrCode::orderBy('id')->pluck('serial')->all())->toBe([1, 2]);
});

test('a shop that already has codes the admin assigned gets none extra', function () {
    [$owner, $shop] = overseasOwner();
    QrCode::factory()->create(['shop_id' => $shop->id, 'destination_url' => 'https://example.com/x']);

    $this->actingAs($owner)->get('/dashboard/qr-codes');

    expect(QrCode::count())->toBe(1);
});

test('UK shops are never issued a code (they order a counter display)', function () {
    [$owner] = overseasOwner(Countries::UK);

    $this->actingAs($owner)->get('/dashboard/qr-codes');

    expect(QrCode::count())->toBe(0);
});

/* ---------- Which design the owner downloads in ---------- */

test('the owner downloads in their shop\'s own design, else the default, else a plain QR', function () {
    [$owner, $shop] = overseasOwner();
    $page = fn () => $this->actingAs($owner->fresh())->get('/dashboard/qr-codes')->viewData('page')['props']['design'];

    expect($page())->toBeNull(); // no design anywhere: plain QR

    $default = qrDesign('Table card', default: true);
    expect($page()['id'])->toBe($default->id);

    $own = qrDesign('Stand');
    $shop->update(['qr_design_id' => $own->id]);
    expect($page()['id'])->toBe($own->id);
});

test('the admin marks one design as the default, and can unset it', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $first = qrDesign('First', default: true);
    $second = qrDesign('Second');

    $this->actingAs($admin)->put("/admin/qr-codes/designs/{$second->id}/default", ['default' => true])->assertSessionHas('status');

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue()
        ->and(QrDesign::defaultDesign()->id)->toBe($second->id);

    $this->get('/admin/qr-codes/designs')->assertInertia(fn ($page) => $page
        ->where('designs', fn ($designs) => collect($designs)->firstWhere('id', $second->id)['is_default'] === true));

    $this->put("/admin/qr-codes/designs/{$second->id}/default", ['default' => false]);
    expect(QrDesign::defaultDesign())->toBeNull();
});

test('the shop settings page says which default design a shop falls back to', function () {
    [, $shop] = overseasOwner();
    qrDesign('Table card', default: true);

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get("/admin/shops/{$shop->id}/settings")
        ->assertInertia(fn ($page) => $page->where('defaultQrDesign', 'Table card'));
});

test('owners cannot change the default design', function () {
    [$owner] = overseasOwner();
    $design = qrDesign('Stand');

    $this->actingAs($owner)->put("/admin/qr-codes/designs/{$design->id}/default", ['default' => true])->assertForbidden();
    expect($design->fresh()->is_default)->toBeFalse();
});
