<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\StaffDevice;
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

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
