<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\Countries;
use App\Support\Features;
use App\Support\HeaderStyle;
use App\Support\MenuThemes;
use App\Support\ShopContact;
use App\Support\ThemeCatalog;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory, RecordsActivity;

    /** Activity log: product_ordered_at is set by payment, logged as the order. */
    protected array $activityIgnore = ['product_ordered_at'];

    public function activityShopId(): ?int
    {
        return $this->id;
    }

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'max_stamps',
        'reward_title',
        'contact_name',
        'contact_email',
        'contact_phone_code',
        'contact_phone',
        'address_line1',
        'address_line2',
        'town',
        'postcode',
        'country',
        'qr_design_id',
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
        'menu_theme',
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
        static::creating(function (Shop $shop) {
            $shop->menu_slug ??= static::uniqueMenuSlug($shop->name ?? '');
        });

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
            'features' => 'array',
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

    /** The design an overseas owner downloads their assigned QR codes in (null = plain QR). */
    public function qrDesign(): BelongsTo
    {
        return $this->belongsTo(QrDesign::class);
    }

    /**
     * Only UK shops can order our hardware (the counter display). Elsewhere
     * the owner downloads the QR codes the admin assigned to the shop.
     */
    /** The design the owner downloads QR codes in: this shop's own, else the default design (null = plain QR). */
    public function downloadDesign(): ?QrDesign
    {
        return $this->qrDesign ?? QrDesign::defaultDesign();
    }

    public function canOrderProducts(): bool
    {
        return ($this->country ?? Countries::UK) === Countries::UK;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function marketingCampaigns(): HasMany
    {
        return $this->hasMany(MarketingCampaign::class);
    }

    /** Whether this shop has an owner feature (App\Support\Features): its own override, else the platform default. */
    public function hasFeature(string $feature): bool
    {
        return $this->featureOverride($feature) ?? Features::default($feature);
    }

    /** true / false when the admin set it for this shop, null = follows the default. */
    public function featureOverride(string $feature): ?bool
    {
        $override = ($this->features ?? [])[$feature] ?? null;

        return $override === null ? null : (bool) $override;
    }

    public function menuSections(): HasMany
    {
        return $this->hasMany(MenuSection::class)->orderBy('position');
    }

    public function menuItems(): HasManyThrough
    {
        return $this->hasManyThrough(MenuItem::class, MenuSection::class);
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

    /** No look-alikes (0/o, 1/l/i), like the QR sticker codes. */
    private const MENU_CODE_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /**
     * The public menu link: the name + a short random code ("bean-there-7k2q").
     * Never just the name - that's the card link, which owners don't get
     * (see "Owners don't get their card link"), and they do see their menu link.
     */
    public static function uniqueMenuSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 60, '') ?: 'shop';

        do {
            $code = '';
            for ($i = 0; $i < 4; $i++) {
                $code .= self::MENU_CODE_ALPHABET[random_int(0, strlen(self::MENU_CODE_ALPHABET) - 1)];
            }
            $slug = "{$base}-{$code}";
        } while (static::where('menu_slug', $slug)->exists());

        return $slug;
    }

    /** The shop's public menu page. */
    public function menuUrl(): string
    {
        return route('menu.show', $this->menu_slug);
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

    /**
     * The contact number for display, code and number together: "+44 7700900123"
     * (saved separately, see App\Support\ShopContact). Null without a number.
     */
    public function contactPhone(): ?string
    {
        if (blank($this->contact_phone)) {
            return null;
        }

        return $this->contact_phone_code ? "+{$this->contact_phone_code} {$this->contact_phone}" : $this->contact_phone;
    }

    /** The same number for a tel: link ("+447700900123"). */
    public function contactPhoneTel(): ?string
    {
        return $this->contactPhone() ? str_replace(' ', '', $this->contactPhone()) : null;
    }

    /** The look customers see: the catalog theme plus any of the owner's own tweaks. */
    public function appliedTheme(): array
    {
        return ThemeCatalog::forShop($this->theme, $this->theme_custom);
    }

    /** The menu page's look (colours, fonts and layout), picked by the admin. */
    public function menuTheme(): array
    {
        return MenuThemes::resolve($this->menu_theme);
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
