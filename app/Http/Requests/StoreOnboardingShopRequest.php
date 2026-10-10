<?php

namespace App\Http\Requests;

use App\Models\Shop;

/**
 * Shop setup, final submit: step 1's business details plus step 2's
 * loyalty card.
 */
class StoreOnboardingShopRequest extends ValidateOnboardingBusinessRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'max_stamps' => Shop::maxStampsRules(),
            'reward_title' => ['required', 'string', 'max:150'],
            // Optional, cropped square in the browser first. Same rules as Theme → logo.
            'logo' => ['nullable', ...UpdateShopLogoRequest::LOGO_RULES],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            ...(new UpdateShopLogoRequest)->messages(),
        ];
    }
}
