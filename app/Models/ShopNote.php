<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An admin's note on a shop (calls, objections, what was agreed). Admin only. */
class ShopNote extends Model
{
    public const MAX_LENGTH = 2000;

    protected $fillable = ['body', 'user_id', 'lead_status'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The shape the admin's notes lists show (shop settings → Account, the Shops grid's dialog). */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            // The follow-up status it was written with (App\Support\LeadStatus, null = none).
            'lead_status' => $this->lead_status,
            'author' => $this->author?->name,
            'at_label' => $this->created_at->timezone('Europe/London')->format('j M Y, H:i'),
        ];
    }
}
