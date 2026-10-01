<?php

use App\Enums\UserRole;
use App\Models\QrBatch;
use App\Models\QrCode;
use App\Models\QrDesign;
use App\Models\Shop;
use App\Models\User;

function qrAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

// --- Access ---------------------------------------------------------------

test('a guest is redirected to login from the QR admin page', function () {
    $this->get('/admin/qr-codes')->assertRedirect('/login');
});

test('an owner cannot manage QR codes', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id]);
    $qr = QrCode::factory()->create();

    $this->actingAs($owner)->get('/admin/qr-codes')->assertForbidden();
    $this->actingAs($owner)->post('/admin/qr-codes', ['quantity' => 5])->assertForbidden();
    $this->actingAs($owner)->put("/admin/qr-codes/{$qr->id}", ['destination_url' => 'https://example.com'])->assertForbidden();
    $this->actingAs($owner)->postJson('/admin/qr-codes/print', ['batch' => $qr->qr_batch_id])->assertForbidden();

    expect(QrCode::count())->toBe(1);
});

// --- Generation -----------------------------------------------------------

test('an admin can generate a batch of unique, short codes', function () {
    $response = $this->actingAs(qrAdmin())->post('/admin/qr-codes', ['quantity' => 250, 'name' => 'Summer festival']);

    $batch = QrBatch::sole();
    $response->assertRedirect("/admin/qr-codes?batch={$batch->id}");
    $response->assertSessionHas('status');

    $codes = $batch->codes()->pluck('code');
    expect($codes)->toHaveCount(250)
        ->and($codes->unique())->toHaveCount(250)
        ->and($batch->name)->toBe('Summer festival');

    // 6 characters, never the look-alikes 0/O/1/I.
    $codes->each(fn ($code) => expect($code)->toMatch('/^[2-9A-HJ-NP-Z]{6}$/'));

    // New codes start unmapped.
    expect($batch->codes()->whereNotNull('destination_url')->count())->toBe(0);
});

test('a second batch never reuses a code from the first', function () {
    $admin = qrAdmin();
    $this->actingAs($admin)->post('/admin/qr-codes', ['quantity' => 500]);
    $this->actingAs($admin)->post('/admin/qr-codes', ['quantity' => 500]);

    expect(QrBatch::count())->toBe(2)
        ->and(QrCode::count())->toBe(1000)
        ->and(QrCode::distinct()->count('code'))->toBe(1000);
});

test('the quantity must be between 1 and the per-batch maximum', function (mixed $quantity) {
    $this->actingAs(qrAdmin())->post('/admin/qr-codes', ['quantity' => $quantity])->assertSessionHasErrors('quantity');

    expect(QrCode::count())->toBe(0);
})->with([0, 1001, 'lots', null]);

// --- Listing --------------------------------------------------------------

test('the QR page lists codes with stats and batches', function () {
    $batch = QrBatch::factory()->create(['name' => 'Posters']);
    $shop = Shop::factory()->create(['name' => 'Artisan Cafe']);
    QrCode::factory()->count(2)->create(['qr_batch_id' => $batch->id]);
    QrCode::factory()->mapped()->create(['qr_batch_id' => $batch->id, 'shop_id' => $shop->id]);

    $this->actingAs(qrAdmin())->get('/admin/qr-codes')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/QrCodes/Index')
        ->where('stats.total', 3)
        ->where('stats.mapped', 1)
        ->where('stats.unmapped', 2)
        ->where('stats.batches', 1)
        ->where('batches.0.label', "Batch #{$batch->id} · Posters")
        ->where('batches.0.mapped_count', 1)
        ->where('shops.0.name', 'Artisan Cafe')
        ->where('shops.0.qr_codes_count', 1)
        ->has('codes.data', 3)
        ->where('codes.data.2.shop_name', 'Artisan Cafe')
        ->where('codes.data.0.scan_url', fn ($url) => str_ends_with($url, '/qr/'.QrCode::orderBy('id')->first()->code))
    );
});

test('QR codes are automatically assigned when mapped to a shop card and can be filtered by shop', function () {
    $batch = QrBatch::factory()->create();
    $shop = Shop::factory()->create(['name' => 'Artisan Cafe', 'slug' => 'artisan-cafe']);
    $assigned = QrCode::factory()->create(['qr_batch_id' => $batch->id]);

    $admin = qrAdmin();
    $this->actingAs($admin)->put("/admin/qr-codes/{$assigned->id}", [
        'destination_url' => route('card.show', $shop->slug),
    ])->assertRedirect();

    expect($assigned->fresh()->shop_id)->toBe($shop->id);

    $this->actingAs($admin)->get("/admin/qr-codes?shop={$shop->id}")
        ->assertInertia(fn ($page) => $page
            ->where('filters.shop', $shop->id)
            ->has('codes.data', 1)
            ->where('codes.data.0.shop_name', 'Artisan Cafe')
        );
});

