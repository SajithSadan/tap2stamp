<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Unique per shop: staff pick their own name on the shared device.
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('staff_members', 'name')->where('shop_id', $this->user()->shop->id),
            ],
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Someone on your team already has that name. Add a surname or initial.',
            'pin.regex' => 'The PIN must be 4 to 6 digits.',
        ];
    }
}
