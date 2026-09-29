<?php

namespace App\Enums;

enum InventoryHolder: string
{
    case Player = 'player';
    case Resident = 'resident';
    case Object = 'object';

    /**
     * A resident pays and is paid without ever running out, so only the player
     * and objects have a credit balance.
     */
    public function countsCredits(): bool
    {
        return $this !== self::Resident;
    }
}
