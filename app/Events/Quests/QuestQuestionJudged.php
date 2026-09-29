<?php

namespace App\Events\Quests;

/**
 * A judged question was answered yes. Conditions reading it are checked against the session as it is now.
 */
class QuestQuestionJudged extends QuestTriggerEvent
{
    public function leaves(): array
    {
        return ['question'];
    }
}
