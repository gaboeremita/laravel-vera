<?php

namespace App\Enums;

enum QuestStatus: string
{
    case Available = 'available';
    case Active = 'active';
    case Completed = 'completed';
    case Failed = 'failed';
    case Abandoned = 'abandoned';

    public function hasEnded(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Abandoned], true);
    }
}
