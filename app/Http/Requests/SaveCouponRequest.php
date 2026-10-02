<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SaveCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already gated to role:admin.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => Coupon::normalise((string) $this->input('code'))]);
    }

    public function rules(): array
    {
        $percent = $this->input('discount_type') === Coupon::PERCENT;

        return [
            'code' => ['required', 'string', 'between:3,32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($this->route('coupon'))],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in([Coupon::PERCENT, Coupon::FIXED])],
            // Percent: a whole number 1-100. Fixed: pounds, as typed (e.g. "5" or "7.50").
            'discount_value' => $percent
                ? ['required', 'integer', 'between:1,100']
                : ['required', 'numeric', 'min:0.01', 'max:10000', 'decimal:0,2'],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'once_per_shop' => ['boolean'],
            // The last day it works (inclusive, UK time).
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, numbers, - and _ only (no spaces).',
            'code.unique' => 'There is already a coupon with this code.',
            'discount_value.between' => 'Enter a percentage from 1 to 100.',
            'discount_value.integer' => 'Enter a whole percentage, e.g. 10.',
            'discount_value.decimal' => 'Enter an amount in pounds, e.g. 5 or 7.50.',
        ];
    }

    /** The validated values as coupon columns (fixed amounts in pence). */
    public function couponValues(): array
    {
        $percent = $this->input('discount_type') === Coupon::PERCENT;

        return [
            'code' => $this->input('code'),
            'description' => $this->filled('description') ? $this->string('description')->trim()->value() : null,
            'discount_type' => $this->input('discount_type'),
            'discount_value' => $percent ? $this->integer('discount_value') : (int) round((float) $this->input('discount_value') * 100),
            'product_id' => $this->filled('product_id') ? $this->integer('product_id') : null,
            'max_uses' => $this->filled('max_uses') ? $this->integer('max_uses') : null,
            'once_per_shop' => $this->boolean('once_per_shop'),
            'expires_at' => $this->filled('expires_on')
                ? Carbon::createFromFormat('Y-m-d', $this->input('expires_on'), 'Europe/London')->endOfDay()
                : null,
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
