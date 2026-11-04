<?php

namespace App\Http\Requests;

use App\Models\PosCustomer;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The billing app's restaurant details: onboarding (POST /api/pos/register,
 * with a username + password) and edits (PUT /api/pos/me, details only -
 * the password has its own endpoint).
 */
class SavePosCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function registering(): bool
    {
        return $this->isMethod('post');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'username' => $this->has('username') ? PosCustomer::normaliseUsername((string) $this->input('username')) : null,
            'country' => $this->has('country') ? strtoupper(trim((string) $this->input('country'))) : null,
        ], fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        // Registering: everything required. Editing: only what's sent changes.
        $required = $this->registering() ? 'required' : 'sometimes';

        return [
            'restaurant_name' => [$required, 'string', 'max:255'],
            'contact_name' => [$required, 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => [$required, 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{6,20}$/'],
            'address_line1' => [$required, 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'town' => [$required, 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:16'],
            'country' => [$required, Rule::in(Countries::codes())],
            ...($this->registering() ? [
                'username' => ['required', 'string', 'between:3,32', 'regex:/^[a-z0-9._-]+$/', Rule::unique('pos_customers', 'username')],
                'password' => ['required', 'string', 'min:6', 'max:128'],
            ] : []),
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Use letters, numbers, dots, - and _ only (no spaces).',
            'username.unique' => 'This username is taken.',
            'phone.regex' => 'Enter a valid phone number.',
        ];
    }

    /** The validated restaurant details (not the sign-in), trimmed. */
    public function details(): array
    {
        return collect($this->safe()->only(PosCustomer::DETAILS))
            ->map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value)
            ->all();
    }
}
