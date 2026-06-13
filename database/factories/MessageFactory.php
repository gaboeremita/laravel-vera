<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        $roles = ['user', 'assistant'];

        return [
            'conversation_id' => Conversation::factory(),
            'role' => fake()->randomElement($roles),
            'content' => fake()->paragraph(),
            'thinking' => fake()->optional(0.3)->paragraph(),
            'image' => null,
            'emotion' => fake()->optional(0.5)->randomElement([
                'neutral', 'happy', 'angry', 'annoyed', 'sad', 'surprised',
                'flirty', 'embarrassed', 'confused', 'content', 'amused', 'sultry',
            ]),
        ];
    }
}
