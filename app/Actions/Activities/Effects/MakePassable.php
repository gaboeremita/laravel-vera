<?php

namespace App\Actions\Activities\Effects;

use App\Actions\Activities\ActivityUse;
use App\Models\World;

/**
 * The object stops blocking the player's way for the rest of the session.
 */
class MakePassable extends Effect
{
    public function type(): string
    {
        return 'makePassable';
    }

    public function validate(array $config, World $world): array
    {
        return [];
    }

    public function normalize(array $config): array
    {
        return [];
    }

    public function apply(array $config, ActivityUse $use): void
    {
        $use->setObjectState('passable', true);
    }

    public function describe(array $config, ActivityUse $use): string
    {
        return "The {$use->objectName()} opens the way: from now on the player can pass through it.";
    }
}
