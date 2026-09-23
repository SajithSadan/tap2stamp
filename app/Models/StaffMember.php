<?php

namespace App\Models;

use Database\Factories\StaffMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffMember extends Model
{
    /** @use HasFactory<StaffMemberFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'name',
        'pin_hash',
        'deactivated_at',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'deactivated_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function stampLogs(): HasMany
    {
        return $this->hasMany(StampLog::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }
}
