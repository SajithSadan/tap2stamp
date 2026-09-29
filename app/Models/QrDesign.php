<?php

namespace App\Models;

use App\Support\QrStyle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A printable sticker design: background image + where the QR block sits on
 * it + how the QR looks (QrStyle). Used when printing QR codes; the codes
 * themselves never change.
 */
class QrDesign extends Model
{
    /** Printed sticker width limits, in mm (A4 is 210 wide, minus margins). */
    public const MIN_WIDTH_MM = 30;

    public const MAX_WIDTH_MM = 186;

    /** Printed height limits, in mm (A4 is 297 tall, minus margins and the sheet header). */
    public const MIN_HEIGHT_MM = 30;

    public const MAX_HEIGHT_MM = 265;

    /** Standard print sizes, picked in the editor. The artwork is fitted inside, never stretched. */
    public const PRESETS = [
        'stand' => ['label' => 'Stand', 'width_mm' => 90, 'height_mm' => 140],
        'table' => ['label' => 'Table', 'width_mm' => 60, 'height_mm' => 60],
    ];

    protected $fillable = [
        'name',
        'image_path',
        'image_width',
        'image_height',
        'qr_x',
        'qr_y',
        'qr_size',
        'width_mm',
        'preset',
        'height_mm',
        'style',
        'logo_path',
    ];

    protected function casts(): array
    {
        return [
            'image_width' => 'integer',
            'image_height' => 'integer',
            'qr_x' => 'float',
            'qr_y' => 'float',
            'qr_size' => 'float',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'style' => 'array',
        ];
    }

    /** Every style key filled in - designs saved before styling get the plain look. */
    public function fullStyle(): array
    {
        return QrStyle::normalise($this->style);
    }

    /**
     * Printed width (mm) of the artwork itself: the sticker's width, unless
     * a fixed height makes the artwork fit by height instead (it's fitted
     * inside the sticker, never stretched). Mirrors artRect() in lib/qrPrint.js.
     */
    public static function artWidthMm(int $widthMm, ?int $heightMm, int $imageWidth, int $imageHeight): float
    {
        return $heightMm ? min($widthMm, $heightMm * $imageWidth / $imageHeight) : $widthMm;
    }

    /** Relative URL (/uploads/...), like shop banners - see config/filesystems.php "uploads". */
    public function imageUrl(): string
    {
        return Storage::disk('uploads')->url($this->image_path);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('uploads')->url($this->logo_path) : null;
    }

    /** Everything the pages and the client-side PDF/PNG builder need. */
    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image_url' => $this->imageUrl(),
            'image_width' => $this->image_width,
            'image_height' => $this->image_height,
            'qr_x' => $this->qr_x,
            'qr_y' => $this->qr_y,
            'qr_size' => $this->qr_size,
            'width_mm' => $this->width_mm,
            'preset' => $this->preset,
            'height_mm' => $this->height_mm,
            'style' => $this->fullStyle(),
            'logo_url' => $this->logoUrl(),
        ];
    }
}
