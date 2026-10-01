<?php

namespace App\Http\Requests;

use App\Services\QrCodeGenerator;
use Illuminate\Foundation\Http\FormRequest;

class RecordQrPrintRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is restricted to role:admin.
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.QrCodeGenerator::MAX_PER_BATCH],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:qr_codes,id'],
            'design_ids' => ['present', 'array', 'max:2'],
            'design_ids.*' => ['required', 'integer', 'distinct', 'exists:qr_designs,id'],
        ];
    }
}