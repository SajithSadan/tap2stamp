<?php

namespace App\Http\Requests;

use App\Support\RewardGames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** The owner's reward game builder: the game, its prizes and when customers play. */
class SaveRewardGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Blank optional numbers arrive as "" - treat them as not set. */
    protected function prepareForValidation(): void
    {
        $blank = fn ($v) => $v === '' ? null : $v;

        $this->merge([
            'trigger_value' => $blank($this->input('trigger_value')),
            'max_plays_per_customer' => $blank($this->input('max_plays_per_customer')),
            'plays_period' => $blank($this->input('plays_period')),
            // Older clients / tests that don't send it keep the default.
            'after_win' => $blank($this->input('after_win')) ?? 'continue',
            'starts_on' => $blank($this->input('starts_on')),
            'ends_on' => $blank($this->input('ends_on')),
            'prizes' => collect($this->input('prizes', []))->map(fn ($prize) => is_array($prize) ? [
                ...$prize,
                'detail' => $blank(trim((string) ($prize['detail'] ?? ''))),
                'title' => trim((string) ($prize['title'] ?? '')),
                'bonus_stamps' => $blank($prize['bonus_stamps'] ?? null),
                'stock' => $blank($prize['stock'] ?? null),
                'daily_limit' => $blank($prize['daily_limit'] ?? null),
            ] : $prize)->all(),
        ]);
    }

    public function rules(): array
    {
        $type = RewardGames::TYPES[$this->input('type')] ?? ['min_prizes' => 2, 'max_prizes' => 8];

        return [
            'name' => ['required', 'string', 'max:60'],
            'headline' => ['nullable', 'string', 'max:60'],
            'type' => ['required', Rule::in(array_keys(RewardGames::TYPES))],
            'trigger' => ['required', Rule::in(array_keys(RewardGames::TRIGGERS))],
            'trigger_value' => ['nullable', 'required_if:trigger,'.RewardGames::EVERY_N_STAMPS, 'integer', 'between:1,'.RewardGames::MAX_TRIGGER_VALUE],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'max_plays_per_customer' => ['nullable', 'integer', 'between:1,1000'],
            'plays_period' => ['nullable', Rule::in(array_keys(RewardGames::PLAYS_PERIODS))],
            'after_win' => ['required', Rule::in(array_keys(RewardGames::AFTER_WIN))],
            'voucher_days' => ['required', 'integer', 'between:1,365'],
            // The scratch card's image (no SVG - photos only), and "remove it".
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=300,min_height=200'],
            'remove_image' => ['nullable', 'boolean'],
            'prizes' => ['required', 'array', 'min:'.$type['min_prizes'], 'max:'.$type['max_prizes']],
            'prizes.*.id' => ['nullable', 'integer'],
            'prizes.*.title' => ['required', 'string', 'max:60'],
            'prizes.*.detail' => ['nullable', 'string', 'max:120'],
            'prizes.*.kind' => ['required', Rule::in(array_keys(RewardGames::PRIZE_KINDS))],
            'prizes.*.bonus_stamps' => ['nullable', 'required_if:prizes.*.kind,'.RewardGames::BONUS_STAMPS, 'integer', 'between:1,'.RewardGames::MAX_BONUS_STAMPS],
            'prizes.*.weight' => ['required', 'integer', 'between:1,'.RewardGames::MAX_WEIGHT],
            'prizes.*.stock' => ['nullable', 'integer', 'between:1,100000'],
            'prizes.*.daily_limit' => ['nullable', 'integer', 'between:1,10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'prizes.min' => 'Add at least :min prizes.',
            'prizes.max' => 'This game takes up to :max prizes.',
            'prizes.*.title.required' => 'Name the prize.',
            'prizes.*.bonus_stamps.required_if' => 'How many bonus stamps?',
            'trigger_value.required_if' => 'After how many stamps?',
            'ends_on.after_or_equal' => 'The end date is before the start date.',
            'image.mimes' => 'Upload a JPG, PNG or WebP image.',
            'image.max' => 'That image is over 4 MB. Try a smaller one.',
            'image.dimensions' => 'That image is too small. Use one at least 300 × 200 pixels.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $prizes = collect($this->input('prizes', []))->filter(fn ($p) => is_array($p));
                if ($prizes->isNotEmpty() && $prizes->every(fn ($p) => ($p['kind'] ?? null) === RewardGames::NO_WIN)) {
                    $validator->errors()->add('prizes', 'Add at least one prize customers can win.');
                }
            },
        ];
    }

    /** The game's own fields (no prizes), tidied. */
    public function game(): array
    {
        $data = $this->validated();

        return [
            ...collect($data)->only(['name', 'type', 'trigger', 'starts_on', 'ends_on', 'max_plays_per_customer', 'after_win', 'voucher_days'])->all(),
            // A period only means something with a limit ("3 a day"); a limit without one is per day.
            'plays_period' => filled($data['max_plays_per_customer'] ?? null) ? ($data['plays_period'] ?? 'day') : null,
            'name' => trim($data['name']),
            'headline' => filled($data['headline'] ?? null) ? trim($data['headline']) : null,
            // Only "every N stamps" has a number.
            'trigger_value' => $data['trigger'] === RewardGames::EVERY_N_STAMPS ? (int) $data['trigger_value'] : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function prizes(): array
    {
        return collect($this->validated()['prizes'])->map(fn (array $prize) => [
            ...$prize,
            'bonus_stamps' => $prize['kind'] === RewardGames::BONUS_STAMPS ? (int) $prize['bonus_stamps'] : null,
        ])->values()->all();
    }
}
