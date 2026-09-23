<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShopBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // No SVG (it can carry scripts) - photos only. Wide enough to look
            // sharp as a full-width banner; 4 MB keeps phone photos accepted
            // without letting huge files slow the customer page down.
            'banner' => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096',
                'dimensions:min_width=600,min_height=200',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'banner.mimes' => 'Upload a JPG, PNG or WebP photo.',
            'banner.max' => 'That photo is over 4 MB. Try a smaller one.',
            'banner.dimensions' => 'That photo is too small. Use one at least 600 × 200 pixels.',
        ];
    }
}
