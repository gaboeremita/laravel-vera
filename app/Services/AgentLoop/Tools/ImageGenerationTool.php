<?php

namespace App\Services\AgentLoop\Tools;

use App\Contracts\AgentTool;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\Image;
use App\Services\ImageGenProviders\ImageGenerationService;

class ImageGenerationTool implements AgentTool
{
    public function __construct(
        private readonly ImageGenerationService $imageGenerationService,
        private readonly AssistantUser $assistantUser,
        private readonly Conversation $conversation,
    ) {}

    public function name(): string
    {
        return 'generate_image';
    }

    public function description(): string
    {
        return 'Generates an image from a text description and shows it to the user. Use when the user asks to see, generate, draw, or create a picture or image of something.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'prompt' => [
                    'type' => 'string',
                    'description' => 'A description of the image to generate.',
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

        $result = $this->imageGenerationService->generate($this->assistantUser, $this->conversation, $arguments['prompt']);

        $carrierMessage = $this->conversation->messages()->create([
            'role' => 'assistant',
            'content' => '',
        ]);

        $storagePath = "messages/{$this->assistantUser->user_id}/{$this->conversation->id}";
        Image::storeFromBase64($result['imageData'], $carrierMessage, $storagePath);

        return [
            'status' => 'success',
            'enhanced_prompt' => $result['enhancedPrompt'],
        ];
    }

    public function timeoutSeconds(): int
    {
        return $this->imageGenerationService->resolveTimeoutFor($this->assistantUser) + 30;
    }

    public function retryAttempts(): int
    {
        return 1;
    }
}
