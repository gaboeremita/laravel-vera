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

    /**
     * Generate images for multiple prompts concurrently and return one result per prompt, same order.
     *
     * @param  string[]  $prompts
     * @return ImageGenResult[]
     */
    public function generateMany(array $prompts, array $options = []): array;

    public static function fromModel(ImageGenModel $imageGenModel): static;
}
