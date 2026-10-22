<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\ShopPayments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Admin → shop settings → Payments: refund a payment made online, through its provider. */
class ShopPaymentController extends Controller
{
    public function refund(Request $request, Shop $shop, string $kind, int $id, ShopPayments $payments): RedirectResponse
    {
        $input = $request->validate([
            'amount' => ['required', 'regex:/^\d{1,7}(\.\d{1,3})?$/'],
            'reason' => ['nullable', 'string', 'max:200'],
        ], ['amount.regex' => 'Enter the amount as a number, e.g. 4999 or 49.50.']);

        $message = $payments->refund($shop, $kind, $id, $input['amount'], $input['reason'] ?? null, $request->user());

        return back()->with('status', $message);
    }
}
