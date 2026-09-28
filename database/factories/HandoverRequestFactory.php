<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\Inventory;
use App\Models\WorldSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HandoverRequest>
 */
class HandoverRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'conversation_id' => Conversation::factory(),
            'inventory_id' => Inventory::factory(),
            'credits' => 10,
            'items' => [],
            'reason' => fake()->sentence(3),
        ];
    }
}
