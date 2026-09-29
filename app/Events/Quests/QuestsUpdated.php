<?php

namespace App\Events\Quests;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells the player's page which runs changed and what to announce.
 */
class QuestsUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array<int, array<string, mixed>>  $runs  runs as PlayerRunView shows them
     * @param  array<int, array{type: string, questTitle: string, text: string}>  $notices
     */
    public function __construct(public int $sessionId, public array $runs, public array $notices = []) {}

    /**
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("world-session.{$this->sessionId}")];
    }

    public function broadcastAs(): string
    {
        return 'quests.updated';
    }

    /**
     * @return array{runs: array<int, array<string, mixed>>, notices: array<int, array<string, string>>}
     */
    public function broadcastWith(): array
    {
        return ['runs' => $this->runs, 'notices' => $this->notices];
    }
}
