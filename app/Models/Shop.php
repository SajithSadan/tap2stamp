<?php

namespace App\Models;

use App\Support\HeaderStyle;
use App\Support\ShopContact;
use App\Support\ThemeCatalog;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'google_review_direct',
        'instagram_url',
        'wifi_ssid',
        'wifi_password',
        'product_ordered_at',
        'show_card_link',
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

    protected static function booted(): void
    {
        // Orders placed before the shop had an address (or arranged by the
        // admin first) pick it up once one is saved - unless already posted.
        static::saved(function (Shop $shop) {
            if ($shop->wasChanged(['address_line1', 'address_line2', 'town', 'postcode', 'delivery_address']) && $shop->deliveryAddress()) {
                $shop->orders()
                    ->whereNull('delivery_address')
                    ->whereNull('dispatched_at')
                    ->update(['delivery_address' => $shop->deliveryAddress()]);
            }
        });
    }

    /**
     * A free card link based on $source ("Corner Bakery" -> corner-bakery,
     * or corner-bakery-2 when that's taken). Used to auto-suggest the link
     * on the admin "Add shop" form; the unique rule still has the last word.
     */
    public static function suggestSlug(string $source): string
    {
        $base = Str::limit(Str::slug($source), 140, '') ?: 'shop';
        $slug = $base;

        for ($n = 2; static::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    /**
     * A few free card links to pick from for a shop name: the full name,
     * the name without filler words ("shop", "the", "ltd"…), run together,
     * and with a short ending. Only links nobody has yet, in that order.
     */
    public static function slugIdeas(string $name, int $limit = 4): array
    {
        $words = array_values(array_filter(explode('-', Str::limit(Str::slug($name), 60, ''))));
        if ($words === []) {
            return [];
        }

        $filler = ['the', 'and', 'shop', 'shops', 'store', 'stores', 'ltd', 'limited', 'co', 'uk'];
        $core = array_values(array_diff($words, $filler)) ?: $words;
        $coreSlug = implode('-', $core);

        $candidates = array_values(array_unique(array_filter([
            implode('-', $words),
            $coreSlug,
            implode('', $core),
            count($core) > 1 ? $core[0] : null,
            "{$coreSlug}-rewards",
            "{$coreSlug}-club",
            "{$coreSlug}-card",
        ])));

        $taken = static::whereIn('slug', $candidates)->pluck('slug')->all();

        return array_slice(array_values(array_diff($candidates, $taken)), 0, $limit);
    }

    protected function casts(): array
    {
        return [
            'max_stamps' => 'integer',
            'theme_custom' => 'array',
            'header_style' => 'array',
            'theme_in_dashboard' => 'boolean',
            'google_review_direct' => 'boolean',
            'product_ordered_at' => 'datetime',
            'show_card_link' => 'boolean',
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

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Where an order gets posted: the separate delivery address if given,
     * else the shop address. Null when neither is filled in (older shops).
     */
    public function deliveryAddress(): ?string
    {
        if (filled($this->delivery_address)) {
            return $this->delivery_address;
        }

        $address = collect([$this->address_line1, $this->address_line2, $this->town, $this->postcode])
            ->filter()
            ->implode(', ');

        return $address !== '' ? $address : null;
    }

    /**
     * A free card link (/s/{slug}) made from the shop name: "Bean There" →
     * bean-there, then bean-there-2, -3… if taken. Owners never pick it -
     * they don't see the link at all unless the admin allows it.
     */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;

        for ($n = 2; static::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    /**
     * Business contact + location for the forms, with the contact person and
     * email falling back to the owner's login name/email when not given, so
     * nobody has to type their email twice.
     */
    public function contactDetails(): array
    {
        return [
            ...$this->only(ShopContact::FIELDS),
            'contact_name' => $this->contact_name ?: $this->owner?->name,
            'contact_email' => $this->contact_email ?: $this->owner?->email,
        ];
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
