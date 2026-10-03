<?php

namespace App\Http\Requests;

use App\Models\Shop;
use App\Support\StampIcons;
use App\Support\ThemeCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            'owner_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', Password::min(8)],
            'shop_name' => ['required', 'string', 'max:150'],
            'shop_slug' => ['required', 'string', 'max:150', 'alpha_dash', 'unique:shops,slug'],
            'shop_max_stamps' => Shop::maxStampsRules(),
            'shop_reward_title' => ['required', 'string', 'max:150'],
            'shop_stamp_icon' => ['required', 'string', Rule::in(StampIcons::KEYS)],
            'shop_theme' => ['required', 'string', Rule::in(array_keys(ThemeCatalog::all()))],
        ];
    }

    public function attributes(): array
    {
        return [
            'owner_email' => 'email',
            'owner_password' => 'password',
            'shop_slug' => 'card link',
        ];
    }
}
