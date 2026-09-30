<?php

namespace Database\Factories;

use App\Enums\AssistantKind;
use App\Models\Assistant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assistant>
 */
class AssistantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'prompt' => [
                'identity' => [fake()->sentence()],
            ],
            'opening_message' => fake()->sentence(),
            'kind' => AssistantKind::Assistant,
        ];
    }

    /**
     * Put term rule lines in a prompt section and pick that section for the term rule settings.
     *
     * @param  array{markTerms?: bool, swapInvariant?: bool, highlightMissing?: bool}  $settings
     */
    public function withTermRules(string $sectionText, array $settings = []): static
    {
        return $this->state(fn (array $attributes) => [
            'prompt' => [...($attributes['prompt'] ?? []), 'termRuleSection' => $sectionText],
            'agent_config' => [
                ...($attributes['agent_config'] ?? []),
                'termRules' => [
                    'section' => 'termRuleSection',
                    'markTerms' => false,
                    'swapInvariant' => false,
                    'highlightMissing' => false,
                    ...$settings,
                ],
            ],
        ]);
    }
}
