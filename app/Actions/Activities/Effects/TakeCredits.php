<?php

namespace App\Actions\Activities\Effects;

use App\Actions\Activities\ActivityUse;
use App\Actions\TransferInventory;

/**
 * Charges the player credits, paid to the object.
 */
class TakeCredits extends CreditsEffect
{
    public function __construct(private readonly TransferInventory $transferInventory) {}

    public function type(): string
    {
        return 'takeCredits';
    }

    public function missing(array $config, ActivityUse $use): ?string
    {
        return $use->player->credits !== null && $use->player->credits < $config['amount'] ? "It costs {$config['amount']} credits and the player has only {$use->player->credits}." : null;
    }

    public function describe(array $config, ActivityUse $use): string
    {
        return "The player pays {$config['amount']} credits.";
    }

    public function apply(array $config, ActivityUse $use): void
    {
        $this->transferInventory->handle($use->player, $use->object, $config['amount'], [], $use->reason());
    }
}
