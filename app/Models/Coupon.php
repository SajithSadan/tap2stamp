<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A discount code owners can use when ordering products: a percentage or a
 * fixed amount off the order total. Only paid orders count as a use - an
 * abandoned checkout doesn't use it up. Never deleted (past orders point at
 * it), switched off instead.
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory, RecordsActivity;

    public function activityLabel(): string
    {
        return $this->code;
    }

    public const PERCENT = 'percent';

    public const FIXED = 'fixed';

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'product_id',
        'max_uses',
        'once_per_shop',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'max_uses' => 'integer',
            'once_per_shop' => 'boolean',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function paidOrders(): HasMany
    {
        return $this->orders()->where('status', OrderStatus::Paid);
    }

    public static function normalise(string $code): string
    {
        return strtoupper(trim($code));
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', static::normalise($code))->first();
    }

    /** "10% off" / "£5 off". */
    public function label(): string
    {
        return $this->discount_type === self::PERCENT
            ? "{$this->discount_value}% off"
            : '£'.number_format($this->discount_value / 100, $this->discount_value % 100 === 0 ? 0 : 2).' off';
    }

    /**
     * Pence off an order of $totalPence - never more than the total.
     * Keep in sync with couponDiscount() in resources/js/lib/money.js.
     */
    public function discountFor(int $totalPence): int
    {
        $off = $this->discount_type === self::PERCENT
            ? (int) round($totalPence * $this->discount_value / 100)
            : $this->discount_value;

        return min($totalPence, $off);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsedUp(): bool
    {
        return $this->max_uses !== null && $this->paidOrders()->count() >= $this->max_uses;
    }

    /**
     * Why $shop can't use this coupon on $product, or null when it can.
     * Messages are shown to the owner as they are.
     */
    public function problemFor(Shop $shop, Product $product): ?string
    {
        return match (true) {
            ! $this->is_active, $this->isExpired() => 'This coupon code has expired.',
            $this->product_id !== null && $this->product_id !== $product->id => "This coupon code can't be used on {$product->name}.",
            $this->isUsedUp() => 'This coupon code has been fully used.',
            $this->once_per_shop && $this->paidOrders()->where('shop_id', $shop->id)->exists() => "You've already used this coupon code.",
            default => null,
        };
    }

    /** What the order forms need to show the discount (the server works out the real charge). */
    public function summary(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label(),
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
        ];
    }
}