test('a destination on another site is not automatically assigned by a matching path', function () {
    $shop = Shop::factory()->create(['slug' => 'artisan-cafe']);
    $qr = QrCode::factory()->mapped(route('card.show', $shop->slug))->create(['shop_id' => $shop->id]);

    $this->actingAs(qrAdmin())->put("/admin/qr-codes/{$qr->id}", [
        'destination_url' => 'https://example.com/s/artisan-cafe',
    ])->assertRedirect();

    expect($qr->fresh()->shop_id)->toBeNull();
});

test('codes can be searched and filtered by batch and mapping status', function () {
    $first = QrBatch::factory()->create();
    $second = QrBatch::factory()->create();
    QrCode::factory()->create(['qr_batch_id' => $first->id, 'code' => 'ABCDEF']);
    QrCode::factory()->mapped('https://example.com/summer-festival')->create(['qr_batch_id' => $second->id, 'code' => 'GHJKLM']);
    QrCode::factory()->create(['qr_batch_id' => $second->id, 'code' => 'NPQRST']);

    $admin = qrAdmin();

    $this->actingAs($admin)->get('/admin/qr-codes?search=abcd')
        ->assertInertia(fn ($page) => $page->has('codes.data', 1)->where('codes.data.0.code', 'ABCDEF'));

    $this->actingAs($admin)->get('/admin/qr-codes?search=summer')
        ->assertInertia(fn ($page) => $page->has('codes.data', 1)->where('codes.data.0.code', 'GHJKLM'));

    $this->actingAs($admin)->get("/admin/qr-codes?batch={$second->id}")
        ->assertInertia(fn ($page) => $page->has('codes.data', 2)->where('filters.batch', $second->id));

    $this->actingAs($admin)->get('/admin/qr-codes?status=unmapped')
        ->assertInertia(fn ($page) => $page->has('codes.data', 2));

    $this->actingAs($admin)->get('/admin/qr-codes?status=mapped')
        ->assertInertia(fn ($page) => $page->has('codes.data', 1)->where('codes.data.0.code', 'GHJKLM'));
});

// --- Mapping --------------------------------------------------------------

test('an admin can map a code, change it later, and unmap it - the code itself never changes', function () {
    $qr = QrCode::factory()->create(['code' => 'K7F2QX']);
    $admin = qrAdmin();

    $this->actingAs($admin)->put("/admin/qr-codes/{$qr->id}", ['destination_url' => 'https://example.com/event/summer-festival'])
        ->assertSessionHasNoErrors();
    expect($qr->fresh())
        ->code->toBe('K7F2QX')
        ->destination_url->toBe('https://example.com/event/summer-festival')
        ->mapped_at->not->toBeNull();

    $this->actingAs($admin)->put("/admin/qr-codes/{$qr->id}", ['destination_url' => 'https://example.com/menu']);
    expect($qr->fresh())->code->toBe('K7F2QX')->destination_url->toBe('https://example.com/menu');

    $this->actingAs($admin)->put("/admin/qr-codes/{$qr->id}", ['destination_url' => '']);
    expect($qr->fresh())->code->toBe('K7F2QX')->destination_url->toBeNull()->mapped_at->toBeNull();
});

test('only http and https destinations are accepted', function (string $url) {
    $qr = QrCode::factory()->create();

    $this->actingAs(qrAdmin())->put("/admin/qr-codes/{$qr->id}", ['destination_url' => $url])
        ->assertSessionHasErrors('destination_url');

    expect($qr->fresh()->destination_url)->toBeNull();
})->with([
    'javascript' => 'javascript:alert(1)',
    'data' => 'data:text/html,<script>alert(1)</script>',
    'ftp' => 'ftp://example.com/file',
    'not a url' => 'summer festival',
]);

// --- Scanning -------------------------------------------------------------

