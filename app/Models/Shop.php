<?php

namespace App\Models;

use App\Support\ThemeCatalog;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'max_stamps',
        'reward_title',
        'theme',
        'theme_custom',
        'theme_in_dashboard',
        'stamp_icon',
        'banner_path',
        'google_review_url',
        'instagram_url',
        'wifi_ssid',
        'wifi_password',
    ];

    protected function casts(): array
    {
        return [
            'max_stamps' => 'integer',
            'theme_custom' => 'array',
            'theme_in_dashboard' => 'boolean',
        ];
    }

    public function cards(): HasMany
    {
        return $this->hasMany(CustomerShopCard::class);
    }

    public function stampLogs(): HasMany
    {
        return $this->hasMany(StampLog::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function staffDevices(): HasMany
    {
        return $this->hasMany(StaffDevice::class);
    }

    public function staffMembers(): HasMany
    {
        return $this->hasMany(StaffMember::class);
    }

    /** The look customers see: the catalog theme plus any of the owner's own tweaks. */
    public function appliedTheme(): array
    {
        return ThemeCatalog::forShop($this->theme, $this->theme_custom);
    }

    /** Public URL of the owner's banner photo, or null for the default colour banner. */
    public function bannerUrl(): ?string
    {
        return $this->banner_path ? Storage::disk('uploads')->url($this->banner_path) : null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
