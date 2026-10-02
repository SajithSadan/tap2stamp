<?php

namespace App\Models;

use App\Enums\OrderStage;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'product_id',
        'user_id',
        'product_name',
        'quantity',
        'unit_price_pence',
        'total_pence',
        'list_total_pence',
        'coupon_id',
        'coupon_code',
        'payment_method',
        'status',
        'stripe_session_id',
        'stripe_payment_intent',
        'delivery_address',
        'note',
        'paid_at',
        'processing_at',
        'dispatched_at',
        'delivered_at',
        'courier',
        'tracking_number',
        'tracking_url',
    ];

    /** Most of one product a shop can order at once. Keep in sync with MAX_QUANTITY in resources/js/lib/money.js. */
    public const MAX_QUANTITY = 20;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_pence' => 'integer',
            'total_pence' => 'integer',
            'list_total_pence' => 'integer',
            'payment_method' => PaymentMethod::class,
            'status' => OrderStatus::class,
            'paid_at' => 'datetime',
            'processing_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    /**
     * An order the admin arranged that's waiting for the shop's bank transfer
     * (or cash). Not an abandoned Stripe checkout.
     */
    public function awaitingManualPayment(): bool
    {
        return $this->status === OrderStatus::Pending && $this->payment_method !== PaymentMethod::Stripe;
    }

    /** How far a paid order has got; null while payment isn't confirmed. */
    public function stage(): ?OrderStage
    {
        if (! $this->isPaid()) {
            return null;
        }

        foreach (array_reverse(OrderStage::cases()) as $stage) {
            if ($this->{$stage->column()} !== null) {
                return $stage;
            }
        }

        return OrderStage::Received;
    }

    /**
     * What the owner sees: product, amount, the stage tracker and the parcel
     * tracking. No admin notes or who recorded it.
     */
    public function summary(): array
    {
        $date = fn ($at) => $at?->timezone('Europe/London')->format('j M Y');

        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'unit_price_pence' => $this->unit_price_pence,
            'total_pence' => $this->total_pence,
            // The normal price; more than total_pence when a discount was agreed.
            'list_total_pence' => $this->list_total_pence ?? $this->total_pence,
            'coupon_code' => $this->coupon_code,
            'payment_method' => $this->payment_method->value,
            'payment_label' => $this->payment_method->label(),
            'status' => $this->status->value,
            'stage' => $this->stage()?->value,
            'awaiting_payment' => $this->awaitingManualPayment(),
            'reference' => PaymentMethod::reference($this->id),
            // One entry per stage, with the date it was reached (null = not yet).
            'steps' => collect(OrderStage::cases())->map(fn (OrderStage $stage) => [
                'key' => $stage->value,
                'label' => $stage->label(),
                'date' => $this->isPaid() ? $date($this->{$stage->column()}) : null,
            ])->all(),
            'courier' => $this->courier,
            'tracking_number' => $this->tracking_number,
            'tracking_url' => $this->tracking_url,
            'delivery_address' => $this->delivery_address,
            'created_label' => $date($this->created_at),
        ];
    }
}
