<?php

namespace App\Enums;

/**
 * What kind of turn a character is taking, which decides how much of the
 * world's secrets they are given.
 */
enum TurnMode: string
{
    case InCharacter = 'in_character';
    case OocTurn = 'ooc_turn';
    case Creator = 'creator';
    case BetweenResidents = 'between_residents';

    public function withUser(): bool
    {
        return $this !== self::BetweenResidents;
    }
}
