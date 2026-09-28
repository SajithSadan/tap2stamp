<?php

namespace Database\Factories;

use App\Models\QrBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrBatch>
 */
class QrBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => null,
        ];
    }
}
