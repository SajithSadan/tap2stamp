<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLandingPageRequest;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\SubscriptionPayment;
use App\Services\PlanList;
use App\Services\RazorpayGateway;
use App\Services\StripeGateway;
use App\Support\Countries;
use App\Support\Currencies;
use App\Support\LandingPage;
use App\Support\PaymentProviders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Landing page: the public page at "/" (title, video, pricing per
 * currency). Its pricing plans are the plans owners pay for, so billing is
 * set here too: the default plan + free trial, which plans are offered and the
 * payment countries.
 */
class LandingPageController extends Controller
{
    public function edit(StripeGateway $stripe, RazorpayGateway $razorpay): Response
    {
        $shopCounts = Shop::selectRaw('plan_id, count(*) as total')->groupBy('plan_id')->pluck('total', 'plan_id');

        return Inertia::render('Admin/LandingPage', [
            'content' => LandingPage::content(),
            // For "Load default content": the suggested copy and plans, loaded into the form (saved only on Save).
            'defaults' => LandingPage::defaults(),
            'currencyOptions' => Currencies::options(),
            'limits' => [
                'plans' => LandingPage::MAX_PLANS,
                'features' => LandingPage::MAX_FEATURES,
                'trustPoints' => LandingPage::MAX_TRUST_POINTS,
                'faqs' => LandingPage::MAX_FAQS,
            ],
            'ipLookup' => filled(config('services.ipinfo.token')),
            // {plan id: shops on it}: removing a plan moves them to the default.
            'planShops' => $shopCounts->only(Plan::pluck('id'))->map(fn ($n) => (int) $n),
            // Payments: which countries use Stripe (the rest Razorpay), and whether each has its keys.
            'payments' => [
                'stripe_countries' => PaymentProviders::stripeCountries(),
                'stripe_ready' => $stripe->elementsConfigured(),
                'razorpay_ready' => $razorpay->configured(),
                'countries' => Countries::options(),
            ],
            // Latest plan payments, all shops.
            'recentPayments' => SubscriptionPayment::with('shop:id,name')->where('status', SubscriptionPayment::PAID)
                ->latest('paid_at')->limit(20)->get()->map(fn (SubscriptionPayment $p) => [
                    'id' => $p->id,
                    'shop_id' => $p->shop_id,
                    'shop' => $p->shop?->name,
                    'plan' => $p->plan_name,
                    'amount' => $p->amountLabel(),
                    'provider' => $p->provider,
                    'paid_label' => $p->paid_at->timezone('Europe/London')->format('j M Y'),
                    'period_label' => $p->period_starts_at?->timezone('Europe/London')->format('j M Y').' - '.$p->period_ends_at?->timezone('Europe/London')->format('j M Y'),
                ]),
        ]);
    }

    public function update(UpdateLandingPageRequest $request, PlanList $plans): RedirectResponse
    {
        DB::transaction(function () use ($request, $plans) {
            Setting::set(Setting::LANDING_PAGE, $request->content());
            $plans->replace($request->plans());
        });

        return back()->with('status', 'Landing page saved.');
    }
}
