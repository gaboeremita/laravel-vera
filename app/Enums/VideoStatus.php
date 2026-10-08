<?php

namespace App\Enums;

enum VideoStatus: string
{
    case Queued = 'queued';
    case Generating = 'generating';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
