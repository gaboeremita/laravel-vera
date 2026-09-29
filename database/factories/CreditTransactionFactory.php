<?php

namespace Database\Factories;

use App\Models\CreditTransaction;
use App\Models\WorldSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditTransaction>
 */
class CreditTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'from_name' => fake()->firstName(),
            'to_name' => fake()->firstName(),
            'amount' => fake()->numberBetween(1, 100),
            'reason' => 'gift',
        ];
    }
}
