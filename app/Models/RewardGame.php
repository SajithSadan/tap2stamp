<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\RewardGames;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A shop's reward game (App\Support\RewardGames): a type, its prizes and
 * when customers get a play. Built and published by the owner; only `live`
 * games give plays.
 */
class RewardGame extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'name',
        'headline',
        'type',
        'status',
        'trigger',
        'trigger_value',
        'starts_on',
        'ends_on',
        'max_plays_per_customer',
        'plays_period',
        'after_win',
        'voucher_days',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'trigger_value' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'max_plays_per_customer' => 'integer',
            'voucher_days' => 'integer',
            'position' => 'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(RewardGamePrize::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Where the play limit counts from: today / this week / this month (UK
     * time), or null = all time ("in total", or a game saved before periods).
     */
    public function playsWindowStart(CarbonInterface $now): ?CarbonInterface
    {
        $uk = $now->copy()->timezone('Europe/London');

        return match ($this->plays_period) {
            'day' => $uk->startOfDay(),
            'week' => $uk->startOfWeek(),
            'month' => $uk->startOfMonth(),
            default => null,
        };
    }

    /**
     * May a customer play now? $plays = their plays of this game since
     * playsWindowStart(); $lastWinAt = when they last won a prize from it
     * ("no win" isn't a win). The one place these rules live - the play step
     * asks it.
     */
    public function canPlay(int $plays, ?CarbonInterface $lastWinAt, CarbonInterface $now): bool
    {
        if ($this->max_plays_per_customer !== null && $plays >= $this->max_plays_per_customer) {
            return false;
        }

        return match ($lastWinAt ? $this->after_win : 'continue') {
            'game' => false,
            'day' => ! $lastWinAt->copy()->timezone('Europe/London')->isSameDay($now->copy()->timezone('Europe/London')),
            default => true,
        };
    }

    /** The scratch card's image (what customers scratch off), or null for plain silver. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('uploads')->url($this->image_path) : null;
    }

    public function activityLabel(): string
    {
        return $this->name;
    }

    /** "Every 3rd stamp", "When the card is full"… */
    public function triggerLabel(): string
    {
        if ($this->trigger !== RewardGames::EVERY_N_STAMPS) {
            return RewardGames::TRIGGERS[$this->trigger] ?? $this->trigger;
        }

        $n = (int) $this->trigger_value;

        return $n <= 1 ? 'Every stamp' : 'Every '.$n.match (true) {
            in_array($n % 100, [11, 12, 13], true) => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        }.' stamp';
    }

    /**
     * The game as the owner's list and builder use it.
     *
     * @return array<string, mixed>
     */
    public function toBuilder(): array
    {
        return [
            ...$this->only(['id', 'name', 'headline', 'type', 'status', 'trigger', 'trigger_value', 'max_plays_per_customer', 'plays_period', 'after_win', 'voucher_days']),
            'image_url' => $this->imageUrl(),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'trigger_label' => $this->triggerLabel(),
            'prizes' => $this->prizes->map(fn (RewardGamePrize $prize) => $prize->only([
                'id', 'title', 'detail', 'kind', 'bonus_stamps', 'weight', 'stock', 'daily_limit', 'won_count',
            ]))->values()->all(),
        ];
    }
}
