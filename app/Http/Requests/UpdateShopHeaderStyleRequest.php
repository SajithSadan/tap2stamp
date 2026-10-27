<?php

namespace App\Http\Requests;

use App\Support\HeaderStyle;
use App\Support\ThemeCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateShopHeaderStyleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return HeaderStyle::rules();
    }

    /** Header text / tint is part of the banner, which comes with a plan that has all themes. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->user()->shop->hasAllThemes()) {
                    $validator->errors()->add('header', ThemeCatalog::LOCKED_MESSAGE);
                }
            },
        ];
    }
}
