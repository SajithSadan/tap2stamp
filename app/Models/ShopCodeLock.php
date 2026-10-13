<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A browser (browser_id) or a whole network (ip) that typed too many wrong
 * shop codes. Stays locked until an admin unlocks it (App\Services\ShopCodeJoin).
 */
class ShopCodeLock extends Model
{
    protected $fillable = ['browser_id', 'ip', 'user_agent', 'unlocked_at', 'unlocked_by'];

    protected function casts(): array
    {
        return ['unlocked_at' => 'datetime'];
    }

    public function unlockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    /** @param  Builder<ShopCodeLock>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('unlocked_at');
    }

    /** The short id shown on the locked phone, so the admin can find it here. */
    public static function shortId(string $browserId): string
    {
        return strtoupper(substr($browserId, 0, 8));
    }
}
