<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** One promotion image in the carousel above a shop's menu (App\Services\MenuBanners). */
class MenuBanner extends Model
{
    protected $fillable = ['shop_id', 'image_path', 'position'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function url(): string
    {
        return Storage::disk('uploads')->url($this->image_path);
    }

    /** What the editor and the menu page get. */
    public function toClient(): array
    {
        return ['id' => $this->id, 'url' => $this->url()];
    }
}
