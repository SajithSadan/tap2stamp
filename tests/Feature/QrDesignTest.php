<?php

use App\Enums\UserRole;
use App\Models\QrDesign;
use App\Models\Shop;
use App\Models\User;
use App\Support\QrStyle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('uploads'));

function designAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

/**
 * A 1200 x 800 background with a QR block 30% wide, printed 90 mm wide.
 * Default style: 8% quiet zone each side, so the QR itself is 0.84 x 27 = 22.7 mm.
 */
function designPayload(array $overrides = [], array $style = []): array
{
    return [
        'name' => 'Summer poster',
        'image' => UploadedFile::fake()->image('poster.jpg', 1200, 800),
        'qr_x' => 0.35,
        'qr_y' => 0.275,
        'qr_size' => 0.3,
        'width_mm' => 90,
        // Sent as one JSON field, like the editor does (the form is multipart).
        'style' => json_encode([...QrStyle::DEFAULTS, ...$style]),
        ...$overrides,
    ];
}

function savedDesign(User $admin, array $overrides = [], array $style = []): QrDesign
{
    test()->actingAs($admin)->post('/admin/qr-codes/designs', designPayload($overrides, $style))->assertSessionHasNoErrors();

    return QrDesign::latest('id')->first();
}

// --- Access ---------------------------------------------------------------

test('only the admin can manage sticker designs', function () {
    $this->get('/admin/qr-codes/designs')->assertRedirect('/login');

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id]);
    $design = savedDesign(designAdmin());

    $this->actingAs($owner)->get('/admin/qr-codes/designs')->assertForbidden();
    $this->actingAs($owner)->get('/admin/qr-codes/designs/create')->assertForbidden();
    $this->actingAs($owner)->get("/admin/qr-codes/designs/{$design->id}/edit")->assertForbidden();
    $this->actingAs($owner)->post('/admin/qr-codes/designs', designPayload())->assertForbidden();
    $this->actingAs($owner)->put("/admin/qr-codes/designs/{$design->id}", designPayload())->assertForbidden();
    $this->actingAs($owner)->delete("/admin/qr-codes/designs/{$design->id}")->assertForbidden();

    expect(QrDesign::count())->toBe(1);
});

// --- Pages ----------------------------------------------------------------

test('the designs page lists saved designs with their full style', function () {
    $admin = designAdmin();
    $design = savedDesign($admin);

    $this->actingAs($admin)->get('/admin/qr-codes/designs')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/QrCodes/Designs')
        ->where('designs.0.id', $design->id)
        ->where('designs.0.image_url', $design->imageUrl())
        ->where('designs.0.style.modules', 'square')
    );
});

test('the editor opens empty for a new design and filled in for an existing one', function () {
    $admin = designAdmin();
    $design = savedDesign($admin);

    $this->actingAs($admin)->get('/admin/qr-codes/designs/create')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/QrCodes/DesignEditor')
        ->where('design', null)
        ->where('defaults.fg', '#000000')
        ->where('limits.min_qr_mm', 15)
    );

    $this->actingAs($admin)->get("/admin/qr-codes/designs/{$design->id}/edit")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/QrCodes/DesignEditor')
        ->where('design.id', $design->id)
        ->where('design.name', 'Summer poster')
    );
});

test('the QR codes page offers the saved designs for printing', function () {
    $admin = designAdmin();
    $design = savedDesign($admin);

    $this->actingAs($admin)->get('/admin/qr-codes')->assertInertia(fn ($page) => $page
        ->component('Admin/QrCodes/Index')
        ->where('designs.0.name', 'Summer poster')
        ->where('designs.0.qr_size', 0.3)
        ->where('designs.0.width_mm', $design->width_mm)
        ->has('designs.0.style')
    );
});

// --- Saving ---------------------------------------------------------------

