<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PosCustomer;
use App\Services\ActivityLogger;
use App\Support\Countries;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → POS customers: restaurants on the billing desktop app. The grid
 * never carries passwords; the admin asks for one at a time (password()),
 * and every look is in the activity log.
 */
class PosCustomerController extends Controller
{
    public function index(): Response
    {
        $day = fn ($at) => $at?->timezone('Europe/London')->format('j M Y, H:i');

        return Inertia::render('Admin/PosCustomers', [
            'customers' => PosCustomer::latest()->get()->map(fn (PosCustomer $c) => [
                'id' => $c->id,
                'restaurant_name' => $c->restaurant_name,
                'contact_name' => $c->contact_name,
                'email' => $c->email,
                'phone' => $c->phone,
                'address' => collect([$c->address_line1, $c->address_line2, $c->town, $c->state, $c->postcode, Countries::name($c->country)])->filter()->implode(', '),
                'username' => $c->username,
                'signed_in' => $c->api_token_hash !== null,
                'password_changed_label' => $day($c->password_changed_at),
                'last_login_at' => $c->last_login_at?->toIso8601String(),
                'last_login_label' => $day($c->last_login_at),
                'created_at' => $c->created_at->toIso8601String(),
                'created_label' => $day($c->created_at),
            ]),
        ]);
    }

    /** One customer's password, decrypted, for the admin only. Logged. */
    public function password(PosCustomer $posCustomer): JsonResponse
    {
        try {
            $password = $posCustomer->password;
        } catch (DecryptException) {
            return response()->json(['message' => "This password can't be read (APP_KEY changed)."], 422);
        }

        ActivityLogger::record('pos.password_viewed', "Viewed the billing app password of {$posCustomer->restaurant_name} ({$posCustomer->username})", null, $posCustomer);

        return response()->json(['password' => $password]);
    }
}
