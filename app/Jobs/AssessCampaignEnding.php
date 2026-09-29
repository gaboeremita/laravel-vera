<?php

namespace App\Jobs;

use App\Actions\Quests\AssessEnding;
use App\Actions\Quests\SyncSessionQuests;
use App\Enums\EndingStatus;
use App\Events\Quests\QuestEndingReady;
use App\Models\WorldSessionCampaign;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Writes a campaign's ending once none of its quests can still go on.
 */
class AssessCampaignEnding implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 180;

    public function __construct(public int $campaignEndingId) {}

    public function handle(AssessEnding $assessEnding, SyncSessionQuests $syncSessionQuests): void
    {
        $campaignEnding = WorldSessionCampaign::with(['campaign', 'worldSession.worldUser.world'])->find($this->campaignEndingId);
        if ($campaignEnding === null) {
            return;
        }

        try {
            $ending = $assessEnding->forCampaign($campaignEnding);
        } catch (Throwable $exception) {
            report($exception);
            // A failure in one attempt waits for the next; only the last marks the ending failed.
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff);

                return;
            }
            $this->fail($exception);

            return;
        }

        $campaignEnding->update(['ending' => $ending, 'ending_status' => EndingStatus::Written]);
        QuestEndingReady::dispatch($campaignEnding->world_session_id, ['campaignId' => $campaignEnding->campaign_id, 'title' => $campaignEnding->campaign->title, 'endingStatus' => EndingStatus::Written->value, 'ending' => AssessQuestEnding::view($ending)]);
        $syncSessionQuests->handle($campaignEnding->worldSession);
    }

    public function failed(?Throwable $exception): void
    {
        $campaignEnding = WorldSessionCampaign::with('campaign')->find($this->campaignEndingId);
        if ($campaignEnding === null) {
            return;
        }

        $campaignEnding->update(['ending_status' => EndingStatus::Failed]);
        QuestEndingReady::dispatch($campaignEnding->world_session_id, ['campaignId' => $campaignEnding->campaign_id, 'title' => $campaignEnding->campaign->title, 'endingStatus' => EndingStatus::Failed->value, 'ending' => null]);
    }
}
