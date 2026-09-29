<?php

namespace App\Actions;

use App\Models\KnownFact;
use App\Models\World;
use Throwable;

/**
 * What the player was actually told about a fact, written once from the
 * character's reply, for the player's list of learned facts.
 */
class SummarizeLearnedFact
{
    public const NOTHING_TOLD = 'You haven\'t heard the details yet.';

    public function __construct(private readonly ResolveNarratorModel $resolveNarratorModel) {}

    public function handle(World $world, KnownFact $known, string $characterName, string $reply): KnownFact
    {
        try {
            $response = $this->resolveNarratorModel->handle($world)->chat(messages: [
                ['role' => 'system', 'content' => 'You keep the player\'s journal in a role-playing world. From what a character just said, write in one to three sentences, in second person, what the player was told about the given topic, holding to what was actually said. When the character said nothing about it, answer exactly: '.self::NOTHING_TOLD],
                ['role' => 'user', 'content' => "Topic: {$known->fact->topic}\n\nWhat {$characterName} said:\n{$reply}"],
            ]);
            $summary = trim((string) $response->content);
        } catch (Throwable $e) {
            report($e);
            $summary = '';
        }

        $known->update(['summary' => $summary !== '' ? $summary : $reply]);

        return $known;
    }
}
