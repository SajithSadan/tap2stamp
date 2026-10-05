<?php

namespace App\Models;

use Database\Factories\CustomerShopCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerShopCard extends Model
{
    /** @use HasFactory<CustomerShopCardFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'shop_id',
        'current_stamps',
        'rewards_claimed',
        'last_stamped_at',
        'marketing_consent',
        'marketing_consent_at',
        'marketing_unsubscribe_token',
        'marketing_opted_out_at',
    ];

    protected function casts(): array
    {
        return [
            'current_stamps' => 'integer',
            'rewards_claimed' => 'integer',
            'last_stamped_at' => 'datetime',
            'marketing_consent' => 'boolean',
            'marketing_consent_at' => 'datetime',
            'marketing_opted_out_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** The secret in this card's unsubscribe link, made the first time it's needed. */
    public function unsubscribeToken(): string
    {
        if (! $this->marketing_unsubscribe_token) {
            $this->update(['marketing_unsubscribe_token' => Str::random(32)]);
        }

        return $this->marketing_unsubscribe_token;
    }
}
