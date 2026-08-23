<?php

namespace Database\Factories;

use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantUser>
 */
class AssistantUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assistant_id' => Assistant::factory(),
            'user_id' => User::factory(),
        ];
    }
}
