<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already gated to role:admin.
        return true;
    }

    public function rules(): array
    {
        return [
            // http/https only: the scan route redirects straight to this, so a
            // javascript: or data: URL must never get in. Empty = unmap.
            'destination_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    public function messages(): array
    {
        return [
            'destination_url.url' => 'Enter a full web address starting with http:// or https://.',
        ];
    }
}
