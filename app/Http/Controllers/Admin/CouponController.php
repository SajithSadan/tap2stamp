<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCouponRequest;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Coupon codes owners can use when ordering products. Like products, never
 * deleted (past orders point at them) - switching one off stops it working.
 */
class CouponController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => Coupon::with('product:id,name')
                ->withCount('paidOrders as uses')
                ->latest()
                ->get()
                ->map(fn (Coupon $coupon) => [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'description' => $coupon->description,
                    'label' => $coupon->label(),
                    'discount_type' => $coupon->discount_type,
                    'discount_value' => $coupon->discount_value,
                    'product_id' => $coupon->product_id,
                    'product_name' => $coupon->product?->name,
                    'max_uses' => $coupon->max_uses,
                    'uses' => $coupon->uses,
                    'once_per_shop' => $coupon->once_per_shop,
                    'expires_on' => $coupon->expires_at?->timezone('Europe/London')->format('Y-m-d'),
                    'expires_label' => $coupon->expires_at?->timezone('Europe/London')->format('j M Y'),
                    'is_expired' => $coupon->isExpired(),
                    'is_active' => $coupon->is_active,
                ]),
            'products' => Product::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveCouponRequest $request): RedirectResponse
    {
        $coupon = Coupon::create($request->couponValues());

        return back()->with('status', "Coupon {$coupon->code} added.");
    }

    public function update(SaveCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($request->couponValues());

        return back()->with('status', "Coupon {$coupon->code} saved.");
    }
}
