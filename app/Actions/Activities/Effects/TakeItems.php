<?php

namespace App\Actions\Activities\Effects;

use App\Actions\Activities\ActivityUse;
use App\Actions\TransferInventory;

/**
 * Takes items from the player into the object.
 */
class TakeItems extends ItemsEffect
{
    public function __construct(private readonly TransferInventory $transferInventory) {}

    public function type(): string
    {
        return 'takeItems';
    }

    public function missing(array $config, ActivityUse $use): ?string
    {
        $short = $this->shortOf($config, $use->player);
        if ($short === null) {
            return null;
        }
        $name = $short['item']?->name ?? 'an item';

        return $short['quantity'] === 1 ? "The player does not have the {$name} it takes." : "The player does not have the {$short['quantity']} {$name} it takes.";
    }

    public function describe(array $config, ActivityUse $use): string
    {
        return "The {$use->objectName()} takes {$this->listed($config)} from the player.";
    }

    public function apply(array $config, ActivityUse $use): void
    {
        $this->transferInventory->handle($use->player, $use->object, 0, $this->quantities($config), $use->reason());
    }
}
