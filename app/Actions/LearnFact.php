<?php

namespace App\Actions;

use App\Enums\RevealSource;
use App\Events\Quests\FactLearned;
use App\Models\Fact;
use App\Models\KnownFact;
use App\Models\RevealAttempt;
use App\Models\WorldResident;
use App\Models\WorldSession;

/**
 * The one way a fact becomes known to the player, and the log every attempt
 * to make it known is written to.
 */
class LearnFact
{
    /**
     * @param  array{holder?: ?WorldResident, holderName: string, reason?: ?string, reviewed: bool, approved: bool, verdict?: ?string}  $attempt
     */
    public function recordAttempt(WorldSession $session, Fact $fact, RevealSource $source, array $attempt): RevealAttempt
    {
        return RevealAttempt::create([
            'world_session_id' => $session->id,
            'fact_id' => $fact->id,
            'world_resident_id' => $attempt['holder']?->id ?? null,
            'fact_topic' => $fact->topic,
            'holder_name' => $attempt['holderName'],
            'source' => $source,
            'reason' => $attempt['reason'] ?? null,
            'reviewed' => $attempt['reviewed'],
            'approved' => $attempt['approved'],
            'verdict' => $attempt['verdict'] ?? null,
        ]);
    }

    /**
     * The player finds a fact through an item or an activity: logged as an
     * unreviewed attempt, with the narration as what they were told.
     */
    public function fromTheWorld(WorldSession $session, Fact $fact, RevealSource $source, string $sourceName, string $narration): ?KnownFact
    {
        $this->recordAttempt($session, $fact, $source, ['holderName' => $sourceName, 'reviewed' => false, 'approved' => true]);

        return $this->handle($session, $fact, $source, $sourceName, $narration);
    }

    /**
     * Marks the fact known to the player; a fact already known keeps its
     * first source and summary.
     *
     * @return ?KnownFact the fact when it is newly known, null when it already was
     */
    public function handle(WorldSession $session, Fact $fact, RevealSource $source, string $sourceName, string $summary): ?KnownFact
    {
        $known = KnownFact::firstOrCreate(
            ['world_session_id' => $session->id, 'fact_id' => $fact->id],
            ['source' => $source, 'source_name' => $sourceName, 'summary' => $summary],
        );

        if (! $known->wasRecentlyCreated) {
            return null;
        }

        FactLearned::dispatch($session->id);

        return $known->setRelation('fact', $fact);
    }
}
