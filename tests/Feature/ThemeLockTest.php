<?php

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use App\Support\StampIcons;
use App\Support\ThemeCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function themeOwner(array $shop = []): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    return [$owner, Shop::factory()->create(['user_id' => $owner->id, ...$shop])];
}

function businessPlan(): Plan
{
    $plan = Plan::where('is_default', false)->ordered()->firstOrFail();
    $plan->update(['all_themes' => true]);

    return $plan;
}

test('a shop on a plan without all themes can only pick the free ones', function () {
    [$owner, $shop] = themeOwner();
    $locked = 'bold-local';
    expect(ThemeCatalog::freeThemes())->not->toContain($locked);

    $this->actingAs($owner)->put('/dashboard/theme', ['theme' => $locked])->assertSessionHasErrors('theme');
    expect($shop->fresh()->theme)->toBeNull();

    $this->actingAs($owner)->put('/dashboard/theme', ['theme' => 'modern-minimal'])->assertSessionHasNoErrors();
    expect($shop->fresh()->theme)->toBe('modern-minimal');
});

test('the theme page sends the lock with the free themes and the plan that opens the rest', function () {
    [$owner] = themeOwner();
    $plan = businessPlan();

    $this->actingAs($owner)->get('/dashboard/theme')
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard/Theme')
            ->where('themeLock.free', ThemeCatalog::freeThemes())
            ->where('themeLock.plan', $plan->name)
        );
});

test('a paid-up shop on a plan with all themes can pick any theme', function () {
    $plan = businessPlan();
    [$owner, $shop] = themeOwner(['plan_id' => $plan->id, 'subscription_ends_at' => now()->addYear()]);

    $this->actingAs($owner)->get('/dashboard/theme')->assertInertia(fn ($page) => $page->where('themeLock', null));
    $this->actingAs($owner)->put('/dashboard/theme', ['theme' => 'bold-local'])->assertSessionHasNoErrors();

    expect($shop->fresh()->theme)->toBe('bold-local');
});

test('the themes lock again once that plan has ended, but the shop keeps its theme', function () {
    $plan = businessPlan();
    [$owner, $shop] = themeOwner([
        'plan_id' => $plan->id, 'theme' => 'bold-local',
        'subscription_ends_at' => now()->subDay(), 'trial_ends_at' => now()->subMonth(),
    ]);

    expect($shop->hasAllThemes())->toBeFalse();
    $this->actingAs($owner)->put('/dashboard/theme', ['theme' => 'neon-nightlife'])->assertSessionHasErrors('theme');
    // Re-picking the theme it already has is fine.
    $this->actingAs($owner)->put('/dashboard/theme', ['theme' => 'bold-local'])->assertSessionHasNoErrors();
    expect($shop->fresh()->theme)->toBe('bold-local');
});

test('without all themes only the free stamp icons can be picked', function () {
    [$owner, $shop] = themeOwner();

    $this->actingAs($owner)->put('/dashboard/theme/stamp-icon', ['stamp_icon' => 'crown'])->assertSessionHasErrors('stamp_icon');
    $this->actingAs($owner)->put('/dashboard/theme/stamp-icon', ['stamp_icon' => 'star'])->assertSessionHasNoErrors();
    expect($shop->fresh()->stamp_icon)->toBe('star');

    $this->actingAs($owner)->get('/dashboard/theme')->assertInertia(fn ($page) => $page->where('themeLock.stampIcons', StampIcons::FREE));
});

test('a paid-up shop with all themes can pick any stamp icon', function () {
    [$owner, $shop] = themeOwner(['plan_id' => businessPlan()->id, 'subscription_ends_at' => now()->addYear()]);

    $this->actingAs($owner)->put('/dashboard/theme/stamp-icon', ['stamp_icon' => 'coffee'])->assertSessionHasNoErrors();
    expect($shop->fresh()->stamp_icon)->toBe('coffee');
});

test('without all themes the banner and header text are locked, but a banner can still be removed', function () {
    Storage::fake('uploads');
    [$owner, $shop] = themeOwner(['banner_path' => 'banners/old.jpg']);
    Storage::disk('uploads')->put('banners/old.jpg', 'x');

    $this->actingAs($owner)->post('/dashboard/theme/banner', ['banner' => UploadedFile::fake()->image('b.jpg', 1600, 600)])
        ->assertSessionHasErrors('banner');
    $this->actingAs($owner)->put('/dashboard/theme/header', ['text_color' => '#000000', 'tint' => 20, 'shadow' => false])
        ->assertSessionHasErrors('header');

    $this->actingAs($owner)->delete('/dashboard/theme/banner');
    expect($shop->fresh()->banner_path)->toBeNull();
});

test('a paid-up shop with all themes can upload a banner', function () {
    Storage::fake('uploads');
    [$owner, $shop] = themeOwner(['plan_id' => businessPlan()->id, 'subscription_ends_at' => now()->addYear()]);

    $this->actingAs($owner)->post('/dashboard/theme/banner', ['banner' => UploadedFile::fake()->image('b.jpg', 1600, 600)])
        ->assertSessionHasNoErrors();
    expect($shop->fresh()->banner_path)->not->toBeNull();
});

test('the admin picks the free themes, and the default look is always free', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->put('/admin/settings/free-themes', ['themes' => ['bold-local']])->assertSessionHasNoErrors();

    expect(Setting::get(Setting::FREE_THEMES))->toBe([ThemeCatalog::DEFAULT, 'bold-local'])
        ->and(ThemeCatalog::freeThemes())->toBe([ThemeCatalog::DEFAULT, 'bold-local']);

    $this->actingAs($admin)->put('/admin/settings/free-themes', ['themes' => ['not-a-theme']])->assertSessionHasErrors('themes.0');
});

test('owners cannot change the free themes', function () {
    [$owner] = themeOwner();

    $this->actingAs($owner)->put('/admin/settings/free-themes', ['themes' => ['bold-local']])->assertForbidden();
});

test('the stamp icon keys match resources/js/lib/stampIcons.jsx', function () {
    preg_match_all("/\{ key: '([a-z-]+)'/", file_get_contents(resource_path('js/lib/stampIcons.jsx')), $m);

    expect($m[1])->toEqualCanonicalizing(StampIcons::KEYS)
        ->and(array_diff(StampIcons::FREE, StampIcons::KEYS))->toBe([]);
});
