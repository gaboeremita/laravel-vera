<?php

namespace App\Actions\Activities\Effects;

use App\Models\World;

/**
 * An effect that moves an amount of credits.
 */
abstract class CreditsEffect extends Effect
{
    public function validate(array $config, World $world): array
    {
        return is_int($config['amount'] ?? null) && $config['amount'] >= 1 ? [] : ['The amount must be a whole number of at least 1.'];
    }

    public function normalize(array $config): array
    {
        return ['amount' => (int) $config['amount']];
    }
}
