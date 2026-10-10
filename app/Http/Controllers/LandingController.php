<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\VisitorCountry;
use App\Support\LandingPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "/": the landing page for visitors (title, video, pricing in their own
 * currency); signed-in admins and owners go straight to their home.
 */
class LandingController extends Controller
{
    public function __invoke(Request $request, VisitorCountry $country): Response|RedirectResponse
    {
        // ?preview=1 lets the admin see the page while signed in (Admin → Landing page → Preview).
        $adminPreview = $request->boolean('preview') && $request->user()?->role === UserRole::Admin;

        if ($request->user() && ! $adminPreview) {
            return redirect($request->user()->homeUrl());
        }

        $content = LandingPage::content();
        // ?currency=USD (Admin → Landing page → Preview) only for the admin: the public only
        // ever sees their own country's prices, so nobody can shop around for a cheaper one.
        $currency = LandingPage::currencyFor($content, $country->for($request), $adminPreview ? $request->query('currency') : null);

        return Inertia::render('Landing', LandingPage::forVisitor($content, $currency));
    }
}
