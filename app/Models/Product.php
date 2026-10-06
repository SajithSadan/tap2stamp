<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, RecordsActivity;

    protected $fillable = [
        'name',
        'description',
        'price_pence',
        'price_tiers',
        'is_active',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'price_pence' => 'integer',
            'price_tiers' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * How $quantity items are charged: each item costs the price for its
     * position - price_pence from the 1st, then each price break from its
     * `from` item on. E.g. £40 + break {from 2: £20} → 3 items = 40 + 20 + 20.
     * Keep in sync with priceBreakdown() in resources/js/lib/money.js.
     *
     * @return list<array{from: int, quantity: int, unit_pence: int}>
     */
    public function priceBreakdown(int $quantity): array
    {
        $steps = collect($this->price_tiers ?? [])
            ->sortBy('from')
            ->map(fn (array $tier) => ['from' => (int) $tier['from'], 'unit_pence' => (int) $tier['price_pence']])
            ->prepend(['from' => 1, 'unit_pence' => $this->price_pence])
            ->values();

        return $steps->map(function (array $step, int $i) use ($steps, $quantity) {
            $last = isset($steps[$i + 1]) ? $steps[$i + 1]['from'] - 1 : $quantity;

            return [...$step, 'quantity' => max(0, min($quantity, $last) - $step['from'] + 1)];
        })->filter(fn (array $step) => $step['quantity'] > 0)->values()->all();
    }

    public function totalFor(int $quantity): int
    {
        return collect($this->priceBreakdown($quantity))->sum(fn (array $step) => $step['quantity'] * $step['unit_pence']);
    }

    /** What the pages need to show and work out prices. */
    public function pricing(): array
    {
        return ['price_pence' => $this->price_pence, 'price_tiers' => array_values($this->price_tiers ?? [])];
    }

    /** The product the owner dashboard offers, if one is featured and on sale. */
    public static function featured(): ?self
    {
        return static::active()->where('is_featured', true)->first();
    }
}
