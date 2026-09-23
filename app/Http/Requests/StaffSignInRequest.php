<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StaffSignInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_member_id' => ['required', 'integer'],
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
        ];
    }
}
