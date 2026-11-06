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
 * sign in, read / edit its details, change its password, activate a PC
 * with its one-time activation key. Every change is in the activity log as
 * the restaurant (actor `pos`) - the password and the key themselves never
 * are.
 *
 * The app sends its PC's id in the X-POS-Device header; /me answers whether
 * the app is (still) activated on that PC.
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

            // Not in the response: we read it out to the customer (Admin → POS customers).
            $customer->issueActivationKey();

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
        return response()->json($this->withDevice($request, $this->customer($request)));
    }

    /**
     * Unlocks the billing app on one PC with the activation key we gave the
     * customer. The key works once: after that only on the PC it activated
     * (reinstalling there), never on another. Wrong keys count; at
     * MAX_ACTIVATION_ATTEMPTS the key is locked until the admin makes a new one.
     */
    public function activate(Request $request): JsonResponse
    {
        $input = $request->validate([
            'activation_key' => ['required', 'string', 'max:32'],
            'device_id' => ['required', 'string', 'min:16', 'max:128'],
        ]);
        $key = PosCustomer::normaliseActivationKey($input['activation_key']);
        $device = $input['device_id'];

        // Locked row: two PCs trying the same key at once can't both get it.
        // Results, not exceptions, so a wrong try's count is kept (not rolled back).
        [$customer, $outcome] = DB::transaction(function () use ($request, $key, $device) {
            $customer = PosCustomer::whereKey($this->customer($request)->id)->lockForUpdate()->firstOrFail();

            if ($customer->activation_key_used_at && $customer->isActivatedOn($device) && $customer->activationKeyMatches($key)) {
                return [$customer, 'again'];
            }

            $state = $customer->activationKeyState();
            if (in_array($state, ['none', 'locked'], true)) {
                return [$customer, $state];
            }
            if (! $customer->activationKeyMatches($key)) {
                $customer->increment('activation_attempts');

                return [$customer, $customer->activation_attempts >= PosCustomer::MAX_ACTIVATION_ATTEMPTS ? 'locked' : 'wrong'];
            }
            if ($state !== 'active') {
                return [$customer, $state]; // used (on another PC) or expired
            }

            $customer->forceFill([
                'activation_key_used_at' => now(),
                'activation_attempts' => 0,
                'activated_device_id' => $device,
                'activated_at' => now(),
            ])->save();

            return [$customer, 'activated'];
        });

        if (in_array($outcome, ['activated', 'again'], true)) {
            if ($outcome === 'activated') {
                ActivityLogger::record('pos.activated', "{$customer->restaurant_name} activated the billing app on a PC (from {$request->ip()})", null, $customer, null, ActivityLogger::posCustomer($customer));
            }

            return response()->json(['activated' => true, 'activated_at' => $customer->activated_at?->toIso8601String(), ...$this->withDevice($request, $customer, $device)]);
        }

        $left = PosCustomer::MAX_ACTIVATION_ATTEMPTS - $customer->activation_attempts;
        $message = match ($outcome) {
            'none' => 'There is no activation key for this restaurant yet. Contact us to get one.',
            'locked' => 'Too many wrong activation keys. Contact us for a new key.',
            'wrong' => "That activation key is wrong. {$left} ".($left === 1 ? 'try' : 'tries').' left.',
            'used' => 'This activation key has already been used. Contact us for a new key.',
            'expired' => 'This activation key has expired. Contact us for a new key.',
        };
        ActivityLogger::record('pos.activation_failed', "{$customer->restaurant_name}: activation refused ({$outcome}, from {$request->ip()})", null, $customer, null, ActivityLogger::posCustomer($customer));

        return response()->json([
            'message' => $message,
            'code' => $outcome,
            'errors' => ['activation_key' => [$message]],
        ], 422);
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

    /**
     * The customer, and whether the app is activated on the PC asking
     * (X-POS-Device). false once the key was used on another PC.
     *
     * @return array<string, mixed>
     */
    private function withDevice(Request $request, PosCustomer $customer, ?string $device = null): array
    {
        return [
            'customer' => $customer->toApi(),
            'device_activated' => $customer->isActivatedOn($device ?? $request->header('X-POS-Device')),
        ];
    }

    private function customer(Request $request): PosCustomer
    {
        return $request->attributes->get('posCustomer');
    }
}
