<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'phone',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $customer): void {
            $customer->uuid ??= (string) Str::uuid();
        });
    }

    public function cards(): HasMany
    {
        return $this->hasMany(CustomerShopCard::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
