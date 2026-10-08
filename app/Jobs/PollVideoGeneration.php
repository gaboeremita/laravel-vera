<?php

namespace App\Jobs;

use App\Contracts\VideoGenProvider;
use App\Enums\VideoStatus;
use App\Events\VideoGenerationFinished;
use App\Events\VideoGenerationStatusUpdated;
use App\Models\Video;
use App\Services\VideoGenProviders\VideoGenerationService;
use App\Services\VideoGenProviders\VideoGenManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PollVideoGeneration implements ShouldQueue
{
    use Queueable;

    public const POLL_SECONDS = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Video $video) {}

    public function retryUntil(): \DateTimeInterface
    {
        return $this->video->created_at->copy()->addSeconds(app(VideoGenerationService::class)->maximumWaitSeconds($this->video));
    }

    public function handle(VideoGenManager $videoGenManager, VideoGenerationService $videoGenerationService): void
    {
        if ($this->video->status->isFinished() || $this->messageIsGone($this->video)) {
            return;
        }

        $provider = $videoGenManager->fromModel($videoGenerationService->modelFor($this->video));

        try {
            if ($this->video->job_id === null) {
                $this->video->update(['job_id' => $provider->submit(
                    $this->video->prompt,
                    $this->submitOptions($videoGenerationService),
                    $this->video->firstFrame ? $videoGenerationService->firstFrameUrl($this->video->firstFrame) : null,
                )]);
            } else {
                $result = $provider->status($this->video->job_id);

                if ($result->status === VideoStatus::Completed) {
                    $this->storeVideo($provider, $result->contentUrl);

                    return;
                }

                if ($result->status === VideoStatus::Failed) {
                    $this->markFailed($result->error ?? 'Video generation failed.');

                    return;
                }

                if ($result->status !== $this->video->status) {
                    $this->video->update(['status' => $result->status]);
                    VideoGenerationStatusUpdated::dispatch($this->video);
                }
            }
        } catch (RuntimeException|\InvalidArgumentException $e) {
            $this->markFailed($e->getMessage());

            return;
        }

        // The provider offers no push for a finished job, so the job checks back by
        // re-queuing itself instead of sleeping, which would hold a worker for minutes.
        $this->release(self::POLL_SECONDS);
    }

    public function failed(Throwable $exception): void
    {
        $video = $this->video->fresh();

        if ($video === null || $video->status->isFinished() || $this->messageIsGone($video)) {
            return;
        }

        $this->video = $video;
        $this->markFailed($exception instanceof MaxAttemptsExceededException
            ? 'Video generation timed out after '.app(VideoGenerationService::class)->maximumWaitSeconds($video).' seconds.'
            : $exception->getMessage());
    }

    /**
     * Videos hang off their message through a morph, which the database does not
     * cascade, so a deleted conversation leaves the video behind without a message.
     */
    private function messageIsGone(Video $video): bool
    {
        return $video->videoable?->conversation === null;
    }

    /**
     * @return array<string, mixed>
     */
    private function submitOptions(VideoGenerationService $videoGenerationService): array
    {
        return [
            'duration' => $this->video->duration,
            'aspect_ratio' => $this->video->aspect_ratio,
            'generate_audio' => $this->video->generate_audio,
            'resolution' => $videoGenerationService->modelFor($this->video)->config['resolution'] ?? null,
        ];
    }

    private function storeVideo(VideoGenProvider $provider, ?string $contentUrl): void
    {
        if ($contentUrl === null) {
            $this->markFailed('The finished video had no download link.');

            return;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'video');

        try {
            $provider->download($contentUrl, $tempPath);

            $conversation = $this->video->videoable->conversation;
            $userId = $conversation->assistantUser()?->user_id;
            $this->video->storeDownloaded($tempPath, "messages/{$userId}/{$conversation->id}");
            $this->video->status = VideoStatus::Completed;
            $this->video->save();
        } catch (RuntimeException $e) {
            $this->markFailed($e->getMessage());

            return;
        } finally {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }
        }

        VideoGenerationStatusUpdated::dispatch($this->video);
        VideoGenerationFinished::dispatch($this->video);
    }

    private function markFailed(string $reason): void
    {
        $this->video->update(['status' => VideoStatus::Failed, 'failure_reason' => $reason]);

        Log::error('Video generation failed', [
            'video_id' => $this->video->id,
            'message_id' => $this->video->videoable_id,
            'error' => $reason,
        ]);

        VideoGenerationStatusUpdated::dispatch($this->video);
        VideoGenerationFinished::dispatch($this->video);
    }
}
