<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\LegalPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Admin → Settings → Legal: the company details on the legal pages, and each page's text. */
class LegalPageController extends Controller
{
    public function updateDetails(Request $request): RedirectResponse
    {
        $details = $request->validate([
            'company_name' => ['nullable', 'string', 'max:150'],
            'company_number' => ['nullable', 'string', 'max:50'],
            'registered_address' => ['nullable', 'string', 'max:500'],
            'email' => ['nullable', 'email', 'max:150'],
            // One per line, "Label: number" (e.g. "UK: +44 7932 045061").
            'phones' => ['nullable', 'string', 'max:300'],
            'governing_law' => ['nullable', 'string', 'max:100'],
        ]);

        Setting::set(Setting::LEGAL_DETAILS, array_map(fn ($v) => filled($v) ? trim($v) : null, $details));

        return back()->with('status', 'Company details saved - every legal page shows them now.');
    }

    public function updatePage(Request $request, string $page): RedirectResponse
    {
        abort_unless(isset(LegalPages::PAGES[$page]), 404);
        $markdown = $request->validate(['markdown' => ['required', 'string', 'max:60000']])['markdown'];

        $pages = Setting::get(Setting::LEGAL_PAGES) ?? [];
        $pages[$page] = ['markdown' => str_replace("\r\n", "\n", $markdown), 'updated_at' => now()->toIso8601String()];
        Setting::set(Setting::LEGAL_PAGES, $pages);

        return back()->with('status', LegalPages::PAGES[$page].' saved.');
    }

    /** Back to the built-in text (and its date). */
    public function resetPage(string $page): RedirectResponse
    {
        abort_unless(isset(LegalPages::PAGES[$page]), 404);

        $pages = Setting::get(Setting::LEGAL_PAGES) ?? [];
        unset($pages[$page]);
        Setting::set(Setting::LEGAL_PAGES, $pages);

        return back()->with('status', LegalPages::PAGES[$page].' is back to the default text.');
    }
}