test('scanning a mapped code redirects to its destination without a cacheable 301', function () {
    QrCode::factory()->mapped('https://example.com/event/summer-festival')->create(['code' => 'K7F2QX']);

    $response = $this->get('/qr/K7F2QX');

    $response->assertStatus(302)->assertRedirect('https://example.com/event/summer-festival');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('codes are matched case-insensitively, as people may type them from a sticker', function () {
    QrCode::factory()->mapped('https://example.com/menu')->create(['code' => 'K7F2QX']);

    $this->get('/qr/k7f2qx')->assertRedirect('https://example.com/menu');
});

test('scanning an unmapped code shows the Nothing found page with the code', function () {
    QrCode::factory()->create(['code' => 'K7F2QX']);

    $this->get('/qr/K7F2QX')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Qr/NotFound')->where('code', 'K7F2QX'));
});

test('scanning an unknown code shows the Nothing found page without echoing the input', function () {
    $this->get('/qr/ZZZZZZ')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Qr/NotFound')->where('code', null));
});

test('the new destination applies straight away after remapping', function () {
    $qr = QrCode::factory()->mapped('https://example.com/old')->create(['code' => 'K7F2QX']);
    $this->get('/qr/K7F2QX')->assertRedirect('https://example.com/old');

    $this->actingAs(qrAdmin())->put("/admin/qr-codes/{$qr->id}", ['destination_url' => 'https://example.com/new']);

    // Scan again as a customer - a logged-in admin would get the map screen.
    auth()->logout();
    $this->get('/qr/K7F2QX')->assertRedirect('https://example.com/new');
});

test('an admin scanning a sticker gets the map screen instead of the customer view', function (bool $mapped) {
    $factory = QrCode::factory();
    $qr = ($mapped ? $factory->mapped('https://example.com/menu') : $factory)->create(['code' => 'K7F2QX']);

    $this->actingAs(qrAdmin())->get('/qr/K7F2QX')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/QrCodes/Scan')
        ->where('qr.id', $qr->id)
        ->where('qr.code', 'K7F2QX')
        ->where('qr.destination_url', $mapped ? 'https://example.com/menu' : null)
    );
})->with(['unmapped' => false, 'mapped' => true]);

test('an owner scanning a sticker gets the normal customer view', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    QrCode::factory()->mapped('https://example.com/menu')->create(['code' => 'K7F2QX']);

    $this->actingAs($owner)->get('/qr/K7F2QX')->assertRedirect('https://example.com/menu');
});

test('mapping from the scan screen returns to it with the new destination', function () {
    $qr = QrCode::factory()->create(['code' => 'K7F2QX']);
    $admin = qrAdmin();

    $this->actingAs($admin)
        ->from('/qr/K7F2QX')
        ->put("/admin/qr-codes/{$qr->id}", ['destination_url' => 'https://example.com/menu'])
        ->assertRedirect('/qr/K7F2QX');

    $this->actingAs($admin)->get('/qr/K7F2QX')
        ->assertInertia(fn ($page) => $page->where('qr.destination_url', 'https://example.com/menu'));
});

test('an admin who logs in after being bounced from the QR page lands back on it', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin, 'password' => 'password']);

    $this->get('/admin/qr-codes')->assertRedirect('/login');
    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin/qr-codes');
});

// --- Printing -------------------------------------------------------------

test('print data returns a whole batch in generation order', function () {
    $batch = QrBatch::factory()->create();
    $codes = QrCode::factory()->count(3)->create(['qr_batch_id' => $batch->id]);
    QrCode::factory()->create(); // another batch - not included

    $this->actingAs(qrAdmin())->postJson('/admin/qr-codes/print', ['batch' => $batch->id])
        ->assertOk()
        ->assertJsonPath('title', "Batch #{$batch->id}")
        ->assertJsonCount(3, 'codes')
        ->assertJsonPath('codes.0.code', $codes[0]->code)
        ->assertJsonPath('codes.0.scan_url', route('qr.show', $codes[0]->code));
});

test('print data returns just the selected codes', function () {
    $batch = QrBatch::factory()->create();
    $codes = QrCode::factory()->count(4)->create(['qr_batch_id' => $batch->id]);

    $this->actingAs(qrAdmin())->postJson('/admin/qr-codes/print', ['ids' => [$codes[1]->id, $codes[3]->id]])
        ->assertOk()
        ->assertJsonCount(2, 'codes')
        ->assertJsonPath('codes.0.code', $codes[1]->code)
        ->assertJsonPath('codes.1.code', $codes[3]->code)
        ->assertJsonPath('codes.0.id', $codes[1]->id);
});

