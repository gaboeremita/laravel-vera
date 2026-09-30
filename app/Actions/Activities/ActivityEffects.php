<?php

namespace App\Actions\Activities;

use App\Contracts\ActivityEffect;
use Illuminate\Container\Attributes\Tag;

/**
 * Every kind of effect a response can run, by the `type` it is stored under.
 * A new kind is a class implementing ActivityEffect, tagged in AppServiceProvider.
 */
class ActivityEffects
{
    /** @var array<string, ActivityEffect> */
    private array $effects = [];

    /**
     * @param  iterable<int, ActivityEffect>  $effects
     */
    public function __construct(#[Tag('activityEffects')] iterable $effects)
    {
        foreach ($effects as $effect) {
            $this->effects[$effect->type()] = $effect;
        }
    }

    public function find(string $type): ?ActivityEffect
    {
        return $this->effects[$type] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function types(): array
    {
        return array_keys($this->effects);
    }

    /**
     * Every effect of the list whose type is known, with its config.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array{0: ActivityEffect, 1: array<string, mixed>}>
     */
    public function resolve(array $entries): array
    {
        return collect($entries)
            ->map(fn (array $entry) => [$this->find((string) ($entry['type'] ?? '')), $entry])
            ->filter(fn (array $pair) => $pair[0] !== null)
            ->values()
            ->all();
    }
}
