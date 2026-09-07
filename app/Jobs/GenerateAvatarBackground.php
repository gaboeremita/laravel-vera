<?php

namespace App\Jobs;

use App\Events\AvatarBackgroundStatusUpdated;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Services\AvatarBackground\AvatarBackgroundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GenerateAvatarBackground implements ShouldQueue
{
    use Dispatchable, Queueable;

    public const TIMEOUT_SECONDS = 180;

    public int $tries = 1;

    public int $timeout = self::TIMEOUT_SECONDS;

    public function __construct(
        public AssistantUser $assistantUser,
        public Conversation $conversation,
        public string $description,
        public ?string $requestId = null,
    ) {}

    public static function dispatchFor(AssistantUser $assistantUser, Conversation $conversation, string $description): void
    {
        $description = trim($description);
        $request = [
            'id' => (string) Str::uuid(),
            'description' => $description,
        ];

        try {
            $requestToDispatch = Cache::lock(self::dispatchLockKeyFor($conversation->id), 10)
                ->block(5, function () use ($conversation, $request): ?array {
                    $activeRequest = Cache::get(self::activeRequestKeyFor($conversation->id));

                    if ($activeRequest !== null && ($activeRequest['description'] ?? null) === $request['description']) {
                        return null;
                    }

                    self::markActive($conversation->id, $request);

                    return $request;
                });
        } catch (Throwable $e) {
            Log::warning('Failed to coordinate avatar background generation.', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($requestToDispatch !== null) {
            self::dispatchRequest($assistantUser, $conversation, $requestToDispatch);
        }
    }

    public function handle(AvatarBackgroundService $service): void
    {
        if (! $this->beginRequest()) {
            return;
        }

        Cache::put($this->progressKey(), 'Generating scene...', now()->addSeconds(self::requestStateTtl()));

        try {
            $result = $service->generate($this->assistantUser, $this->conversation, $this->description);

            if ($this->isSuperseded()) {
                return;
            }

            Cache::put($this->cacheKey(), [
                'conversation_id' => $this->conversation->id,
                'floor_url' => $result['floor_url'],
                'surroundings_url' => $result['surroundings_url'],
                'source_description' => $result['source_description'],
                'generated_at' => now()->toIso8601String(),
            ], now()->addSeconds(config('ai.avatar_background.cache_ttl')));
            Cache::forget(self::failureKeyFor($this->conversation->id));
        } catch (Throwable $e) {
            $this->markFailedAttempt();

            throw $e;
        } finally {
            $this->completeRequest();
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markFailedAttempt();
        $this->completeRequest();

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

    public static function activeRequestKeyFor(int $conversationId): string
    {
        return "avatar-background-active:{$conversationId}";
    }

    public static function failureKeyFor(int $conversationId): string
    {
        return "avatar-background-failure:{$conversationId}";
    }

    public static function hasRecentFailureFor(int $conversationId): bool
    {
        return Cache::has(self::failureKeyFor($conversationId));
    }

    private static function dispatchLockKeyFor(int $conversationId): string
    {
        return "avatar-background-dispatch-lock:{$conversationId}";
    }

    /**
     * @param  array{id: string, description: string}  $request
     */
    private static function dispatchRequest(AssistantUser $assistantUser, Conversation $conversation, array $request): void
    {
        try {
            self::dispatch($assistantUser, $conversation, $request['description'], $request['id']);
        } catch (Throwable $e) {
            self::releaseRequest($conversation, $request['id']);

            Log::debug('Avatar background dispatch surfaced a synchronous failure; already logged via failed(), not rethrown.', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{id: string, description: string}  $request
     */
    private static function markActive(int $conversationId, array $request): void
    {
        $expiresAt = now()->addSeconds(self::requestStateTtl());

        Cache::put(self::activeRequestKeyFor($conversationId), $request, $expiresAt);
        Cache::put(self::progressKeyFor($conversationId), 'Generating scene...', $expiresAt);

        self::broadcastStatus($conversationId);
    }

    /**
     * Broadcasting is a best-effort notification, not part of the actual
     * coordination logic — a Reverb outage or misconfiguration must never
     * be able to block generation itself, so failures here are swallowed
     * rather than bubbling up into the Cache::lock callers above.
     */
    private static function broadcastStatus(int $conversationId): void
    {
        try {
            AvatarBackgroundStatusUpdated::dispatch($conversationId);
        } catch (Throwable $e) {
            Log::warning('Failed to broadcast avatar background status.', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function requestStateTtl(): int
    {
        return (int) config('ai.avatar_background.request_state_ttl', 3600);
    }

    private function beginRequest(): bool
    {
        $requestId = $this->requestId ?? null;

        $request = [
            'id' => $requestId ?? (string) Str::uuid(),
            'description' => $this->description,
        ];

        try {
            $claimed = Cache::lock(self::dispatchLockKeyFor($this->conversation->id), 10)
                ->block(5, function () use ($request, $requestId): bool {
                    $activeRequest = Cache::get(self::activeRequestKeyFor($this->conversation->id));

                    if ($requestId !== null) {
                        if (($activeRequest['id'] ?? null) !== $requestId) {
                            return false;
                        }

                        self::markActive($this->conversation->id, $request);

                        return true;
                    }

                    if ($activeRequest !== null
                        || Cache::has(self::cacheKeyFor($this->conversation->id))
                        || self::hasRecentFailureFor($this->conversation->id)) {
                        return false;
                    }

                    self::markActive($this->conversation->id, $request);

                    return true;
                });
        } catch (Throwable $e) {
            Log::warning('Failed to begin avatar background job.', [
                'conversation_id' => $this->conversation->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($claimed) {
            $this->requestId = $request['id'];
        }

        return $claimed;
    }

    private function markFailedAttempt(): void
    {
        Cache::put(
            self::failureKeyFor($this->conversation->id),
            true,
            now()->addSeconds((int) config('ai.avatar_background.failure_cooldown', 300)),
        );
    }

    /**
     * True once a newer request has taken over the active slot for this
     * conversation while this job was still generating — its result is no
     * longer wanted and must not overwrite the newer one's cache entry.
     */
    private function isSuperseded(): bool
    {
        $activeRequest = Cache::get(self::activeRequestKeyFor($this->conversation->id));

        return ($activeRequest['id'] ?? null) !== $this->requestId;
    }

    private function completeRequest(): void
    {
        $requestId = $this->requestId ?? null;

        if ($requestId === null) {
            return;
        }

        self::releaseRequest($this->conversation, $requestId);
    }

    private static function releaseRequest(Conversation $conversation, string $requestId): void
    {
        try {
            Cache::lock(self::dispatchLockKeyFor($conversation->id), 10)
                ->block(5, function () use ($conversation, $requestId): void {
                    $activeRequest = Cache::get(self::activeRequestKeyFor($conversation->id));

                    if (($activeRequest['id'] ?? null) !== $requestId) {
                        return;
                    }

                    Cache::forget(self::activeRequestKeyFor($conversation->id));
                    Cache::forget(self::progressKeyFor($conversation->id));

                    self::broadcastStatus($conversation->id);
                });
        } catch (Throwable $e) {
            Log::warning('Failed to release avatar background generation state.', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
