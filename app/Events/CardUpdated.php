<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Broadcast on a PUBLIC channel (no auth/routes/channels.php needed) scoped
 * to one customer at one shop, so only someone with that exact card open
 * receives it. Deliberately minimal payload - no name, no phone, nothing
 * beyond what the card UI itself already shows.
 */
class CardUpdated implements ShouldBroadcastNow
{
    public function __construct(
        public string $customerUuid,
        public int $shopId,
        public int $stamps,
        public int $maxStamps,
        public string $action,
        public bool $rewardReady,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("card.{$this->customerUuid}.{$this->shopId}");
    }

    public function broadcastAs(): string
    {
        return 'card.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'stamps' => $this->stamps,
            'max_stamps' => $this->maxStamps,
            'action' => $this->action,
            'reward_ready' => $this->rewardReady,
        ];
    }
}
