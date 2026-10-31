<?php

namespace App\Http\Requests;

use App\Models\PlanCoupon;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * A plan coupon (Admin → Landing & plans → Plan coupons). `discounts` is a
 * list of {country, value} rows - the countries that can use it, each with
 * its own discount (percent, or an amount in that country's currency).
 */
class SavePlanCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already gated to role:admin.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => PlanCoupon::normalise((string) $this->input('code')),
            'discounts' => collect($this->input('discounts', []))
                ->map(fn ($row) => [
                    'country' => strtoupper(trim((string) ($row['country'] ?? ''))),
                    'value' => trim((string) ($row['value'] ?? '')),
                ])->values()->all(),
        ]);
    }

    public function rules(): array
    {
        $percent = $this->input('discount_type') === PlanCoupon::PERCENT;

        return [
            'code' => ['required', 'string', 'between:3,32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('plan_coupons', 'code')->ignore($this->route('planCoupon'))],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in([PlanCoupon::PERCENT, PlanCoupon::FIXED])],
            'discounts' => ['required', 'array', 'min:1', 'max:'.count(Countries::ALL)],
            'discounts.*.country' => ['required', 'distinct', Rule::in(Countries::codes())],
            // Percent: a whole number 1-100. Fixed: the amount in that country's currency, as typed.
            'discounts.*.value' => $percent
                ? ['required', 'integer', 'between:1,100']
                : ['required', 'numeric', 'gt:0', 'max:100000000', 'decimal:0,3'],
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')],
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
            'code.unique' => 'There is already a plan coupon with this code.',
            'discounts.required' => 'Add at least one country.',
            'discounts.min' => 'Add at least one country.',
            'discounts.*.country.required' => 'Pick a country.',
            'discounts.*.country.distinct' => 'This country is listed twice.',
            'discounts.*.value.required' => 'Enter the discount.',
            'discounts.*.value.between' => 'Enter a percentage from 1 to 100.',
            'discounts.*.value.integer' => 'Enter a whole percentage, e.g. 10.',
            'discounts.*.value.gt' => 'Enter an amount above 0.',
            'discounts.*.value.numeric' => 'Enter an amount, e.g. 500 or 7.50.',
            'discounts.*.value.decimal' => 'Enter an amount, e.g. 500 or 7.50.',
        ];
    }

    /** The validated values as plan_coupons columns. */
    public function couponValues(): array
    {
        return [
            'code' => $this->input('code'),
            'description' => $this->filled('description') ? $this->string('description')->trim()->value() : null,
            'discount_type' => $this->input('discount_type'),
            'discounts' => collect($this->input('discounts'))->mapWithKeys(fn (array $row) => [$row['country'] => $row['value']])->all(),
            'plan_id' => $this->filled('plan_id') ? $this->integer('plan_id') : null,
            'max_uses' => $this->filled('max_uses') ? $this->integer('max_uses') : null,
            'once_per_shop' => $this->boolean('once_per_shop'),
            'expires_at' => $this->filled('expires_on')
                ? Carbon::createFromFormat('Y-m-d', $this->input('expires_on'), 'Europe/London')->endOfDay()
                : null,
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
