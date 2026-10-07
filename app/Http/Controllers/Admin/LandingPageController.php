<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLandingPageRequest;
use App\Models\Setting;
use App\Support\Currencies;
use App\Support\LandingPage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Admin → Landing page: the public page at "/" (title, video, pricing per currency). */
class LandingPageController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/LandingPage', [
            'content' => LandingPage::content(),
            'currencyOptions' => Currencies::options(),
            'limits' => [
                'plans' => LandingPage::MAX_PLANS,
                'features' => LandingPage::MAX_FEATURES,
                'currencies' => LandingPage::MAX_CURRENCIES,
            ],
            'ipLookup' => filled(config('services.ipinfo.token')),
        ]);
    }

    public function update(UpdateLandingPageRequest $request): RedirectResponse
    {
        Setting::set(Setting::LANDING_PAGE, $request->content());

        return back()->with('status', 'Landing page saved.');
    }
}
