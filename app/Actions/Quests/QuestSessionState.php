<?php

namespace App\Actions\Quests;

use App\Actions\ResolveInventory;
use App\Enums\EndingStatus;
use App\Enums\InventoryHolder;
use App\Enums\QuestOfferStatus;
use App\Enums\QuestStatus;
use App\Models\CreditTransaction;
use App\Models\ItemTransfer;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\ResidentSentiment;
use App\Models\WorldSession;
use App\Models\WorldSessionCampaign;
use App\Models\WorldSessionQuest;
use Illuminate\Support\Collection;

/**
 * What quest conditions read about a session, loaded once per trigger:
 * the player's holdings, what they know and have told, how residents feel
 * about them, what they handed residents, and how other quests, their offers
 * and campaigns went.
 */
class QuestSessionState
{
    /**
     * @param  array<int, ?int>  $itemQuantities  quantity by item id; null is unlimited
     * @param  array<int, int>  $knownFactIds
     * @param  array<int, string>  $acknowledgements  "factId:residentId"
     * @param  Collection<string, WorldSessionQuest>  $latestRuns  by quest key
     * @param  Collection<string, WorldSessionCampaign>  $campaignEndings  by campaign key
     * @param  array<int, array<string, float>>  $sentiments  scores by sentiment name, by resident id
     * @param  array<string, int>  $itemsGiven  quantity the player handed over, by "residentId:itemId"
     * @param  array<int, int>  $creditsPaid  credits the player paid, by resident id
     * @param  array<string, array{pending: bool, lastAnswer: ?QuestOfferStatus, declined: int}>  $offers  by quest key; pending and lastAnswer are about the latest run
     */
    public function __construct(
        public readonly WorldSession $session,
        private readonly ?int $credits,
        private readonly array $itemQuantities,
        private readonly array $knownFactIds,
        private readonly array $acknowledgements,
        private readonly Collection $latestRuns,
        private readonly Collection $campaignEndings,
        private readonly array $sentiments = [],
        private readonly array $itemsGiven = [],
        private readonly array $creditsPaid = [],
        private readonly array $offers = [],
    ) {}

    public static function for(WorldSession $session): self
    {
        $player = app(ResolveInventory::class)->forPlayer($session);
        $latestRuns = $session->questRuns()->with('quest')->orderBy('run')->get()->keyBy(fn (WorldSessionQuest $run) => $run->quest->key);
        $residentByInventory = $session->inventories()->where('holder', InventoryHolder::Resident)->pluck('world_resident_id', 'id')->all();
        $handedOver = fn (string $model) => $model::query()
            ->where('world_session_id', $session->id)
            ->where('from_inventory_id', $player->id)
            ->whereIn('to_inventory_id', array_keys($residentByInventory));

        return new self(
            $session,
            $player->credits,
            $player->items()->pluck('quantity', 'item_id')->all(),
            $session->knownFacts()->pluck('fact_id')->all(),
            $session->factAcknowledgements()->get(['fact_id', 'world_resident_id'])->map(fn ($row) => "{$row->fact_id}:{$row->world_resident_id}")->all(),
            $latestRuns,
            $session->campaignEndings()->with('campaign')->get()->keyBy(fn (WorldSessionCampaign $ending) => $ending->campaign->key),
            ResidentSentiment::where('world_session_id', $session->id)->get()
                ->mapWithKeys(fn (ResidentSentiment $sentiment) => [$sentiment->world_resident_id => $sentiment->values ?? []])
                ->all(),
            $handedOver(ItemTransfer::class)
                ->selectRaw('to_inventory_id, item_id, sum(quantity) as total')->groupBy('to_inventory_id', 'item_id')->get()
                ->mapWithKeys(fn ($row) => [$residentByInventory[$row->to_inventory_id].':'.$row->item_id => (int) $row->total])
                ->all(),
            $handedOver(CreditTransaction::class)
                ->selectRaw('to_inventory_id, sum(amount) as total')->groupBy('to_inventory_id')->get()
                ->mapWithKeys(fn ($row) => [$residentByInventory[$row->to_inventory_id] => (int) $row->total])
                ->all(),
            self::offerHistory($session, $latestRuns),
        );
    }

