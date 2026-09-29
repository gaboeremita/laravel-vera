<?php

namespace App\Actions\Quests;

use App\Enums\QuestEventType;
use App\Models\Quest;
use App\Models\QuestEvent;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;

/**
 * Where a quest's offerQuestion stands in a session, read from the judge's
 * answers in the event log. A yes holds for every later run, until the
 * author changes the question's text.
 */
class OfferQuestionStatus
{
    public static function hash(string $text): string
    {
        return substr(md5($text), 0, 16);
    }

    /**
     * What an offerQuestion's signals and answers record beside the usual
     * payload: the hash of the text they were about, so an edited question
     * needs a new yes.
     *
     * @return array{textHash?: string}
     */
    public static function textHash(WorldSessionQuest $run, string $questionId): array
    {
        return $questionId === Quest::OFFER_QUESTION_ID ? ['textHash' => self::hash((string) $run->quest->offerQuestion())] : [];
    }

    /**
     * @return array{met: bool, reason: ?string} reason is the latest answer's when it isn't met
     */
    public function handle(WorldSession $session, Quest $quest): array
    {
        $answers = QuestEvent::where('type', QuestEventType::QuestionJudged)
            ->whereIn('world_session_quest_id', $session->questRuns()->where('quest_id', $quest->id)->select('id'))
            ->where('payload->question', Quest::OFFER_QUESTION_ID)
            ->where('payload->textHash', self::hash((string) $quest->offerQuestion()))
            ->latest('id')
            ->get();

        if ($answers->contains(fn (QuestEvent $answer) => ($answer->payload['met'] ?? false) === true)) {
            return ['met' => true, 'reason' => null];
        }

        return ['met' => false, 'reason' => $answers->first()?->payload['reason'] ?? null];
    }
}
