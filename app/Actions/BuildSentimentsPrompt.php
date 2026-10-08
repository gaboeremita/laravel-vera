<?php

namespace App\Actions;

use App\Enums\TurnMode;
use App\Models\ResidentSentiment;

/**
 * How a resident feels about the user, for their own prompt while they talk
 * with the user: each of the world's sentiments with its scale and score. In
 * character the numbers shape the resident quietly; out of character and in
 * creator mode the resident can state and change them.
 */
class BuildSentimentsPrompt
{
    /**
     * @param  bool  $canUseTools  whether their model can call tools; changing sentiments needs them
     */
    public function handle(ResidentSentiment $sentiment, TurnMode $mode, bool $canUseTools = true): ?string
    {
        $sentiments = $sentiment->worldResident->world->sentiments ?? [];
        if (! $mode->withUser() || $sentiments === []) {
            return null;
        }

        $values = collect($sentiment->scores())->map(fn (float $value, string $name) => "{$name} ".self::format($value))->implode(', ');
        $lines = [
            'How you feel about the user right now, each on a scale from -10 to 10. Everyone starts at 0, a neutral feeling either way.',
            ...collect($sentiments)->map(fn (array $entry) => "- {$entry['name']}: {$entry['description']}")->all(),
            "Right now: {$values}. Read each value against its scale: near 0 you are still making up your mind, the further toward either end the stronger the feeling, and at the ends it governs how you treat them. Let each one set your warmth, your tone, how close you let them get, and what you are willing to share or do for them, in proportion to where it sits.",
        ];

        if ($mode === TurnMode::InCharacter) {
            $lines[] = 'Keep the numbers to yourself and show them through how you act.';
            if ($canUseTools) {
                $lines[] = 'When something in the conversation truly moves you, call adjust_sentiments in that same reply with the change, usually 1 to 3 points, and your reason.';
            }
        } else {
            $lines[] = 'The user is speaking outside the story: when they ask about these feelings, tell them the numbers plainly.';
            if ($canUseTools) {
                $lines[] = 'When they ask you to change them, call adjust_sentiments with the change that brings each one to the value they want.';
            }
        }

        return implode("\n", $lines);
    }

    public static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') ?: '0';
    }
}
