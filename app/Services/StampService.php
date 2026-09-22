<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Events\CardUpdated;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\StaffDevice;
use App\Models\StampLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StampService
{
    /**
     * @return array{0: int, 1: array<string, mixed>} [HTTP status, JSON body]
     */
    public function scan(StaffDevice $device, string $payload): array
    {
        if (! preg_match('/^TOKEN:([0-9a-fA-F-]{36})\|SHOP:(\d+)$/', $payload, $matches)) {
            return $this->error(422, 'invalid_qr', "That doesn't look like a loyalty card QR code.");
        }

        [, $uuid, $shopId] = $matches;

        // The server trusts nothing from the client beyond the raw payload -
        // the shop in the QR must match the *authenticated device's* shop,
        // never derive authorization from the QR content alone.
        if ((int) $shopId !== $device->shop_id) {
            return $this->error(403, 'shop_mismatch', 'This card belongs to a different business.');
        }

        $customer = Customer::where('uuid', $uuid)->first();

        if (! $customer) {
            return $this->error(404, 'customer_not_found', 'No customer found for this QR code.');
        }

        [$status, $body] = DB::transaction(function () use ($customer, $device) {
            // lockForUpdate() inside the transaction: two near-simultaneous
            // scans of the same card must serialize here, or both could read
            // the same current_stamps and both increment - a lost update.
            $card = CustomerShopCard::where('customer_id', $customer->id)
                ->where('shop_id', $device->shop_id)
                ->lockForUpdate()
                ->first();

            $card ??= CustomerShopCard::create([
                'customer_id' => $customer->id,
                'shop_id' => $device->shop_id,
                'current_stamps' => 0,
                'rewards_claimed' => 0,
            ]);

            $maxStamps = $device->shop->max_stamps;

            if ($card->current_stamps >= $maxStamps) {
                return $this->redeem($card, $customer, $maxStamps);
            }

            $cooldownHours = (int) config('loyalty.stamp_cooldown_hours');

            if ($card->last_stamped_at && $card->last_stamped_at->copy()->addHours($cooldownHours)->isFuture()) {
                return $this->cooldown($card, $customer, $maxStamps, $cooldownHours);
            }

            return $this->stamp($card, $customer, $maxStamps);
        });

        // Dispatched AFTER the transaction above has committed - a broadcast
        // failure must never break or roll back a stamp that already
        // succeeded, so it's wrapped in try/catch and only logged.
        if (in_array($body['code'], ['stamp_added', 'reward_redeemed'], true)) {
            try {
                event(new CardUpdated(
                    customerUuid: $customer->uuid,
                    shopId: $device->shop_id,
                    stamps: $body['stamps'],
                    maxStamps: $body['max_stamps'],
                    action: $body['code'],
                    rewardReady: $body['reward_ready'] ?? false,
                ));
            } catch (Throwable $e) {
                Log::error('CardUpdated broadcast failed', ['message' => $e->getMessage()]);
            }
        }

        return [$status, $body];
    }

    private function redeem(CustomerShopCard $card, Customer $customer, int $maxStamps): array
    {
        $card->update(['current_stamps' => 0, 'rewards_claimed' => $card->rewards_claimed + 1]);

        StampLog::create([
            'customer_id' => $customer->id,
            'shop_id' => $card->shop_id,
            'action_type' => ActionType::RewardRedeemed,
        ]);

        return [200, [
            'status' => 'ok',
            'code' => 'reward_redeemed',
            'message' => 'Reward redeemed!',
            'stamps' => $card->current_stamps,
            'max_stamps' => $maxStamps,
            'customer_name' => $customer->name,
        ]];
    }

    private function cooldown(CustomerShopCard $card, Customer $customer, int $maxStamps, int $cooldownHours): array
    {
        $nextAllowedAt = $card->last_stamped_at->copy()->addHours($cooldownHours);

        return [409, [
            'status' => 'error',
            'code' => 'cooldown',
            'message' => 'Already stamped today at '.$card->last_stamped_at->format('g:i A').'.',
            'stamps' => $card->current_stamps,
            'max_stamps' => $maxStamps,
            'customer_name' => $customer->name,
            'next_allowed_at' => $nextAllowedAt->toIso8601String(),
        ]];
    }

    private function stamp(CustomerShopCard $card, Customer $customer, int $maxStamps): array
    {
        $card->update([
            'current_stamps' => $card->current_stamps + 1,
            'last_stamped_at' => now(),
        ]);

        StampLog::create([
            'customer_id' => $customer->id,
            'shop_id' => $card->shop_id,
            'action_type' => ActionType::StampAdded,
        ]);

        return [200, [
            'status' => 'ok',
            'code' => 'stamp_added',
            'message' => 'Stamped!',
            'stamps' => $card->current_stamps,
            'max_stamps' => $maxStamps,
            'customer_name' => $customer->name,
            'reward_ready' => $card->current_stamps >= $maxStamps,
        ]];
    }

    private function error(int $httpStatus, string $code, string $message): array
    {
        return [$httpStatus, [
            'status' => 'error',
            'code' => $code,
            'message' => $message,
        ]];
    }
}
