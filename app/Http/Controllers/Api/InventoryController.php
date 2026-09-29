<?php

namespace App\Http\Controllers\Api;

use App\Actions\ResolveInventory;
use App\Actions\TransferInventory;
use App\Exceptions\InsufficientInventory;
use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Traits\ResolvesWorldSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use ResolvesWorldSession;

    public function show(Request $request, int $world, int $session, ResolveInventory $resolveInventory): JsonResponse
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);

        return response()->json($resolveInventory->forPlayer($worldSession)->summary());
    }

    /**
     * Every credit change involving the player in the session, newest first.
     */
    public function creditHistory(Request $request, int $world, int $session, ResolveInventory $resolveInventory): JsonResponse
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        $player = $resolveInventory->forPlayer($worldSession);

        return response()->json($worldSession->creditTransactions()
            ->where(fn ($query) => $query->where('from_inventory_id', $player->id)->orWhere('to_inventory_id', $player->id))
            ->latest('id')
            ->get()
            ->map(fn (CreditTransaction $transaction) => [
                'amount' => $transaction->amount,
                'direction' => $transaction->to_inventory_id === $player->id ? 'in' : 'out',
                'counterpart' => $transaction->to_inventory_id === $player->id ? $transaction->from_name : $transaction->to_name,
                'reason' => $transaction->reason,
                'createdAt' => $transaction->created_at->toIso8601String(),
            ]));
    }

    /**
     * What the player can take from an object right now.
     */
    public function object(Request $request, int $world, int $session, string $object, ResolveInventory $resolveInventory): JsonResponse
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        $region = $worldSession->worldUser->world->regions()->findOrFail($request->integer('regionId'));
        abort_if($region->layoutObject($object) === null, 404);

        $takeable = $resolveInventory->forObject($worldSession, $region, $object)->items()->where('takeable', true)->with('item.cardImage')->get()
            ->map(fn (InventoryItem $held) => ['itemId' => $held->item_id, 'name' => $held->item->name, 'quantity' => $held->quantity, 'cardImageUrl' => $held->item->cardImage?->url])
            ->sortBy('name')->values();

        return response()->json(['takeable' => $takeable]);
    }

    public function take(Request $request, int $world, int $session, string $object, ResolveInventory $resolveInventory, TransferInventory $transferInventory): JsonResponse
    {
        $validated = $request->validate(['regionId' => ['required', 'integer'], 'itemId' => ['required', 'integer']]);
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        $region = $worldSession->worldUser->world->regions()->findOrFail($validated['regionId']);
        abort_if($region->layoutObject($object) === null, 404);

        $objectInventory = $resolveInventory->forObject($worldSession, $region, $object);
        if (! $objectInventory->items()->where('item_id', $validated['itemId'])->where('takeable', true)->exists()) {
            return response()->json(['message' => 'There is none of that left to take here.'], 422);
        }

        $player = $resolveInventory->forPlayer($worldSession);
        $before = $player->summary();
        try {
            $transferInventory->handle($objectInventory, $player, 0, [(int) $validated['itemId'] => 1], 'taken');
        } catch (InsufficientInventory) {
            return response()->json(['message' => 'There is none of that left to take here.'], 422);
        }
        $after = $player->summary();

        return response()->json(['inventory' => $after, 'changes' => Inventory::changesBetween($before, $after)]);
    }

    /**
     * The items a vendor has for sale; nothing for a resident who sells nothing.
     */
    public function goods(Request $request, int $world, int $session, int $resident, ResolveInventory $resolveInventory): JsonResponse
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        $worldResident = $worldSession->worldUser->world->residents()->findOrFail($resident);

        return response()->json($resolveInventory->forResident($worldSession, $worldResident)->summary(forSaleOnly: true)['items']);
    }
}
