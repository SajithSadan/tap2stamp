<?php

namespace App\Models;

use Database\Factories\StaffDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffDevice extends Model
{
    /** @use HasFactory<StaffDeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'name',
        'token_hash',
        'last_used_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
