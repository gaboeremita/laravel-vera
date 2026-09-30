<?php

namespace App\Actions\Activities\Effects;

use App\Actions\Activities\ActivityUse;
use App\Actions\TransferInventory;

/**
 * Pays the player credits from the object's stock.
 */
class GiveCredits extends CreditsEffect
{
    public function __construct(private readonly TransferInventory $transferInventory) {}

    public function type(): string
    {
        return 'giveCredits';
    }

    public function missing(array $config, ActivityUse $use): ?string
    {
        return $use->object->credits !== null && $use->object->credits < $config['amount'] ? "The {$use->objectName()} has run out of credits to give." : null;
    }

    public function describe(array $config, ActivityUse $use): string
    {
        return "The player receives {$config['amount']} credits from the {$use->objectName()}.";
    }

    public function apply(array $config, ActivityUse $use): void
    {
        $this->transferInventory->handle($use->object, $use->player, $config['amount'], [], $use->reason());
    }
}
