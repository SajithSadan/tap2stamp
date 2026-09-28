<?php

namespace App\Http\Requests;

use App\Support\ShopContact;
use Illuminate\Foundation\Http\FormRequest;

class StoreOnboardingShopRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:150', 'alpha_dash', 'unique:shops,slug'],
            'max_stamps' => ['required', 'integer', 'between:4,12'],
            'reward_title' => ['required', 'string', 'max:150'],
            ...ShopContact::rules(),
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'Another shop already uses this link. Try adding your town, e.g. corner-bakery-leeds.',
            ...ShopContact::messages(),
        ];
    }
}
