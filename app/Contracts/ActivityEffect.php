<?php

namespace App\Contracts;

use App\Actions\Activities\ActivityUse;
use App\Models\World;

/**
 * One kind of thing a response of an activity can do. Each kind reads its own
 * config, stored beside its `type` in the response's effect list.
 */
interface ActivityEffect
{
    /**
     * The `type` the effect is stored under.
     */
    public function type(): string;

    /**
     * What is wrong with the config, one message per problem.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    public function validate(array $config, World $world): array;

    /**
     * The config as it is stored, keeping only what the effect reads.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normalize(array $config): array;

    /**
     * Why the effect cannot run for this use; null when nothing stands in the way.
     *
     * @param  array<string, mixed>  $config
     */
    public function missing(array $config, ActivityUse $use): ?string;

    /**
     * @param  array<string, mixed>  $config
     */
    public function apply(array $config, ActivityUse $use): void;

    /**
     * What the effect does, told to the narrator, who writes what the player reads.
     *
     * @param  array<string, mixed>  $config
     */
    public function describe(array $config, ActivityUse $use): string;

    /**
     * The ids of the items the config names.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, int>
     */
    public function itemIds(array $config): array;

    /**
     * The config once the item is deleted from the world; null when nothing is left for the effect to do.
     *
     * @param  array<string, mixed>  $config
     * @return ?array<string, mixed>
     */
    public function withoutItem(array $config, int $itemId): ?array;
}
