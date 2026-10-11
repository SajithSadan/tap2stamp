<?php

namespace App\Http\Requests;

use App\Services\QrDownloads;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordQrDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is restricted to role:owner; the codes are checked against the owner's shop.
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(QrDownloads::SOURCES)],
            'format' => ['required', Rule::in(QrDownloads::FORMATS)],
            'qr_code_ids' => ['array', 'max:200', Rule::requiredIf($this->input('source') === 'qr_codes')],
            'qr_code_ids.*' => ['integer', 'distinct'],
            'ok' => ['required', 'boolean'],
            'error' => ['nullable', 'string', 'max:200'],
        ];
    }
}
