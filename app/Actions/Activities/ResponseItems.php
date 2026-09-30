<?php

namespace App\Actions\Activities;

/**
 * The items a list of responses names, in its conditions and effects.
 */
class ResponseItems
{
    public function __construct(
        private readonly ActivityConditions $conditions,
        private readonly ActivityEffects $effects,
    ) {}

    /**
     * @param  array<int, array{condition: ?array, effects: array<int, array<string, mixed>>}>  $responses
     * @return array<int, int>
     */
    public function ids(array $responses): array
    {
        return collect($responses)
            ->flatMap(fn (array $response) => [
                ...$this->conditions->itemIds($response['condition'] ?? null),
                ...collect($this->effects->resolve($response['effects'] ?? []))->flatMap(fn (array $pair) => $pair[0]->itemIds($pair[1])),
            ])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The responses once the item is deleted from the world.
     *
     * @param  array<int, array{condition: ?array, effects: array<int, array<string, mixed>>}>  $responses
     * @return array<int, array{condition: ?array, effects: array<int, array<string, mixed>>}>
     */
    public function without(array $responses, int $itemId): array
    {
        return collect($responses)->map(fn (array $response) => [
            'condition' => $this->conditions->withoutItem($response['condition'] ?? null, $itemId),
            'effects' => collect($this->effects->resolve($response['effects'] ?? []))
                ->map(fn (array $pair) => $pair[0]->withoutItem($pair[1], $itemId))
                ->filter()
                ->values()
                ->all(),
        ])->values()->all();
    }
}
