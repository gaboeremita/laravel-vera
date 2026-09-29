<?php

namespace App\Actions\Quests;

use App\Actions\ResolveInventory;
use App\Enums\EndingStatus;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\WorldSession;
use App\Models\WorldSessionCampaign;
use App\Models\WorldSessionQuest;
use Illuminate\Support\Collection;

/**
 * What quest conditions read about a session, loaded once per trigger:
 * the player's holdings, what they know and have told, and how other quests
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
     */
    public function __construct(
        public readonly WorldSession $session,
        private readonly ?int $credits,
        private readonly array $itemQuantities,
        private readonly array $knownFactIds,
        private readonly array $acknowledgements,
        private readonly Collection $latestRuns,
        private readonly Collection $campaignEndings,
    ) {}

    public static function for(WorldSession $session): self
    {
        $player = app(ResolveInventory::class)->forPlayer($session);

        return new self(
            $session,
            $player->credits,
            $player->items()->pluck('quantity', 'item_id')->all(),
            $session->knownFacts()->pluck('fact_id')->all(),
            $session->factAcknowledgements()->get(['fact_id', 'world_resident_id'])->map(fn ($row) => "{$row->fact_id}:{$row->world_resident_id}")->all(),
            $session->questRuns()->with('quest')->orderBy('run')->get()->keyBy(fn (WorldSessionQuest $run) => $run->quest->key),
            $session->campaignEndings()->with('campaign')->get()->keyBy(fn (WorldSessionCampaign $ending) => $ending->campaign->key),
        );
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
