<?php

namespace App\Http\Requests\Admin;

use App\Models\MenuItem;
use Illuminate\Foundation\Http\FormRequest;

class SaveShopMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The admin route group provides the role check; the target shop is explicit.
        return true;
    }

    public function rules(): array
    {
        return [
            // An empty list is allowed: it clears the menu.
            'sections' => ['present', 'array', 'max:40'],
            'sections.*.name' => ['required', 'string', 'max:80'],
            'sections.*.items' => ['present', 'array', 'max:200'],
            'sections.*.items.*.name' => ['required', 'string', 'max:120'],
            'sections.*.items.*.description' => ['nullable', 'string', 'max:300'],
            'sections.*.items.*.price' => ['nullable', 'string', 'max:40'],
            // Free text - each shop's menu has its own labels.
            'sections.*.items.*.tags' => ['nullable', 'array', 'max:'.MenuItem::MAX_TAGS],
            // Blank ones are dropped by MenuItem::tidyTags() on save.
            'sections.*.items.*.tags.*' => ['nullable', 'string', 'max:'.MenuItem::TAG_LENGTH],
            // Only kept if one of this shop's items already had it (ShopMenu::replace()).
            'sections.*.items.*.image_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sections.*.name' => 'section name',
            'sections.*.items.*.name' => 'item name',
            'sections.*.items.*.description' => 'description',
            'sections.*.items.*.price' => 'price',
            'sections.*.items.*.tags' => 'tags',
            'sections.*.items.*.tags.*' => 'tag',
        ];
    }
}
