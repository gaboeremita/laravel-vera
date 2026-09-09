<?php

namespace App\Events;

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Cache;

class AvatarBackgroundStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public bool $inProgress;

    public ?string $status;

    /**
     * @var ?array<string, mixed>
     */
    public ?array $background;

    public function __construct(public int $conversationId)
    {
        $this->status = Cache::get(GenerateAvatarBackground::progressKeyFor($conversationId));
        $this->inProgress = $this->status !== null;
        $this->background = Cache::get(GenerateAvatarBackground::cacheKeyFor($conversationId));
    }

    /**
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'avatar-background.updated';
    }

    /**
     * @return array{in_progress: bool, status: ?string, background: ?array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return [
            'in_progress' => $this->inProgress,
            'status' => $this->status,
            'background' => $this->background,
        ];
    }
}
