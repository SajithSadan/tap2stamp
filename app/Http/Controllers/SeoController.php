<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\LegalPages;
use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml, built here (not static files) so they point at
 * this install's own URL and so only production is ever indexed.
 */
class SeoController extends Controller
{
    /**
     * Signed-in areas and machine endpoints are never worth crawling. Sign-in
     * / sign-up and card pages are left crawlable on purpose: they answer
     * `X-Robots-Tag: noindex` (SecurityHeaders), which Google only sees if it
     * may fetch them - blocking them here would let a linked URL be listed.
     */
    private const PRIVATE_PATHS = [
        '/admin', '/dashboard', '/staff', '/onboarding', '/my-cards', '/u/',
        '/api/', '/deploy/', '/auth/', '/address-lookup', '/qr/',
    ];

    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', ...array_map(fn ($path) => "Disallow: {$path}", self::PRIVATE_PATHS), '', 'Sitemap: '.self::base().'sitemap.xml']
            // Staging / local copies must never compete with the real site.
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $updated = Setting::where('key', Setting::LANDING_PAGE)->value('updated_at');
        $pages = [
            // The landing page only: every other page of the app is noindex.
            ['loc' => self::base(), 'lastmod' => $updated ? substr((string) $updated, 0, 10) : null, 'priority' => '1.0'],
            // The legal pages and Contact (trust signals; also what payment providers check).
            ...collect(LegalPages::PAGES)->keys()->map(fn ($slug) => ['loc' => self::base().$slug, 'lastmod' => LegalPages::updatedAt($slug)->format('Y-m-d'), 'priority' => '0.3'])->all(),
            ['loc' => self::base().'contact', 'lastmod' => null, 'priority' => '0.3'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($pages as $page) {
            $xml .= '  <url><loc>'.e($page['loc']).'</loc>'
                .($page['lastmod'] ? '<lastmod>'.$page['lastmod'].'</lastmod>' : '')
                .'<priority>'.$page['priority'].'</priority></url>'."\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** APP_URL with a trailing slash - the same address as the landing page's canonical link. */
    private static function base(): string
    {
        return rtrim((string) config('app.url'), '/').'/';
    }
}
