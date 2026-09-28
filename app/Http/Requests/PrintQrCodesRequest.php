<?php

namespace App\Http\Requests;

use App\Services\QrCodeGenerator;
use Illuminate\Foundation\Http\FormRequest;

class PrintQrCodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already gated to role:admin.
        return true;
    }

    public function rules(): array
    {
        return [
            // Either a whole batch or a hand-picked selection.
            'batch' => ['required_without:ids', 'nullable', 'integer', 'exists:qr_batches,id'],
            'ids' => ['required_without:batch', 'nullable', 'array', 'max:'.QrCodeGenerator::MAX_PER_BATCH],
            'ids.*' => ['integer'],
        ];
    }
}
