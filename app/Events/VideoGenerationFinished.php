<?php

namespace App\Events;

use App\Models\Video;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class VideoGenerationFinished implements ShouldBroadcastNow
{
    use Dispatchable;

    public int $userId;

    public int $conversationId;

    public int $assistantId;

    public string $assistantName;

    public string $status;

    public ?string $failureReason;

    public function __construct(Video $video)
    {
        $conversation = $video->videoable->conversation;
        $assistantUser = $conversation->assistantUser();

        $this->userId = $assistantUser->user_id;
        $this->conversationId = $conversation->id;
        $this->assistantId = $assistantUser->assistant_id;
        $this->assistantName = $assistantUser->assistant->name;
        $this->status = $video->status->value;
        $this->failureReason = $video->failure_reason;
    }

    /**
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'video-generation.finished';
    }

    /**
     * @return array{conversationId: int, assistantId: int, assistantName: string, status: string, failureReason: ?string}
     */
    public function broadcastWith(): array
    {
        return [
            'conversationId' => $this->conversationId,
            'assistantId' => $this->assistantId,
            'assistantName' => $this->assistantName,
            'status' => $this->status,
            'failureReason' => $this->failureReason,
        ];
    }
}
