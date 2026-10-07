<?php

namespace App\Http\Requests\Admin;

use App\Support\Currencies;
use App\Support\LandingPage;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The landing page (Admin → Landing page): every plan needs a price in every offered currency. */
class UpdateLandingPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already gated to role:admin.
        return true;
    }

    /** Feature lines arrive as one list; blank lines are dropped. */
    protected function prepareForValidation(): void
    {
        $plans = collect($this->input('plans', []))->map(fn ($plan) => is_array($plan) ? [
            ...$plan,
            'features' => collect($plan['features'] ?? [])->map(fn ($f) => trim((string) $f))->filter()->values()->all(),
            'highlighted' => filter_var($plan['highlighted'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ] : $plan)->all();

        $this->merge([
            'plans' => $plans,
            'currencies' => array_values(array_unique(array_map('strtoupper', (array) $this->input('currencies', [])))),
            'default_currency' => strtoupper((string) $this->input('default_currency')),
        ]);
    }

    public function rules(): array
    {
        $currencies = (array) $this->input('currencies', []);

        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:400'],
            'youtube_url' => ['nullable', 'string', 'max:300', function (string $attribute, mixed $value, Closure $fail) {
                if (filled($value) && ! LandingPage::youtubeId($value)) {
                    $fail('Paste a YouTube video link, e.g. https://www.youtube.com/watch?v=…');
                }
            }],
            'currencies' => ['required', 'array', 'min:1', 'max:'.LandingPage::MAX_CURRENCIES],
            'currencies.*' => ['string', Rule::in(Currencies::codes())],
            'default_currency' => ['required', 'string', Rule::in($currencies)],
            'plans' => ['required', 'array', 'min:1', 'max:'.LandingPage::MAX_PLANS],
            'plans.*.name' => ['required', 'string', 'max:40'],
            'plans.*.description' => ['nullable', 'string', 'max:200'],
            'plans.*.note' => ['nullable', 'string', 'max:120'],
            'plans.*.badge' => ['nullable', 'string', 'max:30'],
            'plans.*.highlighted' => ['boolean'],
            'plans.*.cta_label' => ['required', 'string', 'max:30'],
            'plans.*.period' => ['nullable', 'string', 'max:30'],
            'plans.*.billing_note' => ['nullable', 'string', 'max:60'],
            'plans.*.features' => ['array', 'max:'.LandingPage::MAX_FEATURES],
            'plans.*.features.*' => ['string', 'max:80'],
            // As displayed: up to 2 decimals, e.g. 4.99 or 399.
            ...collect($currencies)->mapWithKeys(fn ($code) => [
                "plans.*.prices.{$code}" => ['required', 'string', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            ])->all(),
        ];
    }

    public function messages(): array
    {
        return [
            'plans.*.prices.*.required' => 'Enter a price for every currency.',
            'plans.*.prices.*.regex' => 'Enter the price as a number, e.g. 4.99.',
            'default_currency.in' => 'The default currency must be one of the offered ones.',
        ];
    }

    /** The validated page, tidied: only offered currencies' prices, empty optional texts as null. */
    public function content(): array
    {
        $data = $this->validated();
        $blank = fn ($v) => filled($v) ? trim($v) : null;

        return [
            'title' => trim($data['title']),
            'description' => $blank($data['description'] ?? null),
            'youtube_url' => $blank($data['youtube_url'] ?? null),
            'default_currency' => $data['default_currency'],
            'currencies' => $data['currencies'],
            'plans' => collect($data['plans'])->map(fn (array $plan) => [
                'name' => trim($plan['name']),
                'description' => $blank($plan['description'] ?? null),
                'note' => $blank($plan['note'] ?? null),
                'badge' => $blank($plan['badge'] ?? null),
                'highlighted' => (bool) ($plan['highlighted'] ?? false),
                'cta_label' => trim($plan['cta_label']),
                'period' => $blank($plan['period'] ?? null),
                'billing_note' => $blank($plan['billing_note'] ?? null),
                'prices' => array_intersect_key($plan['prices'] ?? [], array_flip($data['currencies'])),
                'features' => array_values($plan['features'] ?? []),
            ])->values()->all(),
        ];
    }
}
