<?php

namespace App\Models;

use App\Support\HeaderStyle;
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
        'contact_name',
        'contact_email',
        'contact_phone',
        'address_line1',
        'address_line2',
        'town',
        'postcode',
        'delivery_address',
        'theme',
        'theme_custom',
        'header_style',
        'theme_in_dashboard',
        'stamp_icon',
        'signup_icon',
        'banner_path',
        'logo_path',
        'google_review_url',
        'instagram_url',
        'wifi_ssid',
        'wifi_password',
    ];

    /** Stamps-for-a-reward range. Keep in sync with MIN_STAMPS / MAX_STAMPS in Components/Dashboard/ShopFields.jsx. */
    public const MIN_STAMPS = 3;

    public const MAX_STAMPS = 20;

    /**
     * The one validation rule for `max_stamps`, shared by onboarding, the
     * owner's Settings and the admin "Add shop" form.
     *
     * @return list<string>
     */
    public static function maxStampsRules(): array
    {
        return ['required', 'integer', 'between:'.self::MIN_STAMPS.','.self::MAX_STAMPS];
    }

    protected function casts(): array
    {
        return [
            'max_stamps' => 'integer',
            'theme_custom' => 'array',
            'header_style' => 'array',
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

    /** Card page header text colour, banner tint and title shadow, defaults filled in. */
    public function headerStyle(): array
    {
        return HeaderStyle::resolve($this->header_style);
    }

    /** Public URL of the owner's banner photo, or null for the default colour banner. */
    public function bannerUrl(): ?string
    {
        return $this->banner_path ? Storage::disk('uploads')->url($this->banner_path) : null;
    }

    /** The shop's logo for the round badge on the card page (null = the store icon). */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('uploads')->url($this->logo_path) : null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
