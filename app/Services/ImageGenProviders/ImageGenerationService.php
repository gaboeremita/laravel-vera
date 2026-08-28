<?php

namespace App\Services\ImageGenProviders;

use App\Models\AssistantUser;
use App\Models\Conversation;

class ImageGenerationService
{
    public function __construct(
        private readonly ImageGenManager $imageGenManager = new ImageGenManager,
        private readonly ImageGenPromptEnhancer $promptEnhancer = new ImageGenPromptEnhancer,
    ) {}

    /**
     * @return array{enhancedPrompt: string, imageData: string}
     *
     * @throws \RuntimeException|\InvalidArgumentException if enhancement or generation fails
     */
    public function generate(AssistantUser $assistantUser, Conversation $conversation, string $rawPrompt): array
    {
        $imageGenModel = $this->imageGenManager->resolveImageGenModel($assistantUser);

        $enhancedPrompt = $this->promptEnhancer->enhance($rawPrompt, $assistantUser, $conversation, $imageGenModel);

        $provider = $imageGenModel
            ? $this->imageGenManager->fromModel($imageGenModel)
            : $this->imageGenManager->fromConfig();

        $result = $provider->generate($enhancedPrompt);

        return [
            'enhancedPrompt' => $enhancedPrompt,
            'imageData' => $result->imageData,
        ];
    }

    public function isAvailableFor(AssistantUser $assistantUser): bool
    {
        try {
            $this->imageGenManager->forAssistantUser($assistantUser);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public function resolveTimeoutFor(AssistantUser $assistantUser): int
    {
        $imageGenModel = $this->imageGenManager->resolveImageGenModel($assistantUser);

        return $imageGenModel?->config['timeout'] ?? config('ai.image_gen.timeout');
    }
}
