<?php

namespace App\Http\Requests\Admin;

use App\Services\MenuReader;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ReadMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'max:'.MenuReader::MAX_FILES, function (string $attribute, mixed $files, Closure $fail) {
                $total = collect(is_array($files) ? $files : [])->sum(fn ($file) => $file?->getSize() ?? 0);

                if ($total > MenuReader::MAX_TOTAL_BYTES) {
                    $fail('Those files are too big together - use fewer pages or smaller photos.');
                }
            }],
            // JPG/PNG/WebP photos or a PDF of the menu.
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Add a photo or PDF of the menu.',
            'files.max' => 'Up to '.MenuReader::MAX_FILES.' files at a time.',
            'files.*.mimes' => 'Menus must be JPG, PNG, WebP or PDF.',
            'files.*.max' => 'Each file can be up to 10 MB.',
        ];
    }
}
