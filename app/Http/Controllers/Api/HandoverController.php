<?php

namespace App\Http\Controllers\Api;

use App\Actions\DescribeHandover;
use App\Actions\ResolveInventory;
use App\Actions\TransferInventory;
use App\Enums\HandoverRequestStatus;
use App\Exceptions\InsufficientInventory;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHandoverRequest;
use App\Models\HandoverRequest;
use App\Models\Inventory;
use App\Traits\ResolvesWorldSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HandoverController extends Controller
{
    use ResolvesWorldSession;

    public function __construct(
        private readonly ResolveInventory $resolveInventory,
        private readonly TransferInventory $transferInventory,
        private readonly DescribeHandover $describeHandover,
    ) {}

    public function store(StoreHandoverRequest $request, int $world, int $session): JsonResponse
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        $resident = $worldSession->worldUser->world->residents()->with('assistant')->findOrFail($request->integer('residentId'));
        $player = $this->resolveInventory->forPlayer($worldSession);
        $before = $player->summary();
        $credits = $request->integer('credits');
        $items = $request->itemQuantities();

        try {
            $this->transferInventory->handle($player, $this->resolveInventory->forResident($worldSession, $resident), $credits, $items, 'gift');
        } catch (InsufficientInventory $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'line' => "[{$player->displayName()} hands you {$this->describeHandover->handle($credits, $items)}]",
            ...$this->playerState($player, $before),
        ]);
    }

    public function answer(Request $request, int $world, int $session, int $handoverRequest): JsonResponse
    {
        $accept = $request->validate(['accept' => ['required', 'boolean']])['accept'];
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        $player = $this->resolveInventory->forPlayer($worldSession);
        $before = $player->summary();

        $answered = DB::transaction(function () use ($worldSession, $handoverRequest, $accept, $player): ?HandoverRequest {
            $pending = $worldSession->handoverRequests()->with('inventory')->lockForUpdate()->findOrFail($handoverRequest);
            if ($pending->status !== HandoverRequestStatus::Pending) {
                return null;
            }

            $status = HandoverRequestStatus::Declined;
            if ($accept) {
                try {
                    $this->transferInventory->handle($player, $pending->inventory, $pending->credits, $pending->itemQuantities(), $pending->reason);
                    $status = HandoverRequestStatus::Accepted;
                } catch (InsufficientInventory) {
                    $status = HandoverRequestStatus::Unaffordable;
                }
            }
            $pending->update(['status' => $status, 'answered_at' => now()]);

            return $pending;
        });

        if ($answered === null) {
            return response()->json(['message' => 'This request is no longer waiting for an answer.'], 409);
        }

        $what = $this->describeHandover->handle($answered->credits, $answered->itemQuantities());
        $name = $player->displayName();
        $line = match ($answered->status) {
            HandoverRequestStatus::Accepted => "[{$name} agrees and hands you {$what} {$answered->reason}]",
            HandoverRequestStatus::Unaffordable => "[{$name} can't give you {$what} {$answered->reason}: they don't have it]",
            default => "[{$name} declines to give you {$what} {$answered->reason}]",
        };

        return response()->json(['status' => $answered->status->value, 'line' => $line, ...$this->playerState($player, $before)]);
    }

    public function cancel(Request $request, int $world, int $session, int $conversation): JsonResponse
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        $worldSession->handoverRequests()
            ->where('conversation_id', $conversation)
            ->where('status', HandoverRequestStatus::Pending)
            ->update(['status' => HandoverRequestStatus::Cancelled, 'answered_at' => now()]);

        return response()->json(status: 204);
    }

    /**
     * @param  array{credits: ?int, items: array<int, array<string, mixed>>}  $before
     * @return array{inventory: array<string, mixed>, changes: array<string, mixed>}
     */
    private function playerState(Inventory $player, array $before): array
    {
        $after = $player->summary();

        return ['inventory' => $after, 'changes' => Inventory::changesBetween($before, $after)];
    }
}
