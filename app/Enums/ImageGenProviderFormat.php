<?php

namespace App\Enums;

use App\Services\ImageGenProviders\OpenAiCompatibleImageGenProvider;
use App\Services\ImageGenProviders\OpenRouterImageGenProvider;

enum ImageGenProviderFormat: string
{
    case OpenRouter = 'openrouter';
    case OpenAiCompatible = 'openai_compatible';

    public function providerClass(): string
    {
        return match ($this) {
            self::OpenRouter => OpenRouterImageGenProvider::class,
            self::OpenAiCompatible => OpenAiCompatibleImageGenProvider::class,
        };
    }
}
