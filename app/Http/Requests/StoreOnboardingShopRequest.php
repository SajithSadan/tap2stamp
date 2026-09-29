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
            'slug' => ['required', 'string', 'max:150', 'alpha_dash', 'unique:shops,slug'],
            'max_stamps' => Shop::maxStampsRules(),
            'reward_title' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'Another shop already uses this link. Try adding your town, e.g. corner-bakery-leeds.',
            ...parent::messages(),
        ];
    }
}
