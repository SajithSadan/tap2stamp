<?php

namespace App\Http\Requests;

use App\Support\ThemeCatalog;
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
        return [
            'theme' => ['required', 'string', Rule::in(array_keys(ThemeCatalog::all()))],
        ];
    }
}
