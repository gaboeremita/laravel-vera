<?php

namespace App\Enums;

enum QuestEventType: string
{
    case Started = 'started';
    case BeatFinished = 'beat_finished';
    case BeatUndone = 'beat_undone';
    case FlagSet = 'flag_set';
    case FlagCleared = 'flag_cleared';
    case QuestionSignalled = 'question_signalled';
    case QuestionJudged = 'question_judged';
    case Offered = 'offered';
    case OfferDeclined = 'offer_declined';
    case OfferWithdrawn = 'offer_withdrawn';
    case Completed = 'completed';
    case Failed = 'failed';
    case Abandoned = 'abandoned';
    case Reset = 'reset';
    case DefinitionEdited = 'definition_edited';
    case EndingWritten = 'ending_written';
    case EndingFailed = 'ending_failed';
}
