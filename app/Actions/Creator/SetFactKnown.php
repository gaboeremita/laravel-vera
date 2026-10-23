<?php

namespace App\Actions\Creator;

use App\Actions\LearnFact;
use App\Enums\RevealSource;
use App\Models\Fact;
use App\Models\KnownFact;
use App\Models\WorldSession;

/**
 * Makes the player know a secret of the world, or forget it, as the creator directs.
 */
class SetFactKnown
{
    public function __construct(private readonly LearnFact $learnFact) {}

    /**
     * @return ?KnownFact the fact as the player now knows it, when it became known
     */
    public function handle(WorldSession $session, Fact $fact, bool $known): ?KnownFact
    {
        $this->learnFact->recordAttempt($session, $fact, RevealSource::Creator, [
            'holder' => $fact->holder,
            'holderName' => $fact->holder->assistant->name,
            'reason' => $known ? 'Marked known by the creator' : null,
            'reviewed' => false,
            'approved' => $known,
            'verdict' => $known ? null : 'Marked unknown by the creator',
        ]);

        if (! $known) {
            KnownFact::where('world_session_id', $session->id)->where('fact_id', $fact->id)->delete();

            return null;
        }

        return $this->learnFact->handle($session, $fact, RevealSource::Creator, 'Creator', $fact->content);
    }
}
