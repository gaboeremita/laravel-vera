<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Sentiment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sentiment>
 */
class SentimentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'values' => [],
        ];
    }
}