test('a successful print records every sticker design used for each QR', function () {
    $batch = QrBatch::factory()->create();
    $codes = QrCode::factory()->count(2)->create(['qr_batch_id' => $batch->id]);
    $front = QrDesign::create([
        'name' => 'Table card', 'image_path' => 'designs/table.png', 'image_width' => 600, 'image_height' => 600,
        'qr_x' => 0.2, 'qr_y' => 0.2, 'qr_size' => 0.5, 'width_mm' => 60,
    ]);
    $back = QrDesign::create([
        'name' => 'Back side', 'image_path' => 'designs/back.png', 'image_width' => 600, 'image_height' => 600,
        'qr_x' => 0.2, 'qr_y' => 0.2, 'qr_size' => 0.5, 'width_mm' => 60,
    ]);

    $this->actingAs(qrAdmin())->postJson('/admin/qr-codes/record-print', [
        'ids' => $codes->pluck('id')->all(),
        'design_ids' => [$front->id, $back->id],
    ])->assertOk()->assertJsonPath('recorded', 2);

    foreach ($codes as $code) {
        expect($code->designs()->pluck('qr_designs.id')->all())->toEqualCanonicalizing([$front->id, $back->id]);
    }

    $this->actingAs(qrAdmin())->get('/admin/qr-codes?design='.$front->id)
        ->assertInertia(fn ($page) => $page
            ->where('filters.design', $front->id)
            ->where('designs.0.codes_count', 2)
            ->has('codes.data', 2)
            ->where('codes.data.0.design_names', ['Table card', 'Back side'])
        );
});

test('print design tracking validates QR and design IDs', function () {
    $this->actingAs(qrAdmin())->postJson('/admin/qr-codes/record-print', [
        'ids' => [999999],
        'design_ids' => [999999],
    ])->assertUnprocessable()->assertJsonValidationErrors(['ids.0', 'design_ids.0']);
});

test('print data needs a batch or a selection', function () {
    $this->actingAs(qrAdmin())->postJson('/admin/qr-codes/print', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['batch', 'ids']);
});

// --- Deleting a batch -------------------------------------------------------

test('an admin can delete a batch with all its codes, leaving other batches alone', function () {
    $doomed = QrBatch::factory()->create();
    QrCode::factory()->count(3)->create(['qr_batch_id' => $doomed->id]);
    $kept = QrBatch::factory()->create();
    QrCode::factory()->count(2)->create(['qr_batch_id' => $kept->id]);

    $this->actingAs(qrAdmin())->delete("/admin/qr-codes/batches/{$doomed->id}")
        ->assertRedirect('/admin/qr-codes')
        ->assertSessionHas('status');

    expect(QrBatch::find($doomed->id))->toBeNull()
        ->and(QrCode::where('qr_batch_id', $doomed->id)->count())->toBe(0)
        ->and(QrCode::where('qr_batch_id', $kept->id)->count())->toBe(2);
});

test('a deleted batch\'s stickers show "Nothing found" when scanned', function () {
    $batch = QrBatch::factory()->create();
    $qr = QrCode::factory()->mapped('https://example.com/menu')->create(['qr_batch_id' => $batch->id]);

    $this->actingAs(qrAdmin())->delete("/admin/qr-codes/batches/{$batch->id}");
    auth()->logout();

    $this->get("/qr/{$qr->code}")->assertNotFound()->assertInertia(fn ($page) => $page->component('Qr/NotFound'));
});

test('only the admin can delete a batch', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id]);
    $batch = QrBatch::factory()->create();
    QrCode::factory()->create(['qr_batch_id' => $batch->id]);

    $this->delete("/admin/qr-codes/batches/{$batch->id}")->assertRedirect('/login');
    $this->actingAs($owner)->delete("/admin/qr-codes/batches/{$batch->id}")->assertForbidden();

    expect(QrCode::count())->toBe(1);
});

// --- Serial numbers -----------------------------------------------------------

test('each code\'s serial is its position in its batch, in generation order', function () {
    $first = QrBatch::factory()->create();
    $firstCodes = QrCode::factory()->count(3)->create(['qr_batch_id' => $first->id]);
    $second = QrBatch::factory()->create();
    QrCode::factory()->count(2)->create(['qr_batch_id' => $second->id]);

    $response = $this->actingAs(qrAdmin())->postJson('/admin/qr-codes/print', ['batch' => $first->id])->assertOk();

    expect(collect($response->json('codes'))->pluck('serial')->all())->toBe([1, 2, 3])
        ->and($response->json('codes.0.code'))->toBe($firstCodes[0]->code);
});

test('a code printed on its own keeps its serial from the batch', function () {
    $batch = QrBatch::factory()->create();
    $codes = QrCode::factory()->count(5)->create(['qr_batch_id' => $batch->id]);

    $response = $this->actingAs(qrAdmin())->postJson('/admin/qr-codes/print', ['ids' => [$codes[3]->id]])->assertOk();

    expect($response->json('codes'))->toHaveCount(1)
        ->and($response->json('codes.0.serial'))->toBe(4);
});

test('the QR list shows each code\'s serial', function () {
    $batch = QrBatch::factory()->create();
    QrCode::factory()->count(2)->create(['qr_batch_id' => $batch->id]);

    $this->actingAs(qrAdmin())->get('/admin/qr-codes')->assertInertia(fn ($page) => $page
        ->where('codes.data.0.serial', 1)
        ->where('codes.data.1.serial', 2)
    );
});
