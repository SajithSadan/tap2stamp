<?php

namespace App\Http\Requests;

use App\Models\QrDesign;
use App\Support\QrStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Create (image required) or update (image optional - keeps the current one) a sticker design. */
class SaveQrDesignRequest extends FormRequest
{
    /** Below this a printed QR gets hard for phone cameras to read. */
    public const MIN_QR_MM = 15;

    public function authorize(): bool
    {
        // Route already gated to role:admin.
        return true;
    }

    /** The style travels as one JSON field (the form is multipart, for the uploads). */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('style'))) {
            $this->merge(['style' => json_decode($this->input('style'), true) ?? []]);
        }

        // A preset's size always comes from the server's list, not from what the browser sent.
        $preset = QrDesign::PRESETS[$this->input('preset')] ?? null;
        if ($preset) {
            $this->merge(['width_mm' => $preset['width_mm'], 'height_mm' => $preset['height_mm']]);
        }
        if ($this->input('height_mm') === '') {
            $this->merge(['height_mm' => null]);
        }
    }

    public function rules(): array
    {
        // Photos/artwork only - never SVG (it can carry scripts).
        $image = ['file', 'image', 'mimes:jpg,jpeg,png,webp'];

        return [
            'name' => ['required', 'string', 'max:100'],
            'image' => [$this->route('qrDesign') ? 'nullable' : 'required', ...$image, 'max:5120', 'dimensions:min_width=300,min_height=300'],
            'logo' => ['nullable', ...$image, 'max:1024', 'dimensions:min_width=64,min_height=64'],
            'remove_logo' => ['nullable', 'boolean'],
            'qr_x' => ['required', 'numeric', 'between:0,1'],
            'qr_y' => ['required', 'numeric', 'between:0,1'],
            'qr_size' => ['required', 'numeric', 'between:0.05,1'],
            'width_mm' => ['required', 'integer', 'between:'.QrDesign::MIN_WIDTH_MM.','.QrDesign::MAX_WIDTH_MM],
            'preset' => ['nullable', 'string', Rule::in(array_keys(QrDesign::PRESETS))],
            // Empty = the height follows the artwork's shape.
            'height_mm' => ['nullable', 'integer', 'between:'.QrDesign::MIN_HEIGHT_MM.','.QrDesign::MAX_HEIGHT_MM],
            ...QrStyle::rules(),
        ];
    }

    /** The block must sit wholly on the image, the QR must print big enough to scan, and a logo centre needs a logo. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $style = $this->style();

                if ($style['center_type'] === 'logo' && ! $this->hasFile('logo') && ! $this->keepsLogo()) {
                    $validator->errors()->add('logo', 'Upload a logo for the middle of the QR, or pick another centre option.');
                }

                [$width, $height] = $this->imageSize();
                if (! $width || ! $height) {
                    return;
                }

                $x = (float) $this->input('qr_x');
                $y = (float) $this->input('qr_y');
                $size = (float) $this->input('qr_size');
                // Block height as a fraction of the image height. Small tolerance for browser rounding.
                $fits = $x + $size <= 1.001 && $y + $size * QrStyle::blockAspect($style) * ($width / $height) <= 1.001;

                if (! $fits) {
                    $validator->errors()->add('qr_size', 'The QR goes past the edge of the image. Move it or make it smaller.');

                    return;
                }

                // The artwork may print narrower than the sticker when a fixed height makes it fit by height.
                $artMm = QrDesign::artWidthMm((int) $this->input('width_mm'), $this->filled('height_mm') ? (int) $this->input('height_mm') : null, $width, $height);
                $printedMm = $size * QrStyle::qrFraction($style) * $artMm;
                if ($printedMm < self::MIN_QR_MM) {
                    $validator->errors()->add('qr_size', sprintf(
                        'The QR would print only %d mm wide. Make it at least %d mm (bigger QR, thinner frame or a wider sticker) so phones can scan it.',
                        floor($printedMm),
                        self::MIN_QR_MM,
                    ));
                }
            },
        ];
    }

    /** The validated style with every key filled in. */
    public function style(): array
    {
        return QrStyle::normalise($this->input('style'));
    }

    /** Updating a design that already has a logo, without asking to remove it. */
    public function keepsLogo(): bool
    {
        return (bool) $this->route('qrDesign')?->logo_path && ! $this->boolean('remove_logo');
    }

    /** Pixel size of the new upload, or of the design's current image when none was sent. */
    public function imageSize(): array
    {
        if ($this->hasFile('image')) {
            $info = @getimagesize($this->file('image')->getRealPath());

            return $info ? [$info[0], $info[1]] : [0, 0];
        }

        $design = $this->route('qrDesign');

        return $design ? [$design->image_width, $design->image_height] : [0, 0];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Upload a background image.',
            'image.mimes' => 'Upload a JPG, PNG or WebP image.',
            'image.max' => 'That image is over 5 MB. Try a smaller one.',
            'image.dimensions' => 'That image is too small. Use one at least 300 × 300 pixels.',
            'logo.mimes' => 'Upload the logo as a JPG, PNG or WebP.',
            'logo.max' => 'That logo is over 1 MB. Try a smaller one.',
            'logo.dimensions' => 'That logo is too small. Use one at least 64 × 64 pixels.',
            'style.*.regex' => 'Pick a colour as a hex code, e.g. #1A2B3C.',
            'style.center_text.required_if' => 'Type the text for the middle of the QR.',
            'style.center_text.max' => 'Keep the middle text to 12 characters so the QR still scans.',
            'style.caption_text.required_unless' => 'Type the caption, or turn the caption off.',
        ];
    }
}
