<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShopOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already gated to role:admin.
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'shop_name' => ['required', 'string', 'max:150'],
            'shop_slug' => ['required', 'string', 'max:150', 'alpha_dash', 'unique:shops,slug'],
            'shop_max_stamps' => ['required', 'integer', 'between:4,12'],
            'shop_reward_title' => ['required', 'string', 'max:150'],
        ];
    }
}
