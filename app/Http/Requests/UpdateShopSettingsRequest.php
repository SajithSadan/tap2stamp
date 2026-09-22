<?php

namespace App\Http\Requests;

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
            'max_stamps' => ['required', 'integer', 'between:4,12'],
            'reward_title' => ['required', 'string', 'max:150'],
            'google_review_url' => ['nullable', 'url', 'max:500'],
            'instagram_url' => ['nullable', 'url', 'max:500'],
            'wifi_ssid' => ['nullable', 'string', 'max:150'],
            'wifi_password' => ['nullable', 'string', 'max:150'],
        ];
    }
}
