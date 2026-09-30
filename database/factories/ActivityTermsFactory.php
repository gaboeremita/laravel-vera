<?php

namespace Database\Factories;

use App\Models\ActivityTerms;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityTerms>
 */
class ActivityTermsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'region_id' => Region::factory()->withLayout(),
            'object_id' => 'pool-lounger-1',
            'activity_id' => 'recline',
            'responses' => [],
        ];
    }

    /**
     * One response that always runs, with these effects.
     *
     * @param  array<int, array<string, mixed>>  $effects
     */
    public function withEffects(array $effects, ?array $condition = null): static
    {
        return $this->state(fn (array $attributes) => ['responses' => [['condition' => $condition, 'effects' => $effects]]]);
    }
}
