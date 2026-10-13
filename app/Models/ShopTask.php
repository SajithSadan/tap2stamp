<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A follow-up for the admin ("Call Bean There"), optionally due on a day. */
class ShopTask extends Model
{
    protected $fillable = ['title', 'due_on', 'created_by'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'done_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('done_at');
    }

    /** For the admin pages: what it is, when it's due, and how urgent. */
    public function toRow(): array
    {
        $today = now('Europe/London')->startOfDay();

        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'title' => $this->title,
            'due_on' => $this->due_on?->format('Y-m-d'),
            'due_label' => $this->due_on?->format('j M'),
            // overdue / today / soon (within 7 days) / later / none
            'urgency' => match (true) {
                $this->due_on === null => 'none',
                $this->due_on->lt($today) => 'overdue',
                $this->due_on->isSameDay($today) => 'today',
                $this->due_on->lte($today->copy()->addDays(7)) => 'soon',
                default => 'later',
            },
            'done' => $this->done_at !== null,
            'done_label' => $this->done_at?->timezone('Europe/London')->format('j M'),
        ];
    }
}
