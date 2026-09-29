<?php

namespace Database\Factories;

use App\Models\Quest;
use App\Models\World;
use App\Models\WorldResident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quest>
 */
class QuestFactory extends Factory
{
    /**
     * A minimal valid definition: starts with the session, one beat finished by a flag.
     *
     * @return array<string, mixed>
     */
    public static function defaultDefinition(): array
    {
        return [
            'description' => 'Something needs doing.',
            'start' => ['mode' => 'auto'],
            'requires' => [],
            'repeatable' => false,
            'beats' => [self::beat('first', ['when' => ['flag' => 'firstDone']])],
            'complete' => null,
            'fail' => null,
            'rubric' => [
                'guidance' => 'Judge how it went.',
                'dimensions' => [['name' => 'care', 'description' => 'How carefully the player acted.']],
                'tiers' => [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function beat(string $id, array $attributes = []): array
    {
        return [
            'id' => $id,
            'text' => "Beat {$id}.",
            'hidden' => false,
            'requires' => [],
            'when' => ['flag' => "{$id}Done"],
            'knowledge' => [],
            'grants' => [],
            'questions' => [],
            ...$attributes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_id' => World::factory(),
            'campaign_id' => null,
            'key' => fake()->unique()->slug(3),
            'title' => fake()->words(3, true),
            'definition' => self::defaultDefinition(),
        ];
    }

    public function autoStart(): static
    {
        return $this->withDefinition(['start' => ['mode' => 'auto']]);
    }

    public function offeredBy(WorldResident $giver): static
    {
        return $this->withDefinition(['start' => ['mode' => 'offer', 'giver' => $giver->id]]);
    }

    public function repeatable(): static
    {
        return $this->withDefinition(['repeatable' => true]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $beats
     */
    public function withBeats(array $beats): static
    {
        return $this->withDefinition(['beats' => $beats]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function withDefinition(array $changes): static
    {
        return $this->state(fn (array $attributes) => ['definition' => [...($attributes['definition'] ?? self::defaultDefinition()), ...$changes]]);
    }
}
