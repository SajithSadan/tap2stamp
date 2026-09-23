<?php

namespace App\Models;

use Database\Factories\StaffDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffDevice extends Model
{
    /** @use HasFactory<StaffDeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'name',
        'staff_member_id',
        'staff_signed_in_at',
        'token_hash',
        'last_used_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'staff_signed_in_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /**
     * The staff member currently signed in on this device, or null once their
     * sign-in has expired or the owner has deactivated them.
     */
    public function activeStaffMember(): ?StaffMember
    {
        $member = $this->staffMember;
        $hours = (int) config('loyalty.staff_session_hours');

        if (! $member || ! $member->isActive() || ! $this->staff_signed_in_at) {
            return null;
        }

        return $this->staff_signed_in_at->copy()->addHours($hours)->isFuture() ? $member : null;
    }

    public function signIn(StaffMember $member): void
    {
        $this->update(['staff_member_id' => $member->id, 'staff_signed_in_at' => now()]);
    }

    public function signOut(): void
    {
        $this->update(['staff_member_id' => null, 'staff_signed_in_at' => null]);
    }
}
