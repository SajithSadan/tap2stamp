<?php

namespace App\Models;

use Database\Factories\CustomerShopCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected function casts(): array
    {
        return [
            'current_stamps' => 'integer',
            'rewards_claimed' => 'integer',
            'last_stamped_at' => 'datetime',
            'marketing_consent' => 'boolean',
            'marketing_consent_at' => 'datetime',
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
}
