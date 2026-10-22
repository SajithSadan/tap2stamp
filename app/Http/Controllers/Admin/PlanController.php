<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Setting;
use App\Support\Countries;
use App\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plan billing, beside the plans themselves (edited with the landing page's
 * pricing, Admin\LandingPageController): the owner's Plan page preview by
 * country and the payment countries.
 */
class PlanController extends Controller
{
    /** The owner's Plan page as an owner in ?country= sees it (default India). */
    public function preview(Request $request): Response
    {
        $country = strtoupper($request->string('country')->value());
        $country = in_array($country, Countries::codes(), true) ? $country : 'IN';
        $currency = Currencies::forCountry($country);

        return Inertia::render('Admin/Plans/Preview', [
            'plans' => Plan::active()->ordered()->get()->map(fn (Plan $plan) => $plan->forOwner($currency))->values(),
            'countries' => Countries::options(),
            'country' => $country,
            'currency' => $currency,
        ]);
    }

    /** The countries that pay with Stripe's card form; every other country pays with Razorpay. */
    public function updatePayments(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'stripe_countries' => ['present', 'array', 'max:'.count(Countries::codes())],
            'stripe_countries.*' => ['string', 'distinct', Rule::in(Countries::codes())],
        ]);

        Setting::set(Setting::PAYMENT_COUNTRIES, ['stripe' => array_values($input['stripe_countries'])]);

        return back()->with('status', 'Payment countries saved.');
    }
}
