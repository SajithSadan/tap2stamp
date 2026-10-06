<?php

namespace App\Models\Concerns;

use App\Services\ActivityLogger;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes created / updated / deleted to the activity log, with each changed
 * field's before and after (App\Services\ActivityLogger skips it when no
 * admin, owner or staff member is acting).
 *
 * A model can set:
 *  - $activityIgnore: fields never logged (bookkeeping like last_used_at);
 *    a save that only touched these isn't logged at all
 *  - $activitySecret: fields logged as "changed", never their values
 *  - $activityEvents: which of created/updated/deleted to log
 *  - activityLabel(): the name shown in the log ("Sam", "Order #12")
 *  - activityShopId(): the shop it belongs to (default: its shop_id)
 *
 * Only saves through the model are seen - mass query updates/deletes need
 * an explicit ActivityLogger::record() where they happen.
 */
trait RecordsActivity
{
    private const ALWAYS_IGNORED = ['created_at', 'updated_at', 'remember_token'];

    public static function bootRecordsActivity(): void
    {
        $events = property_exists(static::class, 'activityEvents') ? (new static)->activityEvents : ['created', 'updated', 'deleted'];

        foreach ($events as $event) {
            static::$event(function (Model $model) use ($event) {
                $model->recordActivity($event);
            });
        }
    }

    protected function recordActivity(string $event): void
    {
        $changes = match ($event) {
            'updated' => $this->activityChanges(),
            'created' => $this->activitySnapshot(fn ($value) => [null, $value]),
            'deleted' => $this->activitySnapshot(fn ($value) => [$value, null]),
        };

        // Only bookkeeping fields changed - nothing worth a log line.
        if ($event === 'updated' && $changes === []) {
            return;
        }

        $type = ActivityLogger::subjectType($this);
        $noun = Str::of($type)->replace('_', ' ')->toString();
        $label = $this->activityLabel();
        $verb = ['created' => 'Added', 'updated' => 'Changed', 'deleted' => 'Removed'][$event];

        ActivityLogger::record(
            "{$type}.{$event}",
            trim("{$verb} {$noun} {$label}").($event === 'updated' ? ': '.implode(', ', array_map(fn ($f) => str_replace('_', ' ', $f), array_keys($changes))) : ''),
            $this->activityShopId(),
            $this,
            $changes,
        );
    }

    /** @return array<string, array{0: mixed, 1: mixed}> */
    protected function activityChanges(): array
    {
        $changes = [];

        foreach (array_keys($this->getChanges()) as $field) {
            if ($this->isActivityIgnored($field)) {
                continue;
            }

            $changes[$field] = $this->isActivitySecret($field)
                ? ['•••', 'changed']
                : [$this->activityValue($this->getOriginal($field)), $this->activityValue($this->getAttribute($field))];
        }

        return $changes;
    }

    /** @return array<string, array{0: mixed, 1: mixed}> */
    protected function activitySnapshot(callable $pair): array
    {
        $changes = [];

        foreach ($this->getAttributes() as $field => $value) {
            if ($this->isActivityIgnored($field) || $field === $this->getKeyName() || $value === null || $value === '') {
                continue;
            }

            $changes[$field] = $pair($this->isActivitySecret($field) ? 'set' : $this->activityValue($this->getAttribute($field)));
        }

        return $changes;
    }

    private function activityValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i'),
            $value instanceof Arrayable => $value->toArray(),
            is_string($value) && json_validate($value) && in_array($value[0] ?? '', ['{', '['], true) => json_decode($value, true),
            default => $value,
        };
    }

    private function isActivityIgnored(string $field): bool
    {
        return in_array($field, [...self::ALWAYS_IGNORED, ...($this->activityIgnore ?? [])], true);
    }

    private function isActivitySecret(string $field): bool
    {
        return in_array($field, $this->activitySecret ?? [], true);
    }

    public function activityLabel(): string
    {
        return (string) ($this->getAttribute('name') ?? '#'.$this->getKey());
    }

    public function activityShopId(): ?int
    {
        return $this->getAttribute('shop_id');
    }
}
