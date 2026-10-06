<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One thing an admin, owner or staff member did. Written by App\Services\ActivityLogger. */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_type',
        'user_id',
        'staff_member_id',
        'actor_name',
        'as_owner',
        'shop_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'as_owner' => 'boolean',
            'changes' => 'array',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
