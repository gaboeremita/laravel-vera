<?php

namespace App\Services\VideoGenProviders;

use App\Contracts\VideoGenProvider;
use App\DTOs\VideoGenJobStatus;
use App\DTOs\VideoGenSupportedSettings;
use App\Enums\VideoStatus;
use App\Models\VideoGenModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenRouterVideoGenProvider implements VideoGenProvider
{
    private const REQUEST_TIMEOUT = 60;

    private const DOWNLOAD_TIMEOUT = 180;

    private const SUPPORTED_SETTINGS_TTL = 86400;

    public function __construct(
        private readonly string $url,
        private readonly string $model,
        private readonly ?string $apiKey,
        private readonly array $additionalConfig = [],
    ) {}

    public static function fromModel(VideoGenModel $videoGenModel): static
    {
        $provider = $videoGenModel->provider;

        return new static(
            url: rtrim($provider->url, '/'),
            model: $videoGenModel->endpoint,
            apiKey: $provider->api_key,
            additionalConfig: $videoGenModel->additional_config ?? [],
        );
    }

    public function submit(string $prompt, array $options = [], ?string $firstFrameUrl = null): string
    {
        $response = $this->send(fn () => Http::timeout(self::REQUEST_TIMEOUT)
            ->withHeaders($this->headers())
            ->post($this->url, $this->payload($prompt, $options, $firstFrameUrl)));

        if ($response->failed()) {
            throw new RuntimeException('Video generation request failed: '.$response->body());
        }

        $jobId = $response->json('id');

        if (! $jobId) {
            throw new RuntimeException('Video generation response did not contain a job id.');
        }

        return $jobId;
    }

    public function status(string $jobId): VideoGenJobStatus
    {
        $response = $this->send(fn () => Http::timeout(self::REQUEST_TIMEOUT)
            ->withHeaders($this->headers())
            ->get("{$this->url}/{$jobId}"));

        if ($response->failed()) {
            throw new RuntimeException('Video generation status request failed: '.$response->body());
        }

        $status = $response->json('status');

        return match ($status) {
            'pending' => new VideoGenJobStatus(VideoStatus::Queued),
            'in_progress' => new VideoGenJobStatus(VideoStatus::Generating),
            'completed' => new VideoGenJobStatus(VideoStatus::Completed, contentUrl: $response->json('unsigned_urls.0')),
            'failed', 'cancelled', 'expired' => new VideoGenJobStatus(VideoStatus::Failed, error: $this->errorMessage($response->json('error'), $status)),
            default => throw new RuntimeException("Video generation returned an unknown status: {$status}"),
        };
    }

    public function download(string $contentUrl, string $targetPath): void
    {
        $response = $this->send(fn () => Http::timeout(self::DOWNLOAD_TIMEOUT)
            ->withHeaders($this->headers())
            ->sink($targetPath)
            ->get($contentUrl));

        if ($response->failed()) {
            throw new RuntimeException('Video download failed with status '.$response->status().'.');
        }
    }

    public function supportedSettings(): ?VideoGenSupportedSettings
    {
        $cacheKey = 'video-gen-supported:'.md5($this->url).":{$this->model}";

        $settings = Cache::remember($cacheKey, self::SUPPORTED_SETTINGS_TTL, function (): ?array {
            try {
                $response = Http::timeout(self::REQUEST_TIMEOUT)
                    ->withHeaders($this->headers())
                    ->get("{$this->url}/models");
            } catch (ConnectionException $e) {
                Log::warning('Could not read supported video settings.', ['model' => $this->model, 'error' => $e->getMessage()]);

                return null;
            }

            $model = collect($response->successful() ? $response->json('data', []) : [])->firstWhere('id', $this->model);

            if ($model === null) {
                Log::warning('Supported video settings do not list the model.', ['model' => $this->model, 'status' => $response->status()]);

                return null;
            }

            return [
                'durations' => array_map('intval', $model['supported_durations'] ?? []),
                'aspectRatios' => array_values($model['supported_aspect_ratios'] ?? []),
            ];
        });

        return $settings === null ? null : new VideoGenSupportedSettings($settings['durations'], $settings['aspectRatios']);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [];
        if ($this->apiKey) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function payload(string $prompt, array $options, ?string $firstFrameUrl): array
    {
        $payload = array_merge([
            'model' => $this->model,
            'prompt' => $prompt,
        ], $this->additionalConfig, array_filter($options, fn (mixed $value) => $value !== null));

        if ($firstFrameUrl !== null) {
            $payload['frame_images'] = [[
                'type' => 'image_url',
                'image_url' => ['url' => $firstFrameUrl],
                'frame_type' => 'first_frame',
            ]];
        }

        return $payload;
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            throw new RuntimeException('Failed to connect to video generation provider: '.$e->getMessage());
        }
    }

    private function errorMessage(mixed $error, string $status): string
    {
        if (is_array($error)) {
            return $error['message'] ?? json_encode($error);
        }

        return is_string($error) && $error !== '' ? $error : "The video generation job ended as {$status}.";
    }
}
