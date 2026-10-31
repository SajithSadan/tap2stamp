<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePlanCouponRequest;
use App\Models\Plan;
use App\Models\PlanCoupon;
use App\Support\Countries;
use App\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Coupon codes owners can use when paying for a plan - separate from the
 * counter display's coupons. Never deleted (payments point at them),
 * switching one off stops it working.
 */
class PlanCouponController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/PlanCoupons/Index', [
            'coupons' => PlanCoupon::with('plan:id,name')
                ->withCount('paidPayments as uses')
                ->latest()
                ->get()
                ->map(fn (PlanCoupon $coupon) => [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'description' => $coupon->description,
                    'discount_type' => $coupon->discount_type,
                    // [{country, value, label: "10% off" / "₹500 off"}]
                    'discounts' => collect($coupon->discounts)->map(fn ($value, $country) => [
                        'country' => $country,
                        'value' => (string) $value,
                        'label' => $coupon->labelFor($country, Currencies::forCountry($country) ?? 'GBP'),
                    ])->values(),
                    'plan_id' => $coupon->plan_id,
                    'plan_name' => $coupon->plan?->name,
                    'max_uses' => $coupon->max_uses,
                    'uses' => $coupon->uses,
                    'once_per_shop' => $coupon->once_per_shop,
                    'expires_on' => $coupon->expires_at?->timezone('Europe/London')->format('Y-m-d'),
                    'expires_label' => $coupon->expires_at?->timezone('Europe/London')->format('j M Y'),
                    'is_expired' => $coupon->isExpired(),
                    'is_active' => $coupon->is_active,
                ]),
            'plans' => Plan::ordered()->get(['id', 'name']),
            // Each country with the currency its shops pay in (GBP when it has none listed).
            'countries' => collect(Countries::options())->map(fn (array $c) => [
                'code' => $c['code'],
                'name' => $c['name'],
                'currency' => $currency = Currencies::forCountry($c['code']) ?? 'GBP',
                'symbol' => trim(Currencies::symbol($currency)),
            ])->values(),
        ]);
    }

    public function store(SavePlanCouponRequest $request): RedirectResponse
    {
        $coupon = PlanCoupon::create($request->couponValues());

        return back()->with('status', "Plan coupon {$coupon->code} added.");
    }

    public function update(SavePlanCouponRequest $request, PlanCoupon $planCoupon): RedirectResponse
    {
        $planCoupon->update($request->couponValues());

        return back()->with('status', "Plan coupon {$planCoupon->code} saved.");
    }
}
