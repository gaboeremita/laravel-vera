<?php

namespace App\Events;

use App\Models\Video;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class VideoGenerationStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public int $conversationId;

    public int $messageId;

    /**
     * @var array<string, mixed>
     */
    public array $video;

    public function __construct(Video $video)
    {
        $this->conversationId = $video->videoable->conversation_id;
        $this->messageId = $video->videoable_id;
        $this->video = $video->toChatPayload();
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
        return 'video-generation.updated';
    }

    /**
     * @return array{messageId: int, video: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return [
            'messageId' => $this->messageId,
            'video' => $this->video,
        ];
    }
}
