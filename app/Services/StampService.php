<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Events\CardUpdated;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use App\Models\StaffMember;
use App\Models\StampLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StampService
{
    /**
     * A full card is never redeemed by a scan alone: the scan answers
     * `reward_ready` (nothing changes) and the staff member or owner confirms
     * with "Mark reward as given" - the same payload again with $redeem. So
     * an accidental second scan can't reset a card. $redeem on a card that
     * isn't full (e.g. a double tap after it was given) answers `no_reward`
     * and never stamps.
     *
     * @return array{0: int, 1: array<string, mixed>} [HTTP status, JSON body]
     */
    public function scan(Shop $shop, string $payload, ?StaffMember $staff = null, ?int $ownerId = null, bool $redeem = false): array
    {
        if (! preg_match('/^TOKEN:([0-9a-fA-F-]{36})\|SHOP:(\d+)$/', $payload, $matches)) {
            return $this->error(422, 'invalid_qr', "That doesn't look like a loyalty card QR code.");
        }

        [, $uuid, $shopId] = $matches;

        // The server trusts nothing from the client beyond the raw payload -
        // the shop in the QR must match the authenticated shop context.
        if ((int) $shopId !== $shop->id) {
            return $this->error(403, 'shop_mismatch', 'This card belongs to a different business.');
        }

        $customer = Customer::where('uuid', $uuid)->first();

        if (! $customer) {
            return $this->error(404, 'customer_not_found', 'No customer found for this QR code.');
        }

        [$status, $body] = DB::transaction(function () use ($customer, $shop, $staff, $ownerId, $redeem) {
            // lockForUpdate() inside the transaction: two near-simultaneous
            // scans of the same card must serialize here, or both could read
            // the same current_stamps and both increment - a lost update.
            $card = CustomerShopCard::where('customer_id', $customer->id)
                ->where('shop_id', $shop->id)
                ->lockForUpdate()
                ->first();

            $card ??= CustomerShopCard::create([
                'customer_id' => $customer->id,
                'shop_id' => $shop->id,
                'current_stamps' => 0,
                'rewards_claimed' => 0,
            ]);

            $maxStamps = $shop->max_stamps;

            if ($card->current_stamps >= $maxStamps) {
                return $redeem
                    ? $this->redeem($card, $customer, $maxStamps, $staff, $ownerId)
                    : $this->rewardReady($card, $customer, $shop);
            }

            if ($redeem) {
                return $this->noReward($card, $customer, $maxStamps);
            }

            $cooldownHours = (int) config('loyalty.stamp_cooldown_hours');

            if ($card->last_stamped_at && $card->last_stamped_at->copy()->addHours($cooldownHours)->isFuture()) {
                return $this->cooldown($card, $customer, $maxStamps, $cooldownHours);
            }

            return $this->stamp($card, $customer, $maxStamps, $staff, $ownerId);
        });

        // Dispatched AFTER the transaction above has committed - a broadcast
        // failure must never break or roll back a stamp that already
        // succeeded, so it's wrapped in try/catch and only logged.
        if (in_array($body['code'], ['stamp_added', 'reward_redeemed'], true)) {
            // Who stamped whom (the customer by name only, never the phone).
            $redeemed = $body['code'] === 'reward_redeemed';
            ActivityLogger::record(
                $redeemed ? 'stamp.reward_redeemed' : 'stamp.added',
                $redeemed ? "Redeemed {$customer->name}'s reward" : "Stamped {$customer->name}'s card ({$body['stamps']}/{$body['max_stamps']})",
                $shop->id,
                $customer,
                ['stamps' => [$redeemed ? $body['max_stamps'] : $body['stamps'] - 1, $body['stamps']]],
                $staff ? ActivityLogger::staff($staff) : ($ownerId && ($owner = User::find($ownerId)) ? ActivityLogger::user($owner) : null),
            );

            try {
                event(new CardUpdated(
                    customerUuid: $customer->uuid,
                    shopId: $shop->id,
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

    private function redeem(CustomerShopCard $card, Customer $customer, int $maxStamps, ?StaffMember $staff, ?int $ownerId): array
    {
        $card->update(['current_stamps' => 0, 'rewards_claimed' => $card->rewards_claimed + 1]);

        StampLog::create([
            'customer_id' => $customer->id,
            'shop_id' => $card->shop_id,
            'staff_member_id' => $staff?->id,
            'owner_user_id' => $ownerId,
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

    /** A full card was scanned: show the reward and wait for "Mark reward as given". Nothing changes. */
    private function rewardReady(CustomerShopCard $card, Customer $customer, Shop $shop): array
    {
        return [200, [
            'status' => 'ok',
            'code' => 'reward_ready',
            'message' => 'Reward ready to give.',
            'stamps' => $card->current_stamps,
            'max_stamps' => $shop->max_stamps,
            'customer_name' => $customer->name,
            'reward_title' => $shop->reward_title,
            'rewards_claimed' => $card->rewards_claimed,
        ]];
    }

    /** "Mark reward as given" on a card that isn't full - typically a second tap after it was given. */
    private function noReward(CustomerShopCard $card, Customer $customer, int $maxStamps): array
    {
        return [409, [
            'status' => 'error',
            'code' => 'no_reward',
            'message' => $card->current_stamps === 0
                ? 'This reward has already been given.'
                : 'This card isn\'t full yet, so there is no reward to give.',
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

    private function stamp(CustomerShopCard $card, Customer $customer, int $maxStamps, ?StaffMember $staff, ?int $ownerId): array
    {
        $card->update([
            'current_stamps' => $card->current_stamps + 1,
            'last_stamped_at' => now(),
        ]);

        StampLog::create([
            'customer_id' => $customer->id,
            'shop_id' => $card->shop_id,
            'staff_member_id' => $staff?->id,
            'owner_user_id' => $ownerId,
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
            'reward_title' => $card->shop->reward_title,
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
