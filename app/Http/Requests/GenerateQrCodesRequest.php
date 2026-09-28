<?php

namespace App\Http\Requests;

use App\Services\QrCodeGenerator;
use Illuminate\Foundation\Http\FormRequest;

class GenerateQrCodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already gated to role:admin.
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'between:1,'.QrCodeGenerator::MAX_PER_BATCH],
            'name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
