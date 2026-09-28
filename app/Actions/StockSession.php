<?php

namespace App\Actions;

use App\Models\WorldSession;
use Illuminate\Support\Facades\DB;

class StockSession
{
    public function __construct(private readonly ResolveInventory $resolveInventory) {}

    /**
     * Gives a new session a copy of every starting inventory of its world.
     */
    public function handle(WorldSession $session): void
    {
        DB::transaction(function () use ($session): void {
            $session->worldUser->world->startingInventories()->with('items')->get()
                ->each(fn ($starting) => $this->resolveInventory->copy($starting, $session));
        });
    }
}
