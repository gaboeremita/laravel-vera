<?php

namespace App\Actions\Activities\Effects;

use App\Actions\Activities\ActivityUse;
use App\Models\World;

/**
 * The creator's direction for what happens, which the narrator works into
 * the narration the player reads.
 */
class ShowText extends Effect
{
    public function type(): string
    {
        return 'showText';
    }

    public function validate(array $config, World $world): array
    {
        return is_string($config['text'] ?? null) && trim($config['text']) !== '' ? [] : ['Write the text to show.'];
    }

    public function normalize(array $config): array
    {
        return ['text' => trim($config['text'])];
    }

    public function apply(array $config, ActivityUse $use): void {}

    public function describe(array $config, ActivityUse $use): string
    {
        return $config['text'];
    }
}
