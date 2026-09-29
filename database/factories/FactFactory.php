<?php

namespace Database\Factories;

use App\Models\Fact;
use App\Models\WorldResident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fact>
 */
class FactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_resident_id' => WorldResident::factory(),
            'topic' => fake()->unique()->words(3, true),
            'content' => fake()->sentence(12),
            'disclosure' => 'only to someone they trust',
        ];
    }

    /**
     * @param  iterable<int, WorldResident>  $residents
     */
    public function withRelays(iterable $residents): static
    {
        return $this->afterCreating(fn (Fact $fact) => $fact->relays()->attach(collect($residents)->pluck('id')));
    }
}