test('an admin can save a design: the image is stored under a random name with its size', function () {
    $this->actingAs(designAdmin())->post('/admin/qr-codes/designs', designPayload())->assertSessionHas('status');

    $design = QrDesign::sole();
    expect($design->name)->toBe('Summer poster')
        ->and($design->image_width)->toBe(1200)
        ->and($design->image_height)->toBe(800)
        ->and($design->qr_x)->toBe(0.35)
        ->and($design->qr_size)->toBe(0.3)
        ->and($design->width_mm)->toBe(90)
        ->and($design->image_path)->toStartWith('qr-designs/')
        ->and($design->image_path)->not->toContain('poster');

    Storage::disk('uploads')->assertExists($design->image_path);
});

test('saving a new design opens it in the editor', function () {
    $this->actingAs(designAdmin())->post('/admin/qr-codes/designs', designPayload())
        ->assertRedirect('/admin/qr-codes/designs/'.QrDesign::sole()->id.'/edit');
});

test('the style is saved, tidied and completed', function () {
    $design = savedDesign(designAdmin(), style: [
        'fg' => '#0f2a46',
        'modules' => 'dots',
        'eyes' => 'circle',
        'border_width' => 0.03,
        'center_type' => 'text',
        'center_text' => 'SCAN',
        'ecc' => 'M',
    ]);

    $style = $design->fullStyle();
    expect($style['fg'])->toBe('#0F2A46')
        ->and($style['modules'])->toBe('dots')
        ->and($style['eyes'])->toBe('circle')
        ->and($style['border_width'])->toBe(0.03)
        ->and($style['center_text'])->toBe('SCAN')
        // Something in the middle always gets the strongest error correction.
        ->and($style['ecc'])->toBe('H');
});

test('the quiet zone can be turned off for artwork with its own plain area round the QR', function () {
    $design = savedDesign(designAdmin(), style: ['padding' => 0, 'transparent' => true]);

    expect($design->fullStyle()['padding'])->toBe(0.0)
        ->and($design->fullStyle()['transparent'])->toBeTrue();
});

// --- Print size -----------------------------------------------------------

test('a preset sets the exact print size, whatever size the browser sent', function (string $preset, int $width, int $height) {
    $design = savedDesign(designAdmin(), ['preset' => $preset, 'width_mm' => 123, 'height_mm' => 45]);

    expect($design->preset)->toBe($preset)
        ->and($design->width_mm)->toBe($width)
        ->and($design->height_mm)->toBe($height);
})->with([
    'stand' => ['stand', 90, 140],
    'table' => ['table', 60, 60],
]);

test('a custom size can set its own height, or leave it to follow the artwork', function () {
    $admin = designAdmin();

    $fixed = savedDesign($admin, ['width_mm' => 100, 'height_mm' => 150]);
    expect($fixed->preset)->toBeNull()->and($fixed->height_mm)->toBe(150);

    $auto = savedDesign($admin, ['width_mm' => 100, 'height_mm' => '']);
    expect($auto->height_mm)->toBeNull();
});

test('switching a preset design to custom clears the preset', function () {
    $admin = designAdmin();
    $design = savedDesign($admin, ['preset' => 'stand']);

    $this->actingAs($admin)->put("/admin/qr-codes/designs/{$design->id}", designPayload(['image' => null, 'preset' => '', 'width_mm' => 80, 'height_mm' => 120]))
        ->assertSessionHasNoErrors();

    expect($design->fresh()->preset)->toBeNull()
        ->and($design->fresh()->width_mm)->toBe(80);
});

test('the print size is checked', function (array $overrides, string $field) {
    $this->actingAs(designAdmin())->post('/admin/qr-codes/designs', designPayload($overrides))->assertSessionHasErrors($field);
})->with([
    'unknown preset' => [['preset' => 'billboard'], 'preset'],
    'too short' => [['height_mm' => 20], 'height_mm'],
    'too tall for A4' => [['height_mm' => 300], 'height_mm'],
]);

