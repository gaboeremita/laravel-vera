<?php

namespace App\Services\AvatarBackground;

use App\DTOs\ImageGenResult;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Services\ImageGenProviders\ImageGenManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AvatarBackgroundService
{
    public function __construct(
        private readonly ImageGenManager $imageGenManager = new ImageGenManager,
        private readonly AvatarBackgroundPromptEnhancer $promptEnhancer = new AvatarBackgroundPromptEnhancer,
    ) {}

    /**
     * @return array{floor_url: string, surroundings_url: string, source_description: string}
     *
     * @throws \RuntimeException if enhancement or generation fails
     */
    public function generate(AssistantUser $assistantUser, Conversation $conversation, string $rawDescription): array
    {
        $prompts = $this->promptEnhancer->enhance($rawDescription, $assistantUser, $conversation);

        $provider = $this->imageGenManager->forAssistantUser($assistantUser);

        [$floorResult, $surroundingsResult] = $provider->generateMany([
            $prompts['floor'],
            $prompts['surroundings'],
        ]);

        $storagePath = $this->storagePath($conversation);

        Storage::disk('public')->deleteDirectory($storagePath);

        return [
            'floor_url' => $this->storeImage($floorResult, $storagePath, 'floor'),
            'surroundings_url' => $this->storeImage($surroundingsResult, $storagePath, 'surroundings'),
            'source_description' => $rawDescription,
        ];
    }

    private function storagePath(Conversation $conversation): string
    {
        return config('ai.avatar_background.storage_path')."/{$conversation->id}";
    }

    private function storeImage(ImageGenResult $result, string $storagePath, string $name): string
    {
        $imageData = base64_decode($result->imageData);
        $extension = $this->extensionFor($result->contentType);
        $path = "{$storagePath}/{$name}-".Str::uuid().".{$extension}";

        Storage::disk('public')->put($path, $imageData);

        return Storage::disk('public')->url($path);
    }

    private function extensionFor(string $contentType): string
    {
        return match ($contentType) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
