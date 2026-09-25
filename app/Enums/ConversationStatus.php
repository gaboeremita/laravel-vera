<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
}
