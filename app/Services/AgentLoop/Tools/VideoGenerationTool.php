<?php

namespace App\Services\AgentLoop\Tools;

use App\Contracts\AgentTool;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Services\VideoGenProviders\VideoGenerationService;

class VideoGenerationTool implements AgentTool
{
    public function __construct(
        private readonly VideoGenerationService $videoGenerationService,
        private readonly AssistantUser $assistantUser,
        private readonly Conversation $conversation,
    ) {}

    public function name(): string
    {
        return 'generate_video';
    }

    public function description(): string
    {
        return 'Starts generating a short video from a text description and shows it to the user when it is ready. Use when the user asks for a video, clip or animation. An image the user attached to this message becomes the first frame.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'prompt' => [
                    'type' => 'string',
                    'description' => 'A description of the video to generate.',
                ],
                'duration' => [
                    'type' => 'integer',
                    'description' => 'Length in seconds, when the user asked for one.',
                ],
                'aspect_ratio' => [
                    'type' => 'string',
                    'description' => 'Aspect ratio such as 16:9 or 9:16, when the user asked for one.',
                ],
                'generate_audio' => [
                    'type' => 'boolean',
                    'description' => 'Whether the video has sound, when the user said.',
                ],
            ],
            'required' => ['prompt'],
        ];
    }

    public function handle(array $arguments): array
    {
        if (empty($arguments['prompt'])) {
            throw new \RuntimeException('Missing required "prompt" argument.');
        }

        $attachedImage = $this->conversation->messages()
            ->where('role', 'user')
            ->latest('id')
            ->first()
            ?->image;

        if ($attachedImage !== null && ! VideoGenerationService::hasPublicUrl()) {
            throw new \RuntimeException(VideoGenerationService::missingPublicUrlMessage());
        }

        $described = $this->videoGenerationService->improveDescription($this->assistantUser, $this->conversation, $arguments['prompt']);

        $carrierMessage = $this->conversation->messages()->create([
            'role' => 'assistant',
            'content' => '',
        ]);

        $video = $this->videoGenerationService->start($this->assistantUser, $carrierMessage, $described['description'], [
            'duration' => isset($arguments['duration']) ? (int) $arguments['duration'] : $described['duration'],
            'aspectRatio' => $arguments['aspect_ratio'] ?? $described['aspectRatio'],
            'generateAudio' => isset($arguments['generate_audio']) ? (bool) $arguments['generate_audio'] : $described['generateAudio'],
        ], $attachedImage);

        return [
            'status' => 'queued',
            'video_id' => $video->id,
            'enhanced_prompt' => $described['description'],
        ];
    }

    public function timeoutSeconds(): int
    {
        return config('ai.default.config.timeout') + 30;
    }

    public function retryAttempts(): int
    {
        return 1;
    }
}
