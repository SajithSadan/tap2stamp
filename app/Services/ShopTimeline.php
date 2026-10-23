<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Models\ActivityLog;
use App\Models\Shop;
use App\Models\ShopNote;
use App\Models\ShopTask;
use App\Support\LeadStatus;
use Illuminate\Support\Carbon;

/**
 * One shop's story for the admin (Account tab, call prep): usage milestones
 * (signed up, first stamp, 10th customer, first reward, trial ended), the
 * admin's notes and follow-ups, and the activity log - minus the per-scan
 * noise (each stamp, staff PIN sign-ins), which the milestones summarise.
 * Newest first.
 */
class ShopTimeline
{
    /** Activity actions left out: one per scan / shift, and already summed up by the milestones. */
    public const NOISE = ['stamp.added', 'stamp.reward_redeemed', 'staff.signed_in', 'staff.signed_out', 'menu.availability'];

    /** @return list<array{at: string, at_label: string, kind: string, action: ?string, title: string, detail: ?string, who: ?string}> */
    public function for(Shop $shop, int $limit = 60): array
    {
        $entries = collect();
        $add = fn (?Carbon $at, string $kind, string $title, ?string $detail = null, ?string $who = null, ?string $action = null) => $at && $entries->push(compact('at', 'kind', 'title', 'detail', 'who', 'action'));

        // --- Milestones ---------------------------------------------------------
        $owner = $shop->owner;
        $add($owner?->created_at, 'milestone', 'Owner signed up', $owner?->email);
        $add($shop->created_at, 'milestone', 'Shop set up', $shop->name);

        $firstStamp = $shop->stampLogs()->where('action_type', ActionType::StampAdded)->min('created_at');
        $add($firstStamp ? Carbon::parse($firstStamp) : null, 'milestone', 'First stamp given');

        $tenth = $shop->cards()->orderBy('created_at')->orderBy('id')->skip(9)->value('created_at');
        $add($tenth ? Carbon::parse($tenth) : null, 'milestone', '10th customer joined');

        $firstReward = $shop->stampLogs()->where('action_type', ActionType::RewardRedeemed)->min('created_at');
        $add($firstReward ? Carbon::parse($firstReward) : null, 'milestone', 'First reward given');

        if ($shop->trial_ends_at?->isPast()) {
            $add($shop->trial_ends_at, 'milestone', 'Free trial ended');
        }

        // --- Notes and follow-ups ------------------------------------------------
        $shop->notes()->with('author:id,name')->latest()->limit($limit)->get()
            ->each(fn (ShopNote $note) => $add($note->created_at, 'note', LeadStatus::label($note->lead_status) ? 'Note · '.LeadStatus::label($note->lead_status) : 'Note', $note->body, $note->author?->name));

        $shop->tasks()->with('creator:id,name')->latest()->limit($limit)->get()->each(function (ShopTask $task) use ($add) {
            $add($task->created_at, 'task', 'Follow-up added: '.$task->title, $task->due_on ? 'Due '.$task->due_on->format('j M Y') : null, $task->creator?->name);
            $add($task->done_at, 'task', 'Follow-up done: '.$task->title);
        });

        // --- Activity log (owner, staff and admin actions on this shop) ------------
        ActivityLog::where('shop_id', $shop->id)
            ->whereNotIn('action', self::NOISE)
            ->latest('id')
            ->limit($limit)
            ->get(['action', 'description', 'actor_name', 'actor_type', 'created_at'])
            ->each(fn (ActivityLog $log) => $add($log->created_at, 'activity', $log->description, null, $log->actor_name ? "{$log->actor_name} ({$log->actor_type})" : null, $log->action));

        return $entries
            ->sortByDesc(fn ($e) => $e['at']->getTimestamp())
            ->take($limit)
            ->map(fn ($e) => [
                ...$e,
                'at' => $e['at']->toIso8601String(),
                'at_label' => $e['at']->copy()->timezone('Europe/London')->format('j M Y, H:i'),
            ])
            ->values()
            ->all();
    }
}
