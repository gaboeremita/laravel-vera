<?php

namespace App\DTOs;

class ImageGenResult
{
    public function __construct(
        public readonly string $imageData,
        public readonly string $contentType,
        public readonly string $enhancedPrompt,
    ) {}
}
