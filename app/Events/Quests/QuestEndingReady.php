<?php

namespace App\Events\Quests;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A run's or a campaign's ending was written, or couldn't be.
 */
class QuestEndingReady implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array{runId?: int, campaignId?: int, title: string, endingStatus: string, ending: ?array<string, mixed>}  $payload
     */
    public function __construct(public int $sessionId, public array $payload) {}

    /**
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("world-session.{$this->sessionId}")];
    }

    public function broadcastAs(): string
    {
        return 'quests.ending';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
