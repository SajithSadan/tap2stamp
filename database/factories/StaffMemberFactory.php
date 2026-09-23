<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<StaffMember>
 */
class StaffMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->unique()->firstName(),
            'pin_hash' => Hash::make('1234'),
            'deactivated_at' => null,
        ];
    }

    public function withPin(string $pin): static
    {
        return $this->state(fn () => ['pin_hash' => Hash::make($pin)]);
    }

    public function deactivated(): static
    {
        return $this->state(fn () => ['deactivated_at' => now()]);
    }
}
