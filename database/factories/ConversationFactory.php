<?php

namespace Database\Factories;

use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\WorldSession;
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

    public function forWorldSession(WorldSession $session): static
    {
        return $this->state(fn () => ['world_session_id' => $session->id]);
    }
}
