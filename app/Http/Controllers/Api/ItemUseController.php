<?php

namespace App\Http\Controllers\Api;

use App\Actions\Narrate;
use App\Actions\LearnFact;
use App\Actions\ResolveInventory;
use App\Actions\TransferInventory;
use App\Enums\RevealSource;
use App\Exceptions\NarratorUnavailable;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\WorldSession;
use App\Traits\ResolvesWorldSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemUseController extends Controller
{
    use ResolvesWorldSession;

    public function __construct(
        private readonly ResolveInventory $resolveInventory,
        private readonly TransferInventory $transferInventory,
        private readonly Narrate $narrate,
    ) {}

    public function examine(Request $request, int $world, int $session, int $item): JsonResponse
    {
        [$worldSession, $player, $held] = $this->heldItem($request, $world, $session, $item);
        if ($held === null) {
            return response()->json(['message' => 'You are not carrying that.'], 422);
        }

        try {
            $verdict = $this->narrate->handle($worldSession->worldUser->world, $worldSession->region, array_filter([
                'Who' => $player->displayName(),
                'Doing' => "examining the {$held->name} ({$held->description})",
                'What examining it reveals' => $held->contents ?: 'nothing more than what is plain to see',
                'What they learn from it' => $held->revealsFact?->content,
                'Verdict' => 'this always succeeds; describe what they find',
            ]));
        } catch (NarratorUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $learned = $held->revealsFact !== null
            ? app(LearnFact::class)->fromTheWorld($worldSession, $held->revealsFact, RevealSource::Item, $held->name, $verdict['narration'])
            : null;

        return response()->json(['narration' => $verdict['narration'], 'succeeded' => true, 'learnedFacts' => $learned !== null ? [$learned->toPayload()] : []]);
    }

    public function use(Request $request, int $world, int $session, int $item): JsonResponse
    {
        $attempt = $request->validate(['attempt' => ['nullable', 'string', 'max:500']])['attempt'] ?? null;
        [$worldSession, $player, $held] = $this->heldItem($request, $world, $session, $item);
        if ($held === null) {
            return response()->json(['message' => 'You are not carrying that.'], 422);
        }
        $before = $player->summary();

        try {
            $verdict = filled($held->use_requirement)
                ? $this->narrate->handle($worldSession->worldUser->world, $worldSession->region, [
                    'Who' => $player->displayName(),
                    'Doing' => "using the {$held->name} ({$held->description})",
                    'Requirement' => $held->use_requirement,
                    'What examining it reveals' => $held->contents ?: 'nothing more than what is plain to see',
                    "{$player->displayName()} carries" => $this->narrate->holdings($player),
                    'What they do or say' => $attempt ?: 'nothing in particular',
                ])
                : ['succeeded' => true, 'narration' => "You use the {$held->name}."];
        } catch (NarratorUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        if ($verdict['succeeded']) {
            DB::transaction(function () use ($held, $player): void {
                if ($held->consumed_on_use) {
                    $this->transferInventory->handle($player, null, 0, [$held->id => 1], "used {$held->name}");
                }
                $releases = collect($held->releases_items ?? [])->mapWithKeys(fn (array $entry) => [(int) $entry['itemId'] => (int) $entry['quantity']])->all();
                $this->transferInventory->handle(null, $player, $held->releases_credits, $releases, $held->name);
            });
        }

        $after = $player->summary();

        return response()->json([...$verdict, 'inventory' => $after, 'changes' => Inventory::changesBetween($before, $after)]);
    }

    /**
     * @return array{0: WorldSession, 1: Inventory, 2: ?Item}
     */
    private function heldItem(Request $request, int $world, int $session, int $item): array
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        $player = $this->resolveInventory->forPlayer($worldSession);
        $held = $player->items()->where('item_id', $item)->exists() ? Item::where('world_id', $worldSession->worldUser->world_id)->find($item) : null;

        return [$worldSession, $player, $held];
    }
}
