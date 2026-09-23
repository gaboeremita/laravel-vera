<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<World>
 */
class WorldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'environment_disk' => 'public',
            'environment_path' => fake()->uuid().'.glb',
            'environment_original_name' => 'room.glb',
            'assistant_context_prompt' => fake()->sentence(),
            'npc_context_prompt' => fake()->sentence(),
            'settings' => ['player_spawn' => ['x' => 0, 'y' => 0, 'z' => 0]],
        ];
    }

    /**
     * Attach the given user to the world via the world_user pivot, replacing
     * the old direct user_id ownership (World::factory()->for($user) no
     * longer applies since World has no belongsTo(User) relation).
     */
    public function forUser(User $user): static
    {
        return $this->afterCreating(fn (World $world) => $world->users()->attach($user));
    }

    public function withLayout(): static
    {
        return $this->state(fn () => [
            'layout' => [
                'floors' => [
                    ['id' => 'ground', 'name' => 'Ground floor', 'minY' => -2, 'maxY' => 4],
                    ['id' => 'upper', 'name' => 'Upper floor', 'minY' => 4, 'maxY' => 8],
                ],
                'zones' => [
                    [
                        'id' => 'studio', 'name' => 'Music studio', 'description' => 'A studio full of keyboards.',
                        'floorId' => 'ground', 'parentId' => null, 'private' => false,
                        'outline' => [[-10, 0], [0, 0], [0, 10], [-10, 10]], 'minY' => -2, 'maxY' => 4,
                        'entry' => ['x' => -5, 'y' => 0, 'z' => 1], 'activities' => [],
                    ],
                    [
                        'id' => 'vocal-booth', 'name' => 'Vocal booth', 'description' => 'A small glass booth for recording vocals.',
                        'floorId' => 'ground', 'parentId' => 'studio', 'private' => false,
                        'outline' => [[-4, 6], [0, 6], [0, 10], [-4, 10]], 'minY' => -2, 'maxY' => 4,
                        'entry' => ['x' => -2, 'y' => 0, 'z' => 7], 'activities' => [],
                    ],
                    [
                        'id' => 'pool-terrace', 'name' => 'Pool terrace', 'description' => 'An open terrace with an infinity pool.',
                        'floorId' => 'ground', 'parentId' => null, 'private' => false,
                        'outline' => [[0, -10], [10, -10], [10, 0], [0, 0]], 'minY' => -2, 'maxY' => 4,
                        'entry' => ['x' => 5, 'y' => 0, 'z' => -1],
                        'activities' => [['id' => 'swim', 'name' => 'Swim', 'posture' => null, 'pose' => null]],
                    ],
                    [
                        'id' => 'gallery', 'name' => 'Gallery', 'description' => 'An upper gallery overlooking the city.',
                        'floorId' => 'upper', 'parentId' => null, 'private' => false,
                        'outline' => [[-10, 0], [10, 0], [10, 10], [-10, 10]], 'minY' => 4, 'maxY' => 8,
                        'entry' => ['x' => 0, 'y' => 4, 'z' => 5], 'activities' => [],
                    ],
                ],
                'objects' => [
                    [
                        'id' => 'pool-lounger-1', 'name' => 'Pool lounger', 'description' => 'A white lounger by the pool.',
                        'position' => ['x' => 5, 'y' => 0, 'z' => -5], 'zoneId' => 'pool-terrace',
                        'spots' => [[
                            'id' => 'pool-lounger-1-seat',
                            'position' => ['x' => 5, 'y' => 0.3, 'z' => -5], 'facing' => 0.0,
                            'approach' => ['x' => 5, 'y' => 0, 'z' => -4.4],
                            'activities' => [['id' => 'recline', 'name' => 'Recline', 'posture' => 'reclining', 'pose' => null]],
                        ]],
                    ],
                ],
            ],
        ]);
    }
}
