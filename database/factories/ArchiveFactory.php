<?php

namespace Database\Factories;

use App\Models\Archive;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Archive>
 */
class ArchiveFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'user_id' => User::factory(),
        ];
    }
}
