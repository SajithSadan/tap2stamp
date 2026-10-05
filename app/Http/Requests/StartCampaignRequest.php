<?php

namespace App\Http\Requests;

use App\Services\MarketingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The owner dashboard group checks the role; the shop is always the owner's own.
        return true;
    }

    public function rules(): array
    {
        return [
            'audience' => ['required', Rule::in(MarketingService::AUDIENCES)],
            'message' => ['required', 'string', 'min:10', 'max:'.MarketingService::MAX_MESSAGE],
            // WhatsApp image headers: JPG or PNG, up to 5 MB.
            'poster' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'poster.mimes' => 'The poster must be a JPG or PNG.',
            'poster.max' => 'The poster can be up to 5 MB.',
        ];
    }
}
