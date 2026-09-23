<?php

namespace App\Models;

use App\Enums\ActionType;
use Database\Factories\StampLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampLog extends Model
{
    /** @use HasFactory<StampLogFactory> */
    use HasFactory;

    /**
     * Log entries are immutable — no updated_at column exists.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'shop_id',
        'staff_member_id',
        'action_type',
    ];

    protected function casts(): array
    {
        return [
            'action_type' => ActionType::class,
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

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }
}
