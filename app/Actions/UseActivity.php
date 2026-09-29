<?php

namespace App\Actions;

use App\Models\ActivityTerms;
use App\Models\Inventory;
use App\Models\Region;
use App\Models\WorldSession;
use Illuminate\Support\Facades\DB;

class UseActivity
{
    public function __construct(
        private readonly ResolveInventory $resolveInventory,
        private readonly TransferInventory $transferInventory,
        private readonly Narrate $narrate,
    ) {}

    public function terms(Region $region, string $objectId, string $activityId): ?ActivityTerms
    {
        return $region->activityTerms()->with('requiredItem')->where('object_id', $objectId)->where('activity_id', $activityId)->first();
    }

    /**
     * Why the actor cannot use the activity for lack of an item, credits or
     * the object's stock; null when nothing like that stands in the way.
     */
    public function missing(ActivityTerms $terms, Inventory $actor, Inventory $object): ?string
    {
        if ($terms->required_item_id !== null) {
            $held = $actor->items()->where('item_id', $terms->required_item_id)->first();
            if ($held === null || ($held->quantity !== null && $held->quantity < 1)) {
                return "Needs the {$terms->requiredItem->name}.";
            }
        }
        $cost = $this->costFor($terms, $actor);
        if ($cost > 0 && $actor->credits !== null && $actor->credits < $cost) {
            return "Costs {$cost} credits.";
        }
        $payout = $this->payoutFor($terms, $actor);
        if ($payout > 0 && $object->credits !== null && $object->credits < $payout) {
            return 'There is nothing left to get here.';
        }
        foreach ($terms->gives_items ?? [] as $entry) {
            $stock = $object->items()->where('item_id', $entry['itemId'])->first();
            if ($stock === null || ($stock->quantity !== null && $stock->quantity < $entry['quantity'])) {
                return 'There is nothing left to get here.';
            }
        }

        return null;
    }

    /**
     * Checks the activity's terms, asks the narrator about plain-language ones,
     * and applies what it costs and gives.
     *
     * @param  bool  $byPlayer  whether the player is the one using it; only the player learns the secret it may reveal
     * @return array{allowed: bool, reason: ?string, narration: ?string, action: ?string}
     */
    public function handle(WorldSession $session, Region $region, string $objectId, string $activityId, Inventory $actor, string $actorName, ?string $attempt, bool $byPlayer = false): array
    {
        $terms = $this->terms($region, $objectId, $activityId);
        if ($terms === null) {
            return ['allowed' => true, 'reason' => null, 'narration' => null, 'action' => null];
        }

        $object = $this->resolveInventory->forObject($session, $region, $objectId);
        $missing = $this->missing($terms, $actor, $object);
        if ($missing !== null) {
            return ['allowed' => false, 'reason' => $missing, 'narration' => null, 'action' => null];
        }

        $layoutObject = $region->layoutObject($objectId);
        $activityName = $region->objectActivities($objectId)[$activityId]['name'] ?? $activityId;
        $narration = null;
        $action = null;
        $revealed = $byPlayer ? $terms->revealsFact : null;
        if ($terms->hasPlainLanguageTerms() || $revealed !== null) {
            $verdict = $this->narrate->handle($session->worldUser->world, $region, array_filter([
                'Who' => $actorName,
                'Doing' => "{$activityName} at the {$layoutObject['name']} ({$layoutObject['description']})",
                'Requirement' => $terms->requirement ?: 'none; it succeeds',
                'Outcome when it succeeds' => $terms->outcome ?: 'the activity simply happens',
                "{$actorName} carries" => $this->narrate->holdings($actor),
                'What they do or say' => $attempt ?: 'nothing in particular',
                'What they learn when it succeeds' => $revealed?->content,
            ]));
            if (! $verdict['succeeded']) {
                return ['allowed' => false, 'reason' => null, 'narration' => $verdict['narration'], 'action' => $verdict['action']];
            }
            $narration = $verdict['narration'];
            $action = $verdict['action'];
        }

        $reason = "{$layoutObject['name']}: {$activityName}";
        $cost = $this->costFor($terms, $actor);
        $payout = $this->payoutFor($terms, $actor);
        DB::transaction(function () use ($terms, $actor, $object, $reason, $cost, $payout): void {
            $consumed = $terms->consumes_required && $terms->required_item_id !== null ? [$terms->required_item_id => 1] : [];
            $this->transferInventory->handle($actor, $object, $cost, $consumed, $reason);
            $gives = collect($terms->gives_items ?? [])->mapWithKeys(fn (array $entry) => [(int) $entry['itemId'] => (int) $entry['quantity']])->all();
            $this->transferInventory->handle($object, $actor, $payout, $gives, $reason);
        });

        return ['allowed' => true, 'reason' => null, 'narration' => $narration, 'action' => $action];
    }

    /**
     * What the actor pays for the activity. Residents only play at paying, so
     * nothing is charged to them.
     */
    private function costFor(ActivityTerms $terms, Inventory $actor): int
    {
        return $actor->holder->countsCredits() ? $terms->cost : 0;
    }

    /**
     * The credits the activity pays the actor. A resident has no balance to
     * pay into, so the object keeps its credits.
     */
    private function payoutFor(ActivityTerms $terms, Inventory $actor): int
    {
        return $actor->holder->countsCredits() ? $terms->gives_credits : 0;
    }
}
