<?php

namespace App\Http\Requests\Admin;

use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShopSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The admin route group provides the role check; the target shop is explicit.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'max_stamps' => Shop::maxStampsRules(),
            'reward_title' => ['required', 'string', 'max:150'],
            'google_review_url' => ['nullable', 'url:http,https', 'max:500'],
            'google_review_direct' => ['nullable', 'boolean'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:500'],
            'wifi_ssid' => ['nullable', 'string', 'max:150'],
            'wifi_password' => ['nullable', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'regex:/^(\+44[1-9]\d{8,9}|\+91[6-9]\d{9})$/'],
            'address_line1' => ['nullable', 'string', 'max:150'],
            'address_line2' => ['nullable', 'string', 'max:150'],
            'town' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'regex:/^([A-Z]{1,2}\d[A-Z\d]? \d[A-Z]{2}|\d{6})$/'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
