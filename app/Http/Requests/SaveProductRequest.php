<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already gated to role:admin.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            // Pounds, as typed (e.g. "40" or "39.99").
            'price' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            // Price breaks: from item N on, each costs this much (pounds).
            'price_tiers' => ['nullable', 'array', 'max:5'],
            'price_tiers.*.from' => ['required', 'integer', 'between:2,'.Order::MAX_QUANTITY, 'distinct'],
            'price_tiers.*.price' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'price_tiers.*.from.between' => 'Price breaks start from the 2nd item (up to '.Order::MAX_QUANTITY.').',
            'price_tiers.*.from.distinct' => 'Each price break needs a different item number.',
            'price_tiers.*.price.*' => 'Enter a price in pounds, e.g. 20 or 19.99.',
        ];
    }

    /** The validated values as product columns (prices in pence). */
    public function productValues(): array
    {
        return [
            'name' => $this->string('name')->trim()->value(),
            'description' => $this->filled('description') ? $this->string('description')->trim()->value() : null,
            'price_pence' => (int) round((float) $this->input('price') * 100),
            'price_tiers' => collect($this->input('price_tiers', []))
                ->map(fn (array $tier) => ['from' => (int) $tier['from'], 'price_pence' => (int) round((float) $tier['price'] * 100)])
                ->sortBy('from')
                ->values()
                ->all() ?: null,
            'is_active' => $this->boolean('is_active'),
            'is_featured' => $this->boolean('is_featured'),
        ];
    }
}
