<?php

namespace App\Http\Requests\Admin;

use App\Models\Shop;
use App\Support\ShopContact;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShopSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The admin route group provides the role check; the target shop is explicit.
        return true;
    }

    /** Tidy phone/postcode/email the way the owner's form does ("ls1 4ap" → "LS1 4AP"). */
    protected function prepareForValidation(): void
    {
        $this->merge(collect(ShopContact::normalise($this->all()))
            ->filter(fn ($value, $field) => $this->filled($field))
            ->all());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'max_stamps' => Shop::maxStampsRules(),
            'reward_title' => ['required', 'string', 'max:150'],
            'google_review_url' => ['nullable', 'url:http,https', 'max:500'],
            'google_review_direct' => ['nullable', 'boolean'],
            'show_card_link' => ['nullable', 'boolean'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:500'],
            'wifi_ssid' => ['nullable', 'string', 'max:150'],
            'wifi_password' => ['nullable', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'regex:/^(\+44[1-9]\d{8,9}|\+91[6-9]\d{9})$/'],
            'address_line1' => ['nullable', 'string', 'max:150'],
            'address_line2' => ['nullable', 'string', 'max:150'],
            'town' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'regex:/^([A-Z]{1,2}\d[A-Z\d]? \d[A-Z]{2}|\d{6})$/'],
            // Ticked = post to the shop address (delivery_address saved as null).
            'delivery_same' => ['nullable', 'boolean'],
            'delivery_address' => ['nullable', 'required_if:delivery_same,false', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_phone.regex' => 'Enter a valid UK phone number, e.g. 020 7946 0000 or 07700 900123.',
            'postcode.regex' => 'Enter a valid postcode, e.g. SW1A 1AA.',
            'delivery_address.required_if' => 'Enter the delivery address, or tick "Same as the shop address".',
        ];
    }

    /** The validated values as shop columns. */
    public function shopValues(): array
    {
        $values = collect($this->validated())->except('delivery_same')->all();

        if ($this->boolean('delivery_same', true) || blank($values['delivery_address'] ?? null)) {
            $values['delivery_address'] = null;
        }

        return $values;
    }
}
