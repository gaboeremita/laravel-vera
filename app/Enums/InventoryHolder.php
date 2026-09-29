<?php

namespace App\Enums;

enum InventoryHolder: string
{
    case Player = 'player';
    case Resident = 'resident';
    case Object = 'object';
}
