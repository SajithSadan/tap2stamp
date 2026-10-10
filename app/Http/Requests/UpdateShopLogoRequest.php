<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShopLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A shop logo file - here and in shop setup (StoreOnboardingShopRequest).
     * No SVG (it can carry scripts). Shown in a small badge, so 2 MB and
     * 120 px square is plenty (the browser crops it to a 512 px square).
     */
    public const LOGO_RULES = [
        'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048',
        'dimensions:min_width=120,min_height=120',
    ];

    public function rules(): array
    {
        return ['logo' => ['required', ...self::LOGO_RULES]];
    }

    public function messages(): array
    {
        return [
            'logo.mimes' => 'Upload a JPG, PNG or WebP image.',
            'logo.max' => 'That image is over 2 MB. Try a smaller one.',
            'logo.dimensions' => 'That image is too small. Use one at least 120 × 120 pixels.',
        ];
    }
}