test('the scan-size check uses the artwork’s real printed width inside the sticker', function () {
    // 90 x 30 mm with 3:2 artwork: fitted by height, the artwork is only 45 mm wide,
    // so the QR is 0.3 x 0.84 x 45 = 11 mm - too small, although 90 mm would have been fine.
    $this->actingAs(designAdmin())->post('/admin/qr-codes/designs', designPayload(['width_mm' => 90, 'height_mm' => 30]))
        ->assertSessionHasErrors('qr_size');

    expect(QrDesign::artWidthMm(90, 30, 1200, 800))->toBe(45.0)
        ->and(QrDesign::artWidthMm(90, null, 1200, 800))->toBe(90.0);
});

test('the editor gets the presets', function () {
    $this->actingAs(designAdmin())->get('/admin/qr-codes/designs/create')->assertInertia(fn ($page) => $page
        ->where('presets.stand.width_mm', 90)
        ->where('presets.stand.height_mm', 140)
        ->where('presets.table.width_mm', 60)
    );
});

test('the QR box can have its own height; the QR inside stays square', function () {
    // A box twice as wide as it is tall: half the 1200 px width, so 300 px = 0.375 of the 800 px height.
    $design = savedDesign(designAdmin(), ['qr_size' => 0.5, 'qr_y' => 0.1], ['box_ratio' => 0.5, 'padding' => 0.02]);
    $style = $design->fullStyle();

    expect($style['box_ratio'])->toBe(0.5)
        ->and(QrStyle::blockAspect($style))->toBe(0.5)
        // The square QR fits the height: 0.5 - 2 x 0.02 = 0.46 of the box width.
        ->and(round(QrStyle::qrFraction($style), 5))->toBe(0.46);
});

test('left on auto, the box is just tall enough for the QR and caption', function () {
    $style = QrStyle::normalise(['caption_position' => 'below', 'caption_text' => 'Scan me', 'caption_size' => 0.1]);

    expect($style['box_ratio'])->toBeNull()
        ->and(QrStyle::blockAspect($style))->toBe(1.18)
        ->and(round(QrStyle::qrFraction($style), 5))->toBe(0.84);
});

test('the box shape is checked', function (array $overrides, array $style, string $field) {
    $this->actingAs(designAdmin())->post('/admin/qr-codes/designs', designPayload($overrides, $style))->assertSessionHasErrors($field);

    expect(QrDesign::count())->toBe(0);
})->with([
    'too flat' => [[], ['box_ratio' => 0.2], 'style.box_ratio'],
    'too tall' => [[], ['box_ratio' => 5], 'style.box_ratio'],
    // 0.3 wide x 2 tall = 0.9 of the 800 px height, from 0.275 down: off the bottom.
    'taller than the room left' => [[], ['box_ratio' => 2], 'qr_size'],
    // A flat 27 x 13.5 mm box leaves the QR only (0.5 - 0.16) x 27 = 9 mm.
    'so flat the QR prints too small' => [[], ['box_ratio' => 0.5], 'qr_size'],
]);

test('designs saved before styling get the plain defaults', function () {
    $design = savedDesign(designAdmin());
    $design->forceFill(['style' => null])->save();

    expect($design->fresh()->fullStyle())->toBe(QrStyle::normalise(QrStyle::DEFAULTS));
});

test('the style is checked strictly', function (array $style, string $field) {
    $this->actingAs(designAdmin())->post('/admin/qr-codes/designs', designPayload(style: $style))->assertSessionHasErrors($field);

    expect(QrDesign::count())->toBe(0);
})->with([
    'not a colour' => [['fg' => 'red'], 'style.fg'],
    'unknown dot shape' => [['modules' => 'stars'], 'style.modules'],
    'border too thick' => [['border_width' => 0.5], 'style.border_width'],
    'middle text missing' => [['center_type' => 'text', 'center_text' => ''], 'style.center_text'],
    'middle text too long' => [['center_type' => 'text', 'center_text' => 'THIRTEEN CHAR'], 'style.center_text'],
    'caption on but empty' => [['caption_position' => 'below', 'caption_text' => ''], 'style.caption_text'],
]);

