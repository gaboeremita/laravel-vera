<?php

namespace App\Jobs;

use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Services\AvatarBackground\AvatarBackgroundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateAvatarBackground implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public AssistantUser $assistantUser,
        public Conversation $conversation,
        public string $description,
    ) {}

    /**
     * Dispatches the job and marks it in-progress immediately, synchronously —
     * not just once the job itself starts running. Without this, a caller
     * that checks the progress key right after dispatching (e.g. a concurrent
     * page load) could see neither a cache entry nor an in-progress marker
     * during the window before a queue worker picks the job up, and dispatch
     * a duplicate.
     *
     * Swallows any exception the dispatch itself raises: on the `sync` queue
     * driver (as used in tests, and possibly elsewhere), a failing job's
     * exception is re-thrown by the driver back through dispatch() — without
     * this catch, that would break the caller's own request (e.g. sending a
     * chat message) instead of just leaving the background ungenerated, which
     * is exactly what FR-013 says must not happen. failed() has already
     * logged the underlying failure by the time this catch runs.
     */
    public static function dispatchFor(AssistantUser $assistantUser, Conversation $conversation, string $description): void
    {
        Cache::put(self::progressKeyFor($conversation->id), 'Generating scene...', now()->addSeconds(180));

        try {
            self::dispatch($assistantUser, $conversation, $description);
        } catch (Throwable $e) {
            Log::debug('Avatar background dispatch surfaced a synchronous failure; already logged via failed(), not rethrown.', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handle(AvatarBackgroundService $service): void
    {
        Cache::put($this->progressKey(), 'Generating scene...', now()->addSeconds($this->timeout));

        try {
            $result = $service->generate($this->assistantUser, $this->conversation, $this->description);

            Cache::put($this->cacheKey(), [
                'conversation_id' => $this->conversation->id,
                'floor_url' => $result['floor_url'],
                'surroundings_url' => $result['surroundings_url'],
                'source_description' => $result['source_description'],
                'generated_at' => now()->toIso8601String(),
            ], now()->addSeconds(config('ai.avatar_background.cache_ttl')));
        } finally {
            Cache::forget($this->progressKey());
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Failed to generate avatar background', [
            'conversation_id' => $this->conversation->id,
            'error' => $exception->getMessage(),
        ]);
    }

    private function cacheKey(): string
    {
        return self::cacheKeyFor($this->conversation->id);
    }

    private function progressKey(): string
    {
        return self::progressKeyFor($this->conversation->id);
    }

    public static function cacheKeyFor(int $conversationId): string
    {
        return "avatar-background:{$conversationId}";
    }

    public static function progressKeyFor(int $conversationId): string
    {
        return "avatar-background-progress:{$conversationId}";
    }
}
