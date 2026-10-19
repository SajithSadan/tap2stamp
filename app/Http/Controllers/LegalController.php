<?php

namespace App\Http\Controllers;

use App\Support\LegalPages;
use Inertia\Inertia;
use Inertia\Response;

/** The public legal pages and Contact (App\Support\LegalPages). */
class LegalController extends Controller
{
    public function show(string $page): Response
    {
        abort_unless(isset(LegalPages::PAGES[$page]), 404);

        return Inertia::render('Legal', [
            'page' => $page,
            'title' => LegalPages::PAGES[$page],
            'html' => LegalPages::html($page),
            'updated' => LegalPages::updatedAt($page)->format('j F Y'),
            'links' => LegalPages::links(),
        ])->withViewData(['seo' => [
            'title' => LegalPages::PAGES[$page].' | Tada Tap',
            'description' => LegalPages::PAGES[$page].' for Tada Tap, the digital loyalty card app for small businesses.',
            'canonical' => rtrim((string) config('app.url'), '/')."/{$page}",
        ]]);
    }

    public function contact(): Response
    {
        $d = LegalPages::details();

        return Inertia::render('Contact', [
            'company' => $d['company_name'] ?: 'Tada Tap',
            'companyNumber' => $d['company_number'],
            'address' => $d['registered_address'],
            'email' => $d['email'],
            'phones' => LegalPages::phones(),
            'links' => LegalPages::links(),
        ])->withViewData(['seo' => [
            'title' => 'Contact | Tada Tap',
            'description' => 'Get in touch with Tada Tap - support, billing, refunds and orders.',
            'canonical' => rtrim((string) config('app.url'), '/').'/contact',
        ]]);
    }
}
