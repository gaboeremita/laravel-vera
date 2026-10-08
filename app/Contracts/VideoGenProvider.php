<?php

namespace App\Contracts;

use App\DTOs\VideoGenJobStatus;
use App\DTOs\VideoGenSupportedSettings;
use App\Models\VideoGenModel;

interface VideoGenProvider
{
    /**
     * Start generating a video and return the provider's job id.
     *
     * @param  array<string, mixed>  $options
     */
    public function submit(string $prompt, array $options = [], ?string $firstFrameUrl = null): string;

    public function status(string $jobId): VideoGenJobStatus;

    /**
     * Download a finished video into a local file.
     */
    public function download(string $contentUrl, string $targetPath): void;

    /**
     * The lengths and aspect ratios the model can produce, or null when the provider does not say.
     */
    public function supportedSettings(): ?VideoGenSupportedSettings;

    public static function fromModel(VideoGenModel $videoGenModel): static;
}
