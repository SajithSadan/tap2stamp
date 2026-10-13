<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One Gemini request - written by App\Services\AiUsage only. */
class AiUsageLog extends Model
{
    public const UPDATED_AT = null;

    /** Feature key → label (the admin AI usage page). */
    public const FEATURES = [
        'menu_import' => 'Menu photo import',
        'photo_check' => 'Menu photo check',
        'stock_command' => 'Stock-out command',
        'crm_briefing' => 'Follow-up briefing',
        'call_prep' => 'Call prep',
    ];

    protected $guarded = ['id'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
