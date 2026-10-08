<?php

namespace App\Services\VideoGenProviders;

use App\Enums\VideoStatus;
use App\Jobs\PollVideoGeneration;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\Image;
use App\Models\Message;
use App\Models\Video;
use App\Models\VideoGenModel;

class VideoGenerationService
{
    public function __construct(
        private readonly VideoGenManager $videoGenManager = new VideoGenManager,
        private readonly VideoGenPromptEnhancer $promptEnhancer = new VideoGenPromptEnhancer,
    ) {}

    /**
     * @return array{description: string, duration: ?int, aspectRatio: ?string, generateAudio: ?bool}
     *
     * @throws \RuntimeException if the description request fails
     */
    public function improveDescription(AssistantUser $assistantUser, Conversation $conversation, string $rawPrompt): array
    {
        return $this->promptEnhancer->enhance($rawPrompt, $assistantUser, $conversation, $this->videoGenManager->resolveVideoGenModel($assistantUser));
    }

    /**
     * Creates the video on the message and queues its generation.
     *
     * @param  array{duration?: ?int, aspectRatio?: ?string, generateAudio?: ?bool}  $requested
     */
    public function start(AssistantUser $assistantUser, Message $carrierMessage, string $description, array $requested, ?Image $firstFrame): Video
    {
        $selectedModel = $this->videoGenManager->resolveVideoGenModel($assistantUser);
        $model = $selectedModel ?? $this->videoGenManager->configuredModel();
        $defaults = $model->config ?? [];

        $duration = $requested['duration'] ?? $defaults['duration'] ?? null;
        $aspectRatio = $requested['aspectRatio'] ?? $defaults['aspect_ratio'] ?? null;
        $supported = $this->videoGenManager->fromModel($model)->supportedSettings();

        if ($supported !== null) {
            $duration = $this->closestDuration($duration, $supported->durations);
            $aspectRatio = $this->closestAspectRatio($aspectRatio, $supported->aspectRatios);
        }

        $video = $carrierMessage->video()->create([
            'video_gen_model_id' => $selectedModel?->id,
            'status' => VideoStatus::Queued,
            'prompt' => $description,
            'duration' => $duration,
            'aspect_ratio' => $aspectRatio,
            'generate_audio' => $requested['generateAudio'] ?? $defaults['generate_audio'] ?? null,
            'first_frame_image_id' => $firstFrame?->id,
        ]);

        PollVideoGeneration::dispatch($video);

        return $video;
    }

    public function isAvailableFor(AssistantUser $assistantUser): bool
    {
        try {
            $this->videoGenManager->forAssistantUser($assistantUser);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * The model a video is generated with: the one it was started with, or the configured fallback.
     */
    public function modelFor(Video $video): VideoGenModel
    {
        return $video->model ?? $this->videoGenManager->configuredModel();
    }

    public function maximumWaitSeconds(Video $video): int
    {
        return (int) ($this->modelFor($video)->config['timeout'] ?? config('ai.video_gen.timeout'));
    }

    /**
     * The address the provider fetches an attached image from, through the public tunnel.
     *
     * @throws \InvalidArgumentException when no public address is configured
     */
    public function firstFrameUrl(Image $image): string
    {
        $publicUrl = config('ai.video_gen.public_url');

        if (empty($publicUrl)) {
            throw new \InvalidArgumentException(self::missingPublicUrlMessage());
        }

        return rtrim($publicUrl, '/').'/storage/'.$image->path;
    }

    public static function hasPublicUrl(): bool
    {
        return ! empty(config('ai.video_gen.public_url'));
    }

    public static function missingPublicUrlMessage(): string
    {
        return 'Set PUBLIC_TUNNEL_URL to generate a video from an image.';
    }

    /**
     * @param  list<int>  $supported
     */
    private function closestDuration(?int $duration, array $supported): ?int
    {
        if ($duration === null || $supported === [] || in_array($duration, $supported, true)) {
            return $duration;
        }

        return collect($supported)->sortBy(fn (int $option) => abs($option - $duration))->first();
    }

    /**
     * @param  list<string>  $supported
     */
    private function closestAspectRatio(?string $aspectRatio, array $supported): ?string
    {
        $requested = $this->ratioValue($aspectRatio);

        if ($requested === null || $supported === [] || in_array($aspectRatio, $supported, true)) {
            return $aspectRatio;
        }

        return collect($supported)
            ->filter(fn (string $option) => $this->ratioValue($option) !== null)
            ->sortBy(fn (string $option) => abs($this->ratioValue($option) - $requested))
            ->first() ?? $aspectRatio;
    }

    private function ratioValue(?string $aspectRatio): ?float
    {
        if ($aspectRatio === null || ! preg_match('/^(\d+(?:\.\d+)?):(\d+(?:\.\d+)?)$/', $aspectRatio, $parts) || (float) $parts[2] === 0.0) {
            return null;
        }

        return (float) $parts[1] / (float) $parts[2];
    }
}
