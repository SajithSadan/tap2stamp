<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin editing an order. Orders we arranged (bank transfer / cash / free)
 * can change product, quantity, price and method; a Stripe order only its
 * delivery address and note - its amount is what Stripe charged.
 */
class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already gated to role:admin.
        return true;
    }

    /** Before validating: some orders can't be edited at all. */
    protected function prepareForValidation(): void
    {
        $order = $this->route('order');

        abort_if($order->status === OrderStatus::Cancelled, 422, 'Cancelled orders cannot be edited.');
        abort_if($order->status === OrderStatus::Pending && $order->payment_method === PaymentMethod::Stripe, 422, 'The owner is still paying for this one online.');
    }

    public function rules(): array
    {
        $manual = $this->route('order')->payment_method !== PaymentMethod::Stripe;

        return [
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
            ...($manual ? [
                'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
                'quantity' => ['required', 'integer', 'between:1,'.Order::MAX_QUANTITY],
                'payment_method' => ['required', Rule::enum(PaymentMethod::class)->only(PaymentMethod::manual())],
                'amount' => ['required_unless:payment_method,free', 'nullable', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            ] : []),
        ];
    }

    public function amountPence(): int
    {
        return (int) round((float) $this->input('amount', 0) * 100);
    }
}
