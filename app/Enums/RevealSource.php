<?php

namespace App\Enums;

enum RevealSource: string
{
    case InCharacter = 'in_character';
    case OocTurn = 'ooc_turn';
    case Creator = 'creator';
    case Item = 'item';
    case Activity = 'activity';
}
