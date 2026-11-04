<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePosCustomerRequest;
use App\Models\PosCustomer;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The billing desktop app's account API (/api/pos): onboard a restaurant,
 * sign in, read / edit its details, change its password. Every change is in
 * the activity log as the restaurant (actor `pos`) - the password itself
 * never is.
 */
class PosCustomerController extends Controller
{
    public function register(SavePosCustomerRequest $request): JsonResponse
    {
        [$customer, $token] = DB::transaction(function () use ($request) {
            $customer = PosCustomer::create([
                ...$request->details(),
                'username' => $request->input('username'),
                'password' => $request->input('password'),
                'password_changed_at' => now(),
            ]);

            return [$customer, $customer->issueToken()];
        });

        ActivityLogger::record('pos.registered', "{$customer->restaurant_name} signed up for the billing app", null, $customer, null, ActivityLogger::posCustomer($customer));

        return response()->json(['token' => $token, 'customer' => $customer->toApi()], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $input = $request->validate([
            'username' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        $customer = PosCustomer::where('username', PosCustomer::normaliseUsername($input['username']))->first();
        if (! $customer || ! $customer->passwordMatches($input['password'])) {
            throw ValidationException::withMessages(['username' => 'Wrong username or password.']);
        }

        $token = $customer->issueToken();
        $customer->forceFill(['last_login_at' => now()])->save();
        ActivityLogger::record('pos.signed_in', "{$customer->restaurant_name} signed in to the billing app", null, $customer, null, ActivityLogger::posCustomer($customer));

        return response()->json(['token' => $token, 'customer' => $customer->toApi()]);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['customer' => $this->customer($request)->toApi()]);
    }

    public function update(SavePosCustomerRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
        $customer->fill($request->details());
        $changes = collect($customer->getDirty())->mapWithKeys(fn ($value, $field) => [$field => [$customer->getOriginal($field), $value]])->all();
        $customer->save();

        if ($changes) {
            ActivityLogger::record('pos.updated', "{$customer->restaurant_name} updated its details", null, $customer, $changes, ActivityLogger::posCustomer($customer));
        }

        return response()->json(['customer' => $customer->toApi()]);
    }

    /** Needs the current password. Logged with when and from where - never the passwords. */
    public function changePassword(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $input = $request->validate([
            'current_password' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', 'min:6', 'max:128', 'confirmed', 'different:current_password'],
        ]);

        if (! $customer->passwordMatches($input['current_password'])) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is wrong.']);
        }

        $customer->update(['password' => $input['password'], 'password_changed_at' => now()]);
        // Same as every secret field in the log: that it changed, never the value.
        ActivityLogger::record('pos.password_changed', "{$customer->restaurant_name} changed its billing app password (from {$request->ip()})", null, $customer, [
            'password' => ['•••', 'changed'],
        ], ActivityLogger::posCustomer($customer));

        return response()->json(['customer' => $customer->toApi()]);
    }

    /** Signs this app out (the token stops working). */
    public function logout(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $customer->forceFill(['api_token_hash' => null])->save();
        ActivityLogger::record('pos.signed_out', "{$customer->restaurant_name} signed out of the billing app", null, $customer, null, ActivityLogger::posCustomer($customer));

        return response()->json(['status' => 'ok']);
    }

    private function customer(Request $request): PosCustomer
    {
        return $request->attributes->get('posCustomer');
    }
}
