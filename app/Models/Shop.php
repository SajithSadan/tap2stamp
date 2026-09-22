<?php

namespace App\Models;

use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'google_review_url',
        'instagram_url',
        'wifi_ssid',
        'wifi_password',
    ];

    protected function casts(): array
    {
        return [
            'max_stamps' => 'integer',
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
