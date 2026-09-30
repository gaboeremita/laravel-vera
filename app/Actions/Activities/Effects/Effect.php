<?php

namespace App\Actions\Activities\Effects;

use App\Actions\Activities\ActivityUse;
use App\Contracts\ActivityEffect;

/**
 * What most effects share: nothing stands in their way and they name no items.
 */
abstract class Effect implements ActivityEffect
{
    public function missing(array $config, ActivityUse $use): ?string
    {
        return null;
    }

    public function itemIds(array $config): array
    {
        return [];
    }

    public function withoutItem(array $config, int $itemId): ?array
    {
        return $config;
    }
}
