<?php

namespace App\Actions\Activities\Effects;

use App\Actions\Activities\ActivityUse;
use App\Actions\TransferInventory;

/**
 * Hands the player items from the object's stock.
 */
class GiveItems extends ItemsEffect
{
    public function __construct(private readonly TransferInventory $transferInventory) {}

    public function type(): string
    {
        return 'giveItems';
    }

    public function missing(array $config, ActivityUse $use): ?string
    {
        return $this->shortOf($config, $use->object) === null ? null : "The {$use->objectName()} has run out of what it gives.";
    }

    public function describe(array $config, ActivityUse $use): string
    {
        return "The player receives {$this->listed($config)} from the {$use->objectName()}.";
    }

    public function apply(array $config, ActivityUse $use): void
    {
        $this->transferInventory->handle($use->object, $use->player, 0, $this->quantities($config), $use->reason());
    }
}
