<?php

namespace App\Http\Requests;

use App\Support\ThemeCatalog;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShopThemeCustomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ThemeCatalog::customRules();
    }
}
