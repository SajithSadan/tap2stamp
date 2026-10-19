<?php

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Support\LegalPages;

test('every legal page is public, with its title and date', function (string $slug, string $title) {
    $this->get("/{$slug}")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Legal')
        ->where('page', $slug)
        ->where('title', $title)
        ->where('updated', '19 October 2026')
        ->where('html', fn ($html) => str_contains($html, '<h2>') && ! str_contains($html, '{')));
})->with(collect(LegalPages::PAGES)->map(fn ($title, $slug) => [$slug, $title])->values()->all());

test('the refund policy says what the business offers', function () {
    $html = strip_tags(LegalPages::html('refunds'));

    expect($html)
        ->toContain('free trial of 30 days')
        ->toContain('within 14 days of the payment date')
        ->toContain('full refund')
        ->toContain('cancel your subscription at any time')
        ->toContain('is not refunded')
        ->toContain('within 14 days of receiving your order');
});

test('company details fill in every page; without them the pages still read well', function () {
    expect(strip_tags(LegalPages::html('terms')))->toContain('Who provides Tada Tap: Tada Tap.')
        // The support email and phones are built in; only the company itself is missing.
        ->and(LegalPages::missingDetails())->toBe(['company_name', 'registered_address']);

    Setting::set(Setting::LEGAL_DETAILS, [
        'company_name' => 'Techsa Solutions Ltd', 'company_number' => '12345678',
        'registered_address' => "1 High Street\nLeeds LS1 4AP", 'email' => 'hello@tadatap.test', 'phones' => null, 'governing_law' => null,
    ]);

    $terms = LegalPages::html('terms');
    expect(strip_tags($terms))
        ->toContain('Who provides Tada Tap: Techsa Solutions Ltd. Company number 12345678. Registered address: 1 High Street, Leeds LS1 4AP.')
        ->toContain('laws of England and Wales')
        ->and($terms)->toContain('mailto:hello@tadatap.test')
        ->and(LegalPages::missingDetails())->toBe([]);

    $this->get('/contact')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Contact')
        ->where('company', 'Techsa Solutions Ltd')
        ->where('email', 'hello@tadatap.test'));
});

test('the admin edits a page, and can reset it; raw HTML is never rendered', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->put('/admin/settings/legal/refunds', [
        'markdown' => "## Our refunds\n\nWrite to {email}.\n\n<script>alert(1)</script>\n\n[bad](javascript:alert(1))",
    ])->assertSessionHasNoErrors();

    $html = LegalPages::html('refunds');
    expect($html)->toContain('<h2>Our refunds</h2>')
        ->not->toContain('<script>')
        ->not->toContain('javascript:')
        ->and(LegalPages::isCustomised('refunds'))->toBeTrue()
        ->and(LegalPages::updatedAt('refunds')->isToday())->toBeTrue();

    $this->actingAs($admin)->delete('/admin/settings/legal/refunds')->assertSessionHasNoErrors();
    expect(LegalPages::isCustomised('refunds'))->toBeFalse()
        ->and(LegalPages::markdown('refunds'))->toBe(LegalPages::default('refunds'));

    $this->actingAs($admin)->put('/admin/settings/legal/nonsense', ['markdown' => 'x'])->assertNotFound();
});

test('the admin settings page carries the legal details and pages', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/admin/settings')->assertInertia(fn ($page) => $page
        ->where('legal.details.governing_law', 'England and Wales')
        ->has('legal.pages', 4)
        ->where('legal.pages.0.slug', 'privacy')
        ->where('legal.pages.0.customised', false));
});

test('only the admin can change the legal pages', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->put('/admin/settings/legal', ['company_name' => 'Hijack'])->assertForbidden();
    $this->actingAs($owner)->put('/admin/settings/legal/terms', ['markdown' => 'Hijack'])->assertForbidden();

    expect(LegalPages::isCustomised('terms'))->toBeFalse();
});

test('the legal pages are indexable in production, in the sitemap, and linked on every page', function () {
    app()->instance('env', 'production');
    config(['app.url' => 'https://tadatap.app']);

    $this->get('/privacy')->assertHeaderMissing('X-Robots-Tag');
    $this->get('/contact')->assertHeaderMissing('X-Robots-Tag');
    $this->get('/sitemap.xml')->assertSee('<loc>https://tadatap.app/refunds</loc>', false)->assertSee('<loc>https://tadatap.app/contact</loc>', false);

    $this->get('/login')->assertInertia(fn ($page) => $page
        ->where('legalLinks.0.href', '/privacy')
        ->where('legalLinks.4.href', '/contact'));
});

test('the contact page shows the support email and the UK and India numbers out of the box', function () {
    $this->get('/contact')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Contact')
        ->where('email', 'support@tadatap.app')
        ->where('phones.0', ['label' => 'UK', 'number' => '+44 793 204 5061', 'tel' => '+447932045061', 'whatsapp' => 'https://wa.me/447932045061'])
        ->where('phones.1.label', 'India')
        ->where('phones.1.tel', '+919645522802'));

    // And on every legal page's contact section.
    expect(LegalPages::html('refunds'))
        ->toContain('mailto:support@tadatap.app')
        ->toContain('UK: <a href="tel:+447932045061">+44 793 204 5061</a>')
        ->toContain('India: <a href="tel:+919645522802">');
});

test('the admin can change the numbers, one "Label: number" per line', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->put('/admin/settings/legal', ['phones' => "Sales: +44 20 7946 0000\n\n+91 98765 43210", 'email' => 'help@tadatap.app'])
        ->assertSessionHasNoErrors();

    expect(LegalPages::phones())->toBe([
        ['label' => 'Sales', 'number' => '+44 20 7946 0000', 'tel' => '+442079460000', 'whatsapp' => 'https://wa.me/442079460000'],
        ['label' => null, 'number' => '+91 98765 43210', 'tel' => '+919876543210', 'whatsapp' => 'https://wa.me/919876543210'],
    ])->and(LegalPages::details()['email'])->toBe('help@tadatap.app');
});

test('the support chat button opens the UK WhatsApp number (else the first one)', function () {
    $this->get('/register')->assertInertia(fn ($page) => $page->where('supportWhatsapp', 'https://wa.me/447932045061'));

    Setting::set(Setting::LEGAL_DETAILS, ['phones' => "India: +91 96455 22802\nUK: +44 20 7946 0000"]);
    expect(LegalPages::supportWhatsapp())->toBe('https://wa.me/442079460000');

    Setting::set(Setting::LEGAL_DETAILS, ['phones' => 'India: +91 96455 22802']);
    expect(LegalPages::supportWhatsapp())->toBe('https://wa.me/919645522802');
});
