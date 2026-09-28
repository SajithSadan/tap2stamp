<?php

namespace Database\Factories;

use App\Models\QrBatch;
use App\Models\QrCode;
use App\Services\QrCodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrCode>
 */
class QrCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'qr_batch_id' => QrBatch::factory(),
            'code' => fake()->unique()->regexify('['.QrCodeGenerator::ALPHABET.']{'.QrCodeGenerator::LENGTH.'}'),
            'destination_url' => null,
            'mapped_at' => null,
        ];
    }

    public function mapped(string $url = 'https://example.com/event'): static
    {
        return $this->state(fn () => ['destination_url' => $url, 'mapped_at' => now()]);
    }
}
