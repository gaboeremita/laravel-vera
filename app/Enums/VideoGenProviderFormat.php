<?php

namespace App\Enums;

use App\Services\VideoGenProviders\OpenRouterVideoGenProvider;

enum VideoGenProviderFormat: string
{
    case OpenRouter = 'openrouter';

    public function providerClass(): string
    {
        return match ($this) {
            self::OpenRouter => OpenRouterVideoGenProvider::class,
        };
    }
}
