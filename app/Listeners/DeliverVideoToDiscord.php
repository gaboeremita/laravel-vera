<?php

namespace App\Listeners;

use App\Enums\VideoStatus;
use App\Events\VideoGenerationFinished;
use App\Models\Conversation;
use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DeliverVideoToDiscord implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 6;

    public int $timeout = 200;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function shouldQueue(VideoGenerationFinished $event): bool
    {
        return Conversation::whereKey($event->conversationId)->whereNotNull('discord_channel_id')->exists();
    }

    public function handle(VideoGenerationFinished $event): void
    {
        $video = Video::find($event->videoId);
        $conversation = Conversation::find($event->conversationId);

        if ($video === null || $conversation?->discord_channel_id === null) {
            return;
        }

        $apiConfig = config('ai.discord');

        $response = Http::timeout($apiConfig['delivery_timeout'])
            ->withHeaders(['X-Internal-Secret' => $apiConfig['api_secret']])
            ->post("{$apiConfig['api_url']}/assistants/{$event->assistantId}/channels/{$conversation->discord_channel_id}/videos", [
                'replyToMessageId' => $this->replyTarget($video),
                ...($video->status === VideoStatus::Completed
                    ? ['videoUrl' => $video->url]
                    : ['failureReason' => $video->failure_reason]),
            ]);

        if ($response->successful()) {
            return;
        }

        $failure = new RuntimeException("The Discord API service answered {$response->status()}: {$response->body()}");

        if ($response->unauthorized() || $response->unprocessableEntity()) {
            $this->fail($failure);

            return;
        }

        throw $failure;
    }

    public function failed(VideoGenerationFinished $event, Throwable $exception): void
    {
        Log::error('Discord video delivery failed', [
            'video_id' => $event->videoId,
            'conversation_id' => $event->conversationId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * The request is the user message just before the video's message: Discord
     * requests reach the app one at a time, so nothing else lands between them.
     */
    private function replyTarget(Video $video): ?string
    {
        return $video->videoable->conversation->messages()
            ->where('role', 'user')
            ->where('id', '<', $video->videoable_id)
            ->latest('id')
            ->value('discord_message_id');
    }
}
