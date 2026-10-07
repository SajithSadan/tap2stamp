<?php

namespace App\Http\Requests;

use App\Support\ShopContact;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShopContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already gated to role:owner; the shop belongs to auth()->user().
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(ShopContact::normalise($this->all()));
    }

    public function rules(): array
    {
        return ShopContact::rules($this->input('country'), phoneCode: $this->input('contact_phone_code'));
    }

    public function messages(): array
    {
        return ShopContact::messages($this->input('country'), $this->input('contact_phone_code'));
    }
}
