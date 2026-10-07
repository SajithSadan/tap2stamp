<?php

namespace App\Http\Requests;

use App\Support\ShopContact;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shop setup, step 1 ("Your business"): the shop name + contact and
 * location. Checked on its own before the owner moves on to the loyalty
 * card step; the final submit validates everything again.
 */
class ValidateOnboardingBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already gated to role:owner.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(ShopContact::normalise($this->all()));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            // The country (tidied in prepareForValidation) picks the phone / postcode rules.
            ...ShopContact::rules($this->input('country'), phoneCode: $this->input('contact_phone_code')),
        ];
    }

    public function messages(): array
    {
        return ShopContact::messages($this->input('country'), $this->input('contact_phone_code'));
    }
}
