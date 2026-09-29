<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShopLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // No SVG (it can carry scripts). Shown in a small round badge, so
            // 2 MB and 120 px square is plenty.
            'logo' => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048',
                'dimensions:min_width=120,min_height=120',
            ],
        ];
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
