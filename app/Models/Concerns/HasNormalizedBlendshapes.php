<?php

namespace App\Models\Concerns;

trait HasNormalizedBlendshapes
{
    /**
     * Converts {expression, weight} pairs (weight as a 0-100 percentage
     * from the UI) into the 0.0-1.0 scale VRM's expressionManager expects.
     *
     * @param  array<int, array{expression: string, weight: float}>|null  $blendshapes
     * @return array<int, array{expression: string, weight: float}>|null
     */
    public static function normalizeBlendshapes(?array $blendshapes): ?array
    {
        if ($blendshapes === null) {
            return null;
        }

        return array_map(fn (array $b) => [
            'expression' => $b['expression'],
            'weight' => round($b['weight'] / 100, 4),
        ], $blendshapes);
    }
}
