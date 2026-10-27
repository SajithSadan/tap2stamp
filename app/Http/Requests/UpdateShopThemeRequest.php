<?php

namespace App\Http\Requests;

use App\Support\ThemeCatalog;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShopThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shop = $this->user()->shop;

        return [
            'theme' => [
                'required', 'string', Rule::in(array_keys(ThemeCatalog::all())),
                // Locked themes need a plan with all themes; the current one may be re-picked.
                function (string $attribute, mixed $value, Closure $fail) use ($shop) {
                    if ($value !== $shop->theme && ! $shop->canUseTheme($value)) {
                        $fail(ThemeCatalog::LOCKED_MESSAGE);
                    }
                },
            ],
        ];
    }
}
