<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\TransferInventory;

class GrantTool extends CreatorInventoryTool
{
    public function name(): string
    {
        return 'grant';
    }

    public function description(): string
    {
        return 'Gives the user or a resident credits or items out of nothing, as the creator directs.';
    }

    public function handle(array $arguments): array
    {
        $holder = $this->holder((string) ($arguments['holder'] ?? ''));
        $amounts = $this->amounts($arguments);
        app(TransferInventory::class)->handle(null, $holder, $amounts['credits'], $amounts['items'], 'granted by the creator', byCreator: true);

        return ['status' => 'granted', 'note' => "{$holder->displayName()} now has {$this->described($amounts)} more."];
    }
}
