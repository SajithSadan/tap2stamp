<?php

namespace App\Http\Requests;

use App\Support\Countries;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalises a UK number into +447XXXXXXXXX before validation (every
     * other country arrives already as +{code}{number} from the sign-up's
     * country picker, RegistrationModal.jsx), so the rule below only has to
     * check the final canonical shape — accepts 07..., +447..., and 447...
     * input as the spec requires.
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
            'phone' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! self::validMobile((string) $value)) {
                    $fail('Enter a valid mobile number.');
                }
            }],
            // Optional - registering without it is fine.
            'marketing_consent' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * UK mobile (+447 + 9 digits) and Indian mobile (+91, 10 digits starting
     * 6-9) exactly; any other country: a known dialling code followed by the
     * number, 8-15 digits in all (the international maximum). Mirrors the
     * per-country check in RegistrationModal.jsx.
     */
    public static function validMobile(string $phone): bool
    {
        if (str_starts_with($phone, '+44')) {
            return (bool) preg_match('/^\+447\d{9}$/', $phone);
        }
        if (str_starts_with($phone, '+91')) {
            return (bool) preg_match('/^\+91[6-9]\d{9}$/', $phone);
        }
        if (! preg_match('/^\+\d{8,15}$/', $phone)) {
            return false;
        }

        foreach (Countries::dialCodes() as $code) {
            if (str_starts_with($phone, '+'.$code) && strlen($phone) - 1 - strlen($code) >= 4) {
                return true;
            }
        }

        return false;
    }
}
