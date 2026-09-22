<?php

namespace App\Http\Requests;

use App\Models\CustomTheme;
use App\Support\CuratedFonts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hex = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return [
            'name' => ['required', 'string', 'max:60'],

            'page_bg' => $hex,
            'card_bg' => $hex,
            'text' => $hex,
            'muted' => $hex,
            'accent' => $hex,
            'accent_text' => $hex,
            'stamp_filled' => $hex,
            'stamp_empty' => $hex,
            'border' => $hex,

            'heading_font_name' => ['required', 'string', Rule::in(CuratedFonts::names())],
            'body_font_name' => ['required', 'string', Rule::in(CuratedFonts::names())],

            'radius' => ['required', 'string', Rule::in(CustomTheme::RADIUS_PRESETS)],
            'button_radius' => ['required', 'string', Rule::in(CustomTheme::RADIUS_PRESETS)],
        ];
    }
}
