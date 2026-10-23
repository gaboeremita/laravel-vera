<?php

namespace App\Http\Controllers\Api;

use App\Actions\ResolveInventory;
use App\Actions\TransferInventory;
use App\Enums\InventoryHolder;
use App\Exceptions\InsufficientInventory;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\WorldSession;
use App\Traits\ResolvesCreatorSession;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Credits and items moved by the creator: made from nothing, taken to
 * nothing, or carried between any two holders in the session.
 */
class CreatorInventoryController extends Controller
{
    use ResolvesCreatorSession;

    private const HOLDER_RULES = ['type' => ['required', 'in:player,resident,object'], 'residentId' => ['nullable', 'integer'], 'regionId' => ['nullable', 'integer'], 'objectId' => ['nullable', 'string', 'max:100']];

    public function __construct(
        private readonly ResolveInventory $resolveInventory,
        private readonly TransferInventory $transferInventory,
    ) {}

    public function storeCredits(Request $request, int $world, int $session): JsonResponse
    {
        $worldSession = $this->resolveCreatorSession($request, $world, $session);
        $amount = $request->validate(['amount' => ['required', 'integer', 'not_in:0']])['amount'];
        $player = $this->resolveInventory->forPlayer($worldSession);

        return $this->changingPlayer($worldSession, function () use ($player, $amount): void {
            if ($amount > 0) {
                $this->transferInventory->handle(null, $player, $amount, [], 'granted by the creator', byCreator: true);
            } elseif ($player->credits !== null) {
                $this->transferInventory->handle($player, null, min(-$amount, $player->credits), [], 'removed by the creator', byCreator: true);
            }
        });
    }

    public function show(Request $request, int $world, int $session): JsonResponse
    {
        $worldSession = $this->resolveCreatorSession($request, $world, $session);
        $request->validate($this->holderRules('holder'));
        $inventory = $this->holder($request->input('holder'), $worldSession);

        return response()->json([
            'credits' => $inventory->credits,
            'countsCredits' => $inventory->holder->countsCredits(),
            'items' => $inventory->items()->with('item.cardImage')->get()
                ->sortBy(fn (InventoryItem $held) => $held->item->name)
                ->map(fn (InventoryItem $held) => [
                    'itemId' => $held->item_id,
                    'name' => $held->item->name,
                    'quantity' => $held->quantity,
                    'takeable' => (bool) $held->takeable,
                    'cardImageUrl' => $held->item->cardImage?->url,
                ])->values(),
        ]);
    }

    public function grant(Request $request, int $world, int $session): JsonResponse
    {
        $worldSession = $this->resolveCreatorSession($request, $world, $session);
        $validated = $request->validate([...$this->holderRules('holder'), 'itemId' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1']]);
        $holder = $this->holder($validated['holder'], $worldSession);
        $item = Item::availableIn($worldSession)->findOrFail($validated['itemId']);

        return $this->changingPlayer($worldSession, function () use ($holder, $item, $validated): void {
            $this->transferInventory->handle(null, $holder, 0, [$item->id => $validated['quantity']], 'granted by the creator', byCreator: true);
            $this->leaveTakeable($holder, $item);
        });
    }

    public function move(Request $request, int $world, int $session): JsonResponse
    {
        $worldSession = $this->resolveCreatorSession($request, $world, $session);
        $validated = $request->validate([...$this->holderRules('from'), ...$this->holderRules('to'), 'itemId' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1']]);
        $from = $this->holder($validated['from'], $worldSession);
        $to = $this->holder($validated['to'], $worldSession);
        $item = Item::availableIn($worldSession)->findOrFail($validated['itemId']);

        try {
            return $this->changingPlayer($worldSession, function () use ($from, $to, $item, $validated): void {
                $this->transferInventory->handle($from, $to, 0, [$item->id => $validated['quantity']], 'moved by the creator', byCreator: true);
                $this->leaveTakeable($to, $item);
            });
        } catch (InsufficientInventory $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function holderRules(string $key): array
    {
        return collect(self::HOLDER_RULES)->mapWithKeys(fn (array $rules, string $field) => ["{$key}.{$field}" => $rules])->prepend(['required', 'array'], $key)->all();
    }

    /**
     * @param  array{type: string, residentId?: ?int, regionId?: ?int, objectId?: ?string}  $holder
     */
    private function holder(array $holder, WorldSession $session): Inventory
    {
        $worldModel = $session->worldUser->world;

        return match ($holder['type']) {
            'player' => $this->resolveInventory->forPlayer($session),
            'resident' => $this->resolveInventory->forResident($session, $worldModel->residents()->findOrFail((int) ($holder['residentId'] ?? 0))),
            'object' => (function () use ($worldModel, $holder, $session): Inventory {
                $region = $worldModel->regions()->findOrFail((int) ($holder['regionId'] ?? 0));
                abort_if($region->layoutObject((string) ($holder['objectId'] ?? '')) === null, 404);

                return $this->resolveInventory->forObject($session, $region, $holder['objectId']);
            })(),
        };
    }

    /**
     * Items the creator leaves in an object can be taken out again in play.
     */
    private function leaveTakeable(Inventory $holder, Item $item): void
    {
        if ($holder->holder === InventoryHolder::Object) {
            $holder->items()->where('item_id', $item->id)->update(['takeable' => true]);
        }
    }

    /**
     * Runs a change and answers with the player's inventory and what changed in it.
     *
     * @param  Closure(): void  $change
     */
    private function changingPlayer(WorldSession $session, Closure $change): JsonResponse
    {
        $player = $this->resolveInventory->forPlayer($session);
        $before = $player->summary();
        $change();
        $after = $player->fresh()->summary();

        return response()->json(['inventory' => $after, 'changes' => Inventory::changesBetween($before, $after)]);
    }
}