test('the QR block has to sit wholly on the image', function (array $overrides, array $style) {
    $this->actingAs(designAdmin())->post('/admin/qr-codes/designs', designPayload($overrides, $style))->assertSessionHasErrors('qr_size');

    expect(QrDesign::count())->toBe(0);
})->with([
    'past the right edge' => [['qr_x' => 0.8], []],
    // 800 high: a 0.3-wide square block is 360 px = 0.45 of the height.
    'past the bottom edge' => [['qr_y' => 0.6], []],
    // Fits as a square (0.275 + 0.45), but a caption makes the block taller than what's left.
    'caption pushes it off' => [['qr_y' => 0.5], ['caption_position' => 'below', 'caption_text' => 'Scan me', 'caption_size' => 0.16]],
]);

test('the QR itself has to print big enough to scan', function (array $overrides, array $style) {
    $this->actingAs(designAdmin())->post('/admin/qr-codes/designs', designPayload($overrides, $style))->assertSessionHasErrors('qr_size');

    expect(QrDesign::count())->toBe(0);
})->with([
    // 30 mm sticker x 0.3 x 0.84 = 7.6 mm.
    'narrow sticker' => [['width_mm' => 30], []],
    // 90 x 0.3 = 27 mm block, minus a thick frame and quiet zone: 27 x (1 - 2 x 0.28) = 11.9 mm.
    'thick frame' => [[], ['border_width' => 0.08, 'padding' => 0.2]],
]);

