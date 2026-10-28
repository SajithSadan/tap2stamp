<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One prize of a reward game, with its chance (weight) and optional stock / daily limit. */
class RewardGamePrize extends Model
{
    protected $fillable = [
        'title',
        'detail',
        'kind',
        'bonus_stamps',
        'weight',
        'stock',
        'daily_limit',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'bonus_stamps' => 'integer',
            'weight' => 'integer',
            'stock' => 'integer',
            'daily_limit' => 'integer',
            'won_count' => 'integer',
            'position' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(RewardGame::class, 'reward_game_id');
    }
}
