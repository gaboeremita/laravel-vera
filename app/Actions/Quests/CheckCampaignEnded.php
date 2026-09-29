<?php

namespace App\Actions\Quests;

use App\Enums\EndingStatus;
use App\Enums\QuestStatus;
use App\Jobs\AssessCampaignEnding;
use App\Models\Quest;
use App\Models\WorldSessionCampaign;
use App\Models\WorldSessionQuest;
use Illuminate\Support\Collection;

/**
 * Once a quest's ending is settled, checks whether its campaign is over in
 * the session, and has the campaign's ending written once it is.
 */
class CheckCampaignEnded
{
    public function handle(WorldSessionQuest $run): void
    {
        $campaign = $run->quest->campaign;
        if ($campaign === null) {
            return;
        }

        $session = $run->worldSession;
        $runs = $session->questRuns()->whereIn('quest_id', $campaign->quests()->select('id'))->orderBy('id')->get()->groupBy('quest_id');
        $quests = $campaign->quests()->get();
        if ($quests->isEmpty() || ! $quests->every(fn (Quest $quest) => $this->settled($quest, $runs, $quests))) {
            return;
        }

        $campaignEnding = WorldSessionCampaign::firstOrCreate(
            ['world_session_id' => $session->id, 'campaign_id' => $campaign->id],
            ['ending_status' => EndingStatus::Pending],
        );
        if ($campaignEnding->wasRecentlyCreated) {
            AssessCampaignEnding::dispatch($campaignEnding->id);
        }
    }

    /**
     * A quest is settled when it has ended at least once, with its ending
     * written or failed and no run going on, or when it never started and the
     * quests it requires have all ended without letting it.
     *
     * @param  Collection<int, Collection<int, WorldSessionQuest>>  $runs  by quest id
     * @param  Collection<int, Quest>  $quests
     */
    private function settled(Quest $quest, Collection $runs, Collection $quests): bool
    {
        $questRuns = $runs->get($quest->id, collect());
        if ($questRuns->isEmpty()) {
            return collect($quest->requirements())->every(function (array $requirement) use ($runs, $quests): bool {
                $required = $quests->firstWhere('key', $requirement['quest'] ?? null);

                return $required !== null && $runs->get($required->id, collect())->contains(fn (WorldSessionQuest $run) => $run->status->hasEnded());
            }) && $quest->requirements() !== [];
        }

        if ($questRuns->contains(fn (WorldSessionQuest $run) => $run->status === QuestStatus::Active || ($run->status->hasEnded() && $run->ending_status === EndingStatus::Pending))) {
            return false;
        }

        return $questRuns->contains(fn (WorldSessionQuest $run) => $run->status->hasEnded());
    }
}
