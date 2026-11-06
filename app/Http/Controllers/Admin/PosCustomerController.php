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
 * never carries passwords or activation keys; the admin asks for one at a
 * time (password(), activationKey()), and every look is in the activity log.
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
                'place' => collect([$c->town, $c->state])->filter()->implode(', ') ?: null,
                'country_code' => $c->country,
                'country' => $c->country ? Countries::name($c->country) : null,
                'username' => $c->username,
                'signed_in' => $c->api_token_hash !== null,
                'password_changed_label' => $day($c->password_changed_at),
                'last_login_at' => $c->last_login_at?->toIso8601String(),
                'last_login_label' => $day($c->last_login_at),
                'created_at' => $c->created_at->toIso8601String(),
                'created_label' => $day($c->created_at),
                // Never the key itself: shown one at a time (activationKey()).
                'activation' => $c->activationKeyState(),
                'activation_expires_label' => $day($c->activation_key_expires_at),
                'activated_label' => $day($c->activated_at),
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

    /** The activation key, to read out to the customer. Logged. */
    public function activationKey(PosCustomer $posCustomer): JsonResponse
    {
        try {
            $key = $posCustomer->activation_key;
        } catch (DecryptException) {
            return response()->json(['message' => "This key can't be read (APP_KEY changed). Make a new one."], 422);
        }
        if ($key === null) {
            return response()->json(['message' => 'No key yet. Make a new one.'], 422);
        }

        ActivityLogger::record('pos.activation_key_viewed', "Viewed the activation key of {$posCustomer->restaurant_name} ({$posCustomer->username})", null, $posCustomer);

        return response()->json($this->keyAnswer($posCustomer, $key));
    }

    /**
     * A new activation key (the old one stops working): for a new PC, a
     * reinstall on another PC, or a key that expired or was locked. The PC
     * already activated keeps working until the new key is used on a PC.
     */
    public function newActivationKey(PosCustomer $posCustomer): JsonResponse
    {
        $key = $posCustomer->issueActivationKey();
        ActivityLogger::record('pos.activation_key_issued', "Made a new activation key for {$posCustomer->restaurant_name} ({$posCustomer->username})", null, $posCustomer);

        return response()->json($this->keyAnswer($posCustomer, $key));
    }

    /** @return array<string, mixed> */
    private function keyAnswer(PosCustomer $customer, string $key): array
    {
        return [
            'key' => PosCustomer::formatActivationKey($key),
            'state' => $customer->activationKeyState(),
            'expires_label' => $customer->activation_key_expires_at?->timezone('Europe/London')->format('j M Y, H:i'),
        ];
    }
}
