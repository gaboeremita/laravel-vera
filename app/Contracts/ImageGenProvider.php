<?php

namespace App\Contracts;

use App\DTOs\ImageGenResult;
use App\Models\ImageGenModel;

interface ImageGenProvider
{
    /**
     * Generate an image from a prompt and return the result.
     */
    public function generate(string $prompt, array $options = []): ImageGenResult;

    public static function fromModel(ImageGenModel $imageGenModel): static;
}
