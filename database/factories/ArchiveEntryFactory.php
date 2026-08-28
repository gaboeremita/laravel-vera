<?php

namespace Database\Factories;

use App\Models\Archive;
use App\Models\ArchiveEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArchiveEntry>
 */
class ArchiveEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'archive_id' => Archive::factory(),
            'title' => fake()->sentence(3),
            'content' => fake()->paragraph(),
            'keywords' => fake()->words(3),
            'embedding' => null,
        ];
    }
}
