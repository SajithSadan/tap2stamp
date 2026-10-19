<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The public legal pages (/privacy, /terms, /refunds, /shipping) and the
 * company details on them and on /contact.
 *
 * Each page is Markdown: the admin's own text (Admin → Settings → Legal,
 * Setting::LEGAL_PAGES = {page: {markdown, updated_at}}), else the default in
 * resources/legal/{page}.md. Placeholders ({company}, {email}, {trial_days}…)
 * are filled in from Setting::LEGAL_DETAILS and config when the page is
 * shown, so changing a detail updates every page. Rendered with
 * Str::markdown(), raw HTML stripped and unsafe links dropped.
 */
class LegalPages
{
    /** slug => title (each slug is also its URL: /privacy …). */
    public const PAGES = [
        'privacy' => 'Privacy Policy',
        'terms' => 'Terms of Service',
        'refunds' => 'Refund & Cancellation Policy',
        'shipping' => 'Delivery Policy',
    ];

    /** Days to ask for a refund (subscriptions from payment, products from delivery). */
    public const REFUND_DAYS = 14;

    /** "Last updated" for a page still on its default text. */
    public const DEFAULT_UPDATED = '2026-10-19';

    /** The site's name on the pages (the default text says it outright; {website} still works in custom text). */
    public const WEBSITE = 'tadatap.app';

    public const DETAIL_FIELDS = ['company_name', 'company_number', 'registered_address', 'email', 'phones', 'governing_law'];

    /** Shown until the admin sets their own (Admin → Settings → Legal), so a fresh install has them. */
    public const DEFAULTS = [
        'email' => 'support@tadatap.app',
        // One per line, "Label: number".
        'phones' => "UK: +44 793 204 5061\nIndia: +91 96455 22802",
        'governing_law' => 'England and Wales',
    ];

    /** @return array<string, ?string> */
    public static function details(): array
    {
        $saved = Setting::get(Setting::LEGAL_DETAILS) ?? [];

        return [
            ...array_fill_keys(self::DETAIL_FIELDS, null),
            ...self::DEFAULTS,
            ...array_filter(array_intersect_key($saved, array_flip(self::DETAIL_FIELDS)), fn ($v) => filled($v)),
        ];
    }

    /**
     * The contact numbers: [{label, number, tel, whatsapp}] from the "Label: number" lines
     * (a line without a label is just the number).
     *
     * @return list<array{label: ?string, number: string, tel: string, whatsapp: string}>
     */
    public static function phones(): array
    {
        return collect(preg_split('/\R/', (string) self::details()['phones']))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->map(function (string $line) {
                [$label, $number] = str_contains($line, ':') ? array_map('trim', explode(':', $line, 2)) : [null, $line];
                $digits = preg_replace('/\D/', '', $number);

                return ['label' => $label ?: null, 'number' => $number, 'tel' => '+'.$digits, 'whatsapp' => "https://wa.me/{$digits}"];
            })
            ->filter(fn ($phone) => strlen($phone['tel']) > 5)
            ->values()
            ->all();
    }

    /** Details still missing that the pages need (shown to the admin). @return list<string> */
    public static function missingDetails(): array
    {
        $details = self::details();

        return array_values(array_filter(['company_name', 'registered_address', 'email'], fn ($field) => blank($details[$field])));
    }

    /** The page's Markdown with placeholders, as the admin edits it. */
    public static function markdown(string $page): string
    {
        return self::saved($page)['markdown'] ?? self::default($page);
    }

    public static function default(string $page): string
    {
        return (string) file_get_contents(resource_path("legal/{$page}.md"));
    }

    public static function isCustomised(string $page): bool
    {
        return self::saved($page) !== null;
    }

    public static function updatedAt(string $page): Carbon
    {
        $at = self::saved($page)['updated_at'] ?? self::DEFAULT_UPDATED;

        return Carbon::parse($at)->timezone('Europe/London');
    }

    /** The page as safe HTML, placeholders filled in. */
    public static function html(string $page): string
    {
        return Str::markdown(self::fill(self::markdown($page)), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /** Placeholder => text. A missing detail never leaves a gap or a "{company}". */
    public static function placeholders(): array
    {
        $d = self::details();
        $company = $d['company_name'] ?: 'Tada Tap';
        $email = $d['email'] ? "[{$d['email']}](mailto:{$d['email']})" : 'via our [Contact page](/contact)';

        $companyDetails = collect([
            $d['company_number'] ? "Company number {$d['company_number']}." : null,
            $d['registered_address'] ? 'Registered address: '.self::oneLine($d['registered_address']).'.' : null,
        ])->filter()->implode(' ');

        $contact = collect([
            "**{$company}**",
            $d['registered_address'] ? self::oneLine($d['registered_address']) : null,
            $d['email'] ? "Email: [{$d['email']}](mailto:{$d['email']})" : null,
            ...array_map(fn ($p) => ($p['label'] ? "{$p['label']}: " : 'Phone: ')."[{$p['number']}](tel:{$p['tel']})", self::phones()),
            'Or use our [Contact page](/contact).',
        ])->filter()->implode("  \n");

        return [
            '{company}' => $company,
            '{company_details}' => $companyDetails ? ' '.$companyDetails : '',
            '{email}' => $email,
            '{law}' => $d['governing_law'] ?: 'England and Wales',
            // Fixed, not APP_URL: the pages always name the site the same way.
            '{website}' => self::WEBSITE,
            '{trial_days}' => (string) config('loyalty.trial_days', 30),
            '{refund_days}' => (string) self::REFUND_DAYS,
            '{contact_block}' => $contact,
        ];
    }

    public static function fill(string $markdown): string
    {
        return strtr($markdown, self::placeholders());
    }

    /** Footer / nav links: [{slug, title, href}], plus Contact. */
    public static function links(): array
    {
        return [
            ...collect(self::PAGES)->map(fn ($title, $slug) => ['slug' => $slug, 'title' => $title, 'href' => "/{$slug}"])->values()->all(),
            ['slug' => 'contact', 'title' => 'Contact', 'href' => '/contact'],
        ];
    }

    /** wa.me link for the support chat button: the "UK" number, else the first one. */
    public static function supportWhatsapp(): ?string
    {
        $phones = collect(self::phones());
        $phone = $phones->first(fn ($p) => strcasecmp((string) $p['label'], 'UK') === 0) ?? $phones->first();

        return $phone['whatsapp'] ?? null;
    }

    private static function saved(string $page): ?array
    {
        $pages = Setting::get(Setting::LEGAL_PAGES) ?? [];

        return isset($pages[$page]['markdown']) ? $pages[$page] : null;
    }

    private static function oneLine(string $text): string
    {
        return trim(preg_replace('/\s*\R\s*/', ', ', trim($text)), ', ');
    }
}
