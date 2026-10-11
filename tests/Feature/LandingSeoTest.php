<?php

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use App\Support\LandingPage;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::fake());

/** The landing page's saved content, with some fields changed. */
function seoLanding(array $overrides): void
{
    Setting::set(Setting::LANDING_PAGE, [...LandingPage::defaults(), ...$overrides]);
}

/** The JSON-LD graph written into the page, decoded. */
function jsonLd(string $html): array
{
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);

    return json_decode($m[1] ?? 'null', true)['@graph'] ?? [];
}

test('the landing page HTML carries the title, description, canonical and social tags', function () {
    config(['app.url' => 'https://tadatap.app']);
    $defaults = LandingPage::defaults();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('<title inertia>'.e($defaults['seo_title']).'</title>')
        ->toContain('<meta name="description" content="'.e($defaults['seo_description']).'">')
        ->toContain('<link rel="canonical" href="https://tadatap.app/">')
        // One site for every country (UK shops sign up here too): nothing points elsewhere.
        ->not->toContain('hreflang')
        ->not->toContain('tadatap.co.uk')
        ->toContain('<meta property="og:image" content="https://tadatap.app/images/og-image.jpg">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">')
        // Worldwide: no price in the title or description.
        ->not->toMatch('/<title[^>]*>[^<]*[£$₹]/');

    expect(public_path(LandingPage::OG_IMAGE))->toBeFile();
});

test('the page sends Google an FAQPage built from the visible questions', function () {
    $this->get('/')->assertInertia(fn ($page) => $page
        ->component('Landing')
        ->where('faqs.0.question', 'What is a digital loyalty card?'));

    $graph = collect(jsonLd($this->get('/')->getContent()));
    $faq = $graph->firstWhere('@type', 'FAQPage');

    expect($graph->pluck('@type')->all())->toContain('Organization', 'WebSite', 'SoftwareApplication', 'FAQPage')
        ->and($faq['mainEntity'])->toHaveCount(count(LandingPage::defaults()['faqs']))
        ->and($faq['mainEntity'][0]['name'])->toBe('What is a digital loyalty card?')
        ->and($faq['mainEntity'][0]['acceptedAnswer']['@type'])->toBe('Answer');
});

test('no FAQs means no FAQPage, and an empty SEO title falls back to the heading', function () {
    seoLanding(['faqs' => [], 'seo_title' => null, 'seo_description' => null, 'title' => 'Loyalty for everyone']);

    $html = $this->get('/')->getContent();

    expect(collect(jsonLd($html))->pluck('@type')->all())->not->toContain('FAQPage')
        ->and($html)->toContain('<title inertia>Loyalty for everyone</title>');
});

test('admin-typed text cannot break out of the structured data script', function () {
    seoLanding(['faqs' => [['question' => 'Is it safe?', 'answer' => '</script><script>alert(1)</script>']]]);

    $html = $this->get('/')->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and(collect(jsonLd($html))->firstWhere('@type', 'FAQPage')['mainEntity'][0]['acceptedAnswer']['text'])
        ->toBe('</script><script>alert(1)</script>');
});

test('other pages carry no landing page SEO tags', function () {
    expect($this->get('/login')->getContent())->not->toContain('og:image')->not->toContain('application/ld+json');
});

test('the admin saves the SEO title, description and FAQ; empty rows are dropped', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $form = [
        ...collect(LandingPage::defaults())->except('plans')->all(),
        'plans' => collect(LandingPage::defaults()['plans'])->map(fn ($p) => $p)->all(),
        'seo_title' => '  Loyalty cards for any shop | Tada Tap ',
        'seo_description' => 'Stamp cards on the phone.',
        'faq_title' => 'Questions',
        'faqs' => [
            ['question' => ' Is it free? ', 'answer' => ' Yes, to start. '],
            ['question' => '', 'answer' => ''],
        ],
    ];

    $this->actingAs($admin)->put('/admin/landing-page', $form)->assertSessionHasNoErrors();

    $saved = LandingPage::content();
    expect($saved['seo_title'])->toBe('Loyalty cards for any shop | Tada Tap')
        ->and($saved['faq_title'])->toBe('Questions')
        ->and($saved['faqs'])->toBe([['question' => 'Is it free?', 'answer' => 'Yes, to start.']]);
});

test('a half-filled FAQ row and an over-long title are refused', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $form = [...LandingPage::defaults(), 'seo_title' => str_repeat('a', 71), 'faqs' => [['question' => 'Only a question', 'answer' => '']]];

    $this->actingAs($admin)->put('/admin/landing-page', $form)->assertSessionHasErrors(['seo_title', 'faqs.0.answer']);
});

test('robots.txt keeps staging out of search engines and points production at the sitemap', function () {
    $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false);

    app()->instance('env', 'production');
    config(['app.url' => 'https://tadatap.app']);

    $this->get('/robots.txt')->assertOk()
        ->assertSee('Disallow: /admin', false)
        ->assertSee('Disallow: /dashboard', false)
        ->assertDontSee("Disallow: /\n", false)
        ->assertSee('Sitemap: https://tadatap.app/sitemap.xml', false);
});

test('the sitemap lists the landing page at its canonical address', function () {
    config(['app.url' => 'https://tadatap.app']);

    $this->get('/sitemap.xml')->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>https://tadatap.app/</loc>', false);
});

test('only the landing page and public menus may be indexed in production', function () {
    app()->instance('env', 'production');
    $shop = Shop::factory()->create();

    $this->get('/')->assertHeaderMissing('X-Robots-Tag');

    foreach (['/login', '/register', '/forgot-password', "/s/{$shop->slug}", '/my-cards', '/robots.txt'] as $path) {
        $this->get($path)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
    // Signed-in areas: the redirect to log in is noindex too.
    $this->get('/dashboard')->assertRedirect()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/admin')->assertRedirect()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('nothing is indexable outside production', function () {
    $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('robots.txt blocks the sign-in plumbing and the sitemap lists only the landing page', function () {
    app()->instance('env', 'production');
    config(['app.url' => 'https://tadatap.app']);

    $this->get('/robots.txt')
        ->assertSee('Disallow: /auth/', false)
        ->assertSee('Disallow: /qr/', false)
        // Left crawlable so Google can see their noindex.
        ->assertDontSee('Disallow: /login', false)
        ->assertDontSee('Disallow: /register', false);

    $this->get('/sitemap.xml')
        ->assertSee('<loc>https://tadatap.app/</loc>', false)
        ->assertDontSee('register', false);
});
