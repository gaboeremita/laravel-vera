<?php

namespace Database\Factories;

use App\Enums\RevealSource;
use App\Models\Fact;
use App\Models\KnownFact;
use App\Models\WorldSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnownFact>
 */
class KnownFactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'fact_id' => Fact::factory(),
            'source' => RevealSource::InCharacter,
            'source_name' => fake()->firstName(),
            'summary' => fake()->sentence(),
        ];
    }

    public function fromItem(string $itemName): static
    {
        return $this->state(['source' => RevealSource::Item, 'source_name' => $itemName]);
    }
}
