<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\TransferInventory;

class RemoveTool extends CreatorInventoryTool
{
    public function name(): string
    {
        return 'remove';
    }

    public function description(): string
    {
        return 'Takes credits or items away from the user or a resident, as the creator directs.';
    }

    public function handle(array $arguments): array
    {
        $holder = $this->holder((string) ($arguments['holder'] ?? ''));
        $amounts = $this->amounts($arguments);
        app(TransferInventory::class)->handle($holder, null, $amounts['credits'], $amounts['items'], 'removed by the creator', byCreator: true);

        return ['status' => 'removed', 'note' => "{$holder->displayName()} no longer has {$this->described($amounts)}."];
    }
}
