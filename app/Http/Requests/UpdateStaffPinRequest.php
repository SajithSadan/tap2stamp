<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'pin.regex' => 'The PIN must be 4 to 6 digits.',
        ];
    }
}
