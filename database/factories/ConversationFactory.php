<?php

namespace Database\Factories;

use App\Models\AssistantUser;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assistant_user_id' => AssistantUser::factory(),
            'title' => fake()->sentence(3),
        ];
    }
}
