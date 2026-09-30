<?php

namespace Database\Factories;

use App\Models\Region;
use App\Models\WorldSession;
use App\Models\WorldSessionObject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorldSessionObject>
 */
class WorldSessionObjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'region_id' => Region::factory()->withLayout(),
            'object_id' => 'pool-lounger-1',
            'state' => [],
        ];
    }

    public function passable(): static
    {
        return $this->state(fn (array $attributes) => ['state' => ['passable' => true]]);
    }
}
