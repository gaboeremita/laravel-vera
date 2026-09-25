<?php

namespace Database\Factories;

use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\User;
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
            'owner_type' => (new User)->getMorphClass(),
            'owner_id' => User::factory(),
            'counterpart_type' => (new Assistant)->getMorphClass(),
            'counterpart_id' => Assistant::factory(),
            'title' => fake()->sentence(3),
        ];
    }

    /**
     * A user's chat with an assistant also needs the pair the chat belongs to.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Conversation $conversation): void {
            if ($conversation->owner_type !== (new User)->getMorphClass() || $conversation->counterpart_type !== (new Assistant)->getMorphClass()) {
                return;
            }

            $exists = AssistantUser::where('user_id', $conversation->owner_id)->where('assistant_id', $conversation->counterpart_id)->exists();
            if (! $exists) {
                AssistantUser::factory()->create(['user_id' => $conversation->owner_id, 'assistant_id' => $conversation->counterpart_id]);
            }
        });
    }

    public function forAssistantUser(AssistantUser $assistantUser): static
    {
        return $this->state(fn () => ['owner_id' => $assistantUser->user_id, 'counterpart_id' => $assistantUser->assistant_id]);
    }

    public function betweenAssistants(Assistant $owner, Assistant $counterpart): static
    {
        return $this->state(fn () => [
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->id,
            'counterpart_type' => $counterpart->getMorphClass(),
            'counterpart_id' => $counterpart->id,
        ]);
    }

    public function forWorldSession(WorldSession $session): static
    {
        return $this->state(fn () => ['world_session_id' => $session->id]);
    }
}
