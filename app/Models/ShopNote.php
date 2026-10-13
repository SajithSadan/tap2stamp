<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An admin's note on a shop (calls, objections, what was agreed). Admin only. */
class ShopNote extends Model
{
    public const MAX_LENGTH = 2000;

    protected $fillable = ['body', 'user_id'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
