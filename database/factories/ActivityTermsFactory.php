<?php

namespace Database\Factories;

use App\Models\ActivityTerms;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityTerms>
 */
class ActivityTermsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'region_id' => Region::factory()->withLayout(),
            'object_id' => 'pool-lounger-1',
            'activity_id' => 'recline',
            'cost' => 0,
            'gives_credits' => 0,
            'gives_items' => [],
        ];
    }
}
