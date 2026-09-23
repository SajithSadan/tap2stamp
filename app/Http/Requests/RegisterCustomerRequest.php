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
     * Normalises a UK number into +447XXXXXXXXX before validation (Indian
     * numbers arrive already as +91XXXXXXXXXX from the country picker), so
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
            str_starts_with($digits, '+91') => $digits,
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
            // UK mobile (+447 + 9 digits) or Indian mobile (+91, 10 digits starting 6-9).
            'phone' => ['required', 'string', 'regex:/^(\+447\d{9}|\+91[6-9]\d{9})$/'],
            // Optional - registering without it is fine.
            'marketing_consent' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid mobile number.',
        ];
    }
}
