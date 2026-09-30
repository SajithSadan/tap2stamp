<?php

namespace App\Http\Requests;

use App\Support\HeaderStyle;
use Illuminate\Foundation\Http\FormRequest;

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
}
