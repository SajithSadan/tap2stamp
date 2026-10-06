<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The activity log's one writer: what admins, owners and staff did, with
 * before/after for changes. Customers are never recorded - with no admin,
 * owner or staff member behind the request (a customer page, a webhook,
 * the console) nothing is written.
 *
 * Model changes are recorded automatically by the RecordsActivity trait;
 * everything else (stamps, sign-ins, menu saves…) calls record() directly.
 * A logging failure is logged and swallowed - it never breaks the action.
 */
class ActivityLogger
{
    public const ACTOR_TYPES = ['admin', 'owner', 'staff'];

    /**
     * @param  array<string, mixed>|null  $changes  {field: [before, after]} or extra detail
     * @param  array<string, mixed>|null  $actor  override (see actor()); null = who is signed in
     */
    public static function record(
        string $action,
        string $description,
        ?int $shopId = null,
        ?Model $subject = null,
        ?array $changes = null,
        ?array $actor = null,
    ): void {
        try {
            $actor ??= self::actor();
            if ($actor === null) {
                return;
            }

            ActivityLog::create([
                ...$actor,
                'shop_id' => $shopId ?? $actor['shop_id'] ?? null,
                'action' => $action,
                'subject_type' => $subject ? self::subjectType($subject) : null,
                'subject_id' => $subject?->getKey(),
                'description' => mb_strimwidth($description, 0, 255, '…'),
                'changes' => $changes ?: null,
            ]);
        } catch (Throwable $e) {
            Log::error('Activity log write failed', ['action' => $action, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Who is acting on this request: a signed-in staff member on a staff
     * device, else the signed-in admin / owner (an admin viewing a shop as its
     * owner is recorded as the admin). Null for customers and the console.
     *
     * @return array<string, mixed>|null
     */
    public static function actor(): ?array
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        $device = $request?->attributes->get('staffDevice');
        if ($device instanceof StaffDevice) {
            $member = $request->attributes->get('staffMember') ?? $device->activeStaffMember();

            return $member ? self::staff($member) : null;
        }

        $admin = $request?->attributes->get('viewAsAdmin');
        if ($admin instanceof User) {
            return [...self::user($admin), 'as_owner' => true];
        }

        $user = Auth::user();

        return $user instanceof User ? self::user($user) : null;
    }

    /** @return array<string, mixed> */
    public static function staff(StaffMember $member): array
    {
        return [
            'actor_type' => 'staff',
            'user_id' => null,
            'staff_member_id' => $member->id,
            'actor_name' => $member->name,
            'as_owner' => false,
            'shop_id' => $member->shop_id,
        ];
    }

    /** @return array<string, mixed> */
    public static function user(User $user): array
    {
        return [
            'actor_type' => $user->role === UserRole::Admin ? 'admin' : 'owner',
            'user_id' => $user->id,
            'staff_member_id' => null,
            'actor_name' => $user->email ?: $user->name,
            'as_owner' => false,
            // A query, not $user->shop: loading the relation here would cache it
            // (as null, mid sign-up) on the very User the request goes on to use.
            'shop_id' => $user->role === UserRole::Owner ? Shop::where('user_id', $user->id)->value('id') : null,
        ];
    }

    /** "StaffMember" → "staff_member". */
    public static function subjectType(Model $model): string
    {
        return str(class_basename($model))->snake()->toString();
    }
}