    /**
     * @param  Collection<string, WorldSessionQuest>  $latestRuns
     * @return array<string, array{pending: bool, lastAnswer: ?QuestOfferStatus, declined: int}>
     */
    private static function offerHistory(WorldSession $session, Collection $latestRuns): array
    {
        $turnedDown = [QuestOfferStatus::Declined, QuestOfferStatus::Withdrawn];

        return QuestOffer::with('run.quest')->where('world_session_id', $session->id)->orderBy('id')->get()
            ->groupBy(fn (QuestOffer $offer) => $offer->run->quest->key)
            ->map(function (Collection $offers, string $key) use ($latestRuns, $turnedDown): array {
                $onLatest = $offers->where('world_session_quest_id', $latestRuns->get($key)?->id);

                return [
                    'pending' => $onLatest->contains('status', QuestOfferStatus::Pending),
                    'lastAnswer' => $onLatest->where('status', '!==', QuestOfferStatus::Pending)->last()?->status,
                    'declined' => $offers->filter(fn (QuestOffer $offer) => in_array($offer->status, $turnedDown, true))->count(),
                ];
            })
            ->all();
    }

    public function holds(int $itemId, int $atLeast): bool
    {
        if (! array_key_exists($itemId, $this->itemQuantities)) {
            return false;
        }

        return $this->itemQuantities[$itemId] === null || $this->itemQuantities[$itemId] >= $atLeast;
    }

    public function hasCredits(int $atLeast): bool
    {
        return $this->credits === null || $this->credits >= $atLeast;
    }

    /**
     * How many of the item the player holds: 0 when none, null when unlimited.
     */
    public function quantityOf(int $itemId): ?int
    {
        return array_key_exists($itemId, $this->itemQuantities) ? $this->itemQuantities[$itemId] : 0;
    }

    /**
     * The player's credits; null when unlimited.
     */
    public function credits(): ?int
    {
        return $this->credits;
    }

    /**
     * How the resident feels about the player in one sentiment; one that
     * never moved sits where every resident starts, at 0.
     */
    public function sentiment(int $residentId, string $kind): float
    {
        return (float) ($this->sentiments[$residentId][$kind] ?? 0);
    }

    public function gaveTo(int $residentId, int $itemId): int
    {
        return $this->itemsGiven["{$residentId}:{$itemId}"] ?? 0;
    }

    public function spentWith(int $residentId): int
    {
        return $this->creditsPaid[$residentId] ?? 0;
    }

    /**
     * Where another quest stands as quest conditions name it: offered,
     * active, declined or abandoned; otherwise its latest run's status, or
     * null when it has no run.
     */
    public function questState(string $questKey): ?string
    {
        $run = $this->latestRuns->get($questKey);
        if ($run === null) {
            return null;
        }

        $offers = $this->offers[$questKey] ?? ['pending' => false, 'lastAnswer' => null];
        if ($run->status === QuestStatus::Available) {
            return match (true) {
                $offers['pending'] => 'offered',
                in_array($offers['lastAnswer'], [QuestOfferStatus::Declined, QuestOfferStatus::Withdrawn], true) => 'declined',
                default => QuestStatus::Available->value,
            };
        }

        return $run->status->value;
    }

    /**
     * How many times the player turned down the quest's offers in the
     * session, across runs; offers left unanswered count as turned down.
     */
    public function declinedTimes(string $questKey): int
    {
        return $this->offers[$questKey]['declined'] ?? 0;
    }

    public function knows(int $factId): bool
    {
        return in_array($factId, $this->knownFactIds, true);
    }

    public function acknowledged(int $factId, int $residentId): bool
    {
        return in_array("{$factId}:{$residentId}", $this->acknowledgements, true);
    }

    /**
     * Whether the latest run of another quest ended with the flag among its resulting flags.
     */
    public function questEndedWithFlag(string $questKey, string $flag): bool
    {
        return in_array($flag, $this->latestRuns->get($questKey)?->resultingFlags() ?? [], true);
    }

    public function latestRun(Quest $quest): ?WorldSessionQuest
    {
        return $this->latestRuns->get($quest->key);
    }

    /**
     * Whether every requirement of the quest holds: each names another
     * quest's latest run, or a campaign's ending, and how it ended.
     */
    public function requirementsMet(Quest $quest): bool
    {
        return collect($quest->requirements())->every(function (array $requirement): bool {
            $outcome = $requirement['outcome'] ?? 'ended';

            if (isset($requirement['campaign'])) {
                $ending = $this->campaignEndings->get($requirement['campaign']);
                if ($ending === null) {
                    return false;
                }

                return str_starts_with($outcome, 'tier:')
                    ? $ending->ending_status === EndingStatus::Written && ($ending->ending['tier'] ?? null) === substr($outcome, 5)
                    : in_array($outcome, ['ended', 'completed'], true);
            }

            $run = $this->latestRuns->get($requirement['quest'] ?? '');
            if ($run === null || ! $run->status->hasEnded()) {
                return false;
            }

            return match (true) {
                $outcome === 'ended' => true,
                str_starts_with($outcome, 'tier:') => $run->ending_status === EndingStatus::Written && ($run->ending['tier'] ?? null) === substr($outcome, 5),
                default => $run->status === QuestStatus::from($outcome),
            };
        });
    }
}
