<?php

namespace App\Enums;

enum HandoverRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Unaffordable = 'unaffordable';
    case Cancelled = 'cancelled';
}
