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
        // Not sent = the shop's own country (and its phone / postcode rules).
        if (! $this->filled('country')) {
            $this->merge(['country' => $this->route('shop')?->country]);
        }

        // A number sent without its code gets the country's (or the one typed in front of it).
        $this->merge(collect(ShopContact::normalise($this->all()))
            ->filter(fn ($value, $field) => $this->filled($field) || ($field === 'contact_phone_code' && $this->filled('contact_phone')))
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
            // The design this shop's owner downloads their assigned QR codes in (overseas shops).
            'qr_design_id' => ['nullable', 'integer', 'exists:qr_designs,id'],
            // Contact + location, every field optional here; the country picks the phone / postcode rules.
            ...ShopContact::rules($this->input('country'), required: false, phoneCode: $this->input('contact_phone_code')),
        ];
    }

    public function messages(): array
    {
        return ShopContact::messages($this->input('country'), $this->input('contact_phone_code'));
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
