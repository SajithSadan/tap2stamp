<?php

namespace App\Http\Requests;

use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShopSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already gated to role:owner; the shop belongs to auth()->user().
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'max_stamps' => Shop::maxStampsRules(),
            'reward_title' => ['required', 'string', 'max:150'],
            // Both are buttons on the customer card page, so web addresses only.
            'google_review_url' => ['nullable', 'url:http,https', 'max:500'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:500'],
            'wifi_ssid' => ['nullable', 'string', 'max:150'],
            'wifi_password' => ['nullable', 'string', 'max:150'],
        ];
    }
}
