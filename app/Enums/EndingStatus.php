<?php

namespace App\Enums;

enum EndingStatus: string
{
    case Pending = 'pending';
    case Written = 'written';
    case Failed = 'failed';
}
