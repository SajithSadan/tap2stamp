<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StaffDevice>
 */
class StaffDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->words(2, true).' counter',
            'token_hash' => hash('sha256', Str::random(64)),
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }

    /** A device with a staff member of the same shop signed in right now. */
    public function withStaff(): static
    {
        return $this->state(fn () => ['staff_signed_in_at' => now()])
            ->afterCreating(function (StaffDevice $device) {
                $member = StaffMember::factory()->create(['shop_id' => $device->shop_id]);
                $device->update(['staff_member_id' => $member->id]);
            });
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