test('a design needs a usable image', function (Closure $image) {
    $this->actingAs(designAdmin())->post('/admin/qr-codes/designs', designPayload(['image' => $image()]))->assertSessionHasErrors('image');
})->with([
    'none' => [fn () => null],
    'SVG' => [fn () => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')],
    'too small' => [fn () => UploadedFile::fake()->image('tiny.png', 200, 200)],
    'over 5 MB' => [fn () => UploadedFile::fake()->image('huge.jpg', 1200, 800)->size(6000)],
]);

test('the printed width stays within A4', function (int $width) {
    $this->actingAs(designAdmin())->post('/admin/qr-codes/designs', designPayload(['width_mm' => $width]))->assertSessionHasErrors('width_mm');
})->with([29, 187]);

// --- Logo -----------------------------------------------------------------

test('a logo in the middle needs a logo', function () {
    $this->actingAs(designAdmin())->post('/admin/qr-codes/designs', designPayload(style: ['center_type' => 'logo']))->assertSessionHasErrors('logo');
});

test('a logo is stored with the design, and can be removed', function () {
    $admin = designAdmin();
    $design = savedDesign($admin, ['logo' => UploadedFile::fake()->image('brand.png', 200, 200)], ['center_type' => 'logo']);
    $logo = $design->logo_path;

    expect($logo)->toStartWith('qr-designs/logos/')
        ->and($design->toClient()['logo_url'])->not->toBeNull();
    Storage::disk('uploads')->assertExists($logo);

    $this->actingAs($admin)->put("/admin/qr-codes/designs/{$design->id}", designPayload(['image' => null, 'remove_logo' => 1]))->assertSessionHasNoErrors();

    expect($design->fresh()->logo_path)->toBeNull();
    Storage::disk('uploads')->assertMissing($logo);
});

test('an existing logo is kept when the design is saved without a new one', function () {
    $admin = designAdmin();
    $design = savedDesign($admin, ['logo' => UploadedFile::fake()->image('brand.png', 200, 200)], ['center_type' => 'logo']);

    $this->actingAs($admin)->put("/admin/qr-codes/designs/{$design->id}", designPayload(['image' => null], ['center_type' => 'logo']))
        ->assertSessionHasNoErrors();

    Storage::disk('uploads')->assertExists($design->fresh()->logo_path);
});

// --- Editing --------------------------------------------------------------

test('updating without a new image keeps the current one', function () {
    $admin = designAdmin();
    $design = savedDesign($admin);
    $path = $design->image_path;

    $this->actingAs($admin)->put("/admin/qr-codes/designs/{$design->id}", designPayload(['image' => null, 'name' => 'Renamed', 'qr_x' => 0.1]))
        ->assertRedirect("/admin/qr-codes/designs/{$design->id}/edit");

    $design->refresh();
    expect($design->name)->toBe('Renamed')
        ->and($design->qr_x)->toBe(0.1)
        ->and($design->image_path)->toBe($path);
    Storage::disk('uploads')->assertExists($path);
});

test('replacing the image stores the new one and deletes the old file', function () {
    $admin = designAdmin();
    $design = savedDesign($admin);
    $old = $design->image_path;

    $this->actingAs($admin)->put("/admin/qr-codes/designs/{$design->id}", designPayload([
        'image' => UploadedFile::fake()->image('square.png', 1000, 1000),
    ]))->assertSessionHasNoErrors();

    $design->refresh();
    expect($design->image_path)->not->toBe($old)
        ->and($design->image_width)->toBe(1000)
        ->and($design->image_height)->toBe(1000);
    Storage::disk('uploads')->assertMissing($old);
    Storage::disk('uploads')->assertExists($design->image_path);
});

test('the fit check uses the current image when none is uploaded', function () {
    $admin = designAdmin();
    $design = savedDesign($admin);

    // Fine on a square image, but past the bottom of this 1200 x 800 one.
    $this->actingAs($admin)->put("/admin/qr-codes/designs/{$design->id}", designPayload(['image' => null, 'qr_y' => 0.6]))
        ->assertSessionHasErrors('qr_size');
});

test('deleting a design removes it and its files', function () {
    $admin = designAdmin();
    $design = savedDesign($admin, ['logo' => UploadedFile::fake()->image('brand.png', 200, 200)], ['center_type' => 'logo']);

    $this->actingAs($admin)->delete("/admin/qr-codes/designs/{$design->id}")->assertRedirect('/admin/qr-codes/designs');

    expect(QrDesign::count())->toBe(0);
    Storage::disk('uploads')->assertMissing($design->image_path);
    Storage::disk('uploads')->assertMissing($design->logo_path);
});

// --- Serial number style ----------------------------------------------------

test('a design can print a serial number, with its own prefix, size, position and colour', function () {
    $design = savedDesign(designAdmin(), style: [
        'serial_enabled' => true,
        'serial_prefix' => 'No. ',
        'serial_size' => 0.06,
        'serial_offset' => 0.1,
        'serial_color' => '#0f2a46',
        'serial_bold' => true,
    ]);

    $style = $design->fullStyle();
    expect($style['serial_enabled'])->toBeTrue()
        ->and($style['serial_prefix'])->toBe('No. ')
        ->and($style['serial_size'])->toBe(0.06)
        ->and($style['serial_offset'])->toBe(0.1)
        ->and($style['serial_color'])->toBe('#0F2A46')
        ->and($style['serial_bold'])->toBeTrue();
});

test('designs without the setting print no serial number', function () {
    $style = savedDesign(designAdmin())->fullStyle();

    expect($style['serial_enabled'])->toBeFalse()
        // Normal weight unless Bold is switched on.
        ->and($style['serial_bold'])->toBeFalse();
});

test('the serial number settings are checked', function (array $style, string $field) {
    $this->actingAs(designAdmin())->post('/admin/qr-codes/designs', designPayload(style: $style))->assertSessionHasErrors($field);
})->with([
    'prefix too long' => [['serial_prefix' => 'TOO LONG!'], 'style.serial_prefix'],
    'text too big' => [['serial_size' => 0.5], 'style.serial_size'],
    'too far up' => [['serial_offset' => 0.9], 'style.serial_offset'],
    'not a colour' => [['serial_color' => 'blue'], 'style.serial_color'],
]);
