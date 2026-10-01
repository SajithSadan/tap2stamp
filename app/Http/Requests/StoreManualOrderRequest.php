<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already gated to role:admin.
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'quantity' => ['required', 'integer', 'between:1,'.Order::MAX_QUANTITY],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->only(PaymentMethod::manual())],
            // The agreed price in pounds (normal, discounted); ignored for "free".
            'amount' => ['required_unless:payment_method,free', 'nullable', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            // false = still waiting for the shop's bank transfer (confirmed later).
            'paid' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function amountPence(): int
    {
        return (int) round((float) $this->input('amount', 0) * 100);
    }
}
