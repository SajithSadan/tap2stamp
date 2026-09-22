<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalises the phone number into +447XXXXXXXXX before validation, so
     * the regex rule below only has to check the final canonical shape —
     * accepts 07..., +447..., and 447... input as the spec requires.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->normalizePhone((string) $this->input('phone')),
        ]);
    }

    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\s+/', '', $raw) ?? '';

        return match (true) {
            str_starts_with($digits, '+447') => $digits,
            str_starts_with($digits, '447') => '+'.$digits,
            str_starts_with($digits, '07') => '+44'.substr($digits, 1),
            default => $digits,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^\+447\d{9}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid UK mobile number.',
        ];
    }
}
