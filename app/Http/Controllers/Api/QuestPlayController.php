<?php

namespace App\Http\Controllers\Api;

use App\Actions\Quests\BroadcastQuestRuns;
use App\Actions\Quests\EndQuestRun;
use App\Actions\Quests\PlayerRunView;
use App\Enums\EndingStatus;
use App\Enums\QuestStatus;
use App\Http\Controllers\Controller;
use App\Jobs\AssessQuestEnding;
use App\Models\Campaign;
use App\Models\QuestEvent;
use App\Models\WorldSessionCampaign;
use App\Models\WorldSessionQuest;
use App\Traits\ResolvesWorldSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestPlayController extends Controller
{
    use ResolvesWorldSession;

    /**
     * Every run in the session as the player sees it, and the world's
     * campaigns with their endings here.
     */
    public function index(Request $request, int $world, int $session, PlayerRunView $playerRunView): JsonResponse
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        $endings = $worldSession->campaignEndings()->get()->keyBy('campaign_id');

        return response()->json([
            'runs' => $worldSession->questRuns()->with('quest')->where('status', '!=', QuestStatus::Available)->orderBy('id')->get()
                ->map(fn (WorldSessionQuest $run) => $playerRunView->handle($run))
                ->values(),
            'campaigns' => $worldSession->worldUser->world->campaigns()->with('quests:id,campaign_id')->orderBy('title')->get()
                ->map(fn (Campaign $campaign) => $this->campaignView($campaign, $endings->get($campaign->id))),
        ]);
    }

    /**
     * Every quest event of the session, oldest first, for the world's author.
     */
    public function events(Request $request, int $world, int $session): JsonResponse
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);

        return response()->json(QuestEvent::with('run.quest')
            ->whereIn('world_session_quest_id', $worldSession->questRuns()->select('id'))
            ->oldest('id')
            ->get()
            ->map(fn (QuestEvent $event) => [
                'id' => $event->id,
                'questTitle' => $event->run->quest->title,
                'run' => $event->run->run,
                'beat' => $event->beat,
                'type' => $event->type->value,
                'payload' => $event->payload,
                'byCreator' => $event->by_creator,
                'createdAt' => $event->created_at?->toIso8601String(),
            ]));
    }

    /**
     * The player gives up an active quest; its ending is written like any other.
     */
    public function abandon(Request $request, int $world, int $session, int $run, EndQuestRun $endQuestRun, BroadcastQuestRuns $broadcastQuestRuns, PlayerRunView $playerRunView): JsonResponse
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        $questRun = $worldSession->questRuns()->with('quest')->findOrFail($run);
        if ($questRun->status !== QuestStatus::Active) {
            return response()->json(['message' => 'Only a quest in progress can be abandoned.'], 422);
        }

        $endQuestRun->handle($questRun, QuestStatus::Abandoned, ['by' => 'player']);
        $broadcastQuestRuns->handle($worldSession->id, [$questRun], [BroadcastQuestRuns::notice('questEnded', $questRun, QuestStatus::Abandoned->value)]);

        return response()->json($playerRunView->handle($questRun->fresh('quest')));
    }

    /**
     * Writes a run's ending again after it couldn't be written.
     */
    public function assess(Request $request, int $world, int $session, int $run): JsonResponse
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        $questRun = $worldSession->questRuns()->findOrFail($run);
        if ($questRun->ending_status !== EndingStatus::Failed) {
            return response()->json(['message' => 'Only an ending that couldn\'t be written can be written again.'], 422);
        }

        $questRun->update(['ending_status' => EndingStatus::Pending]);
        AssessQuestEnding::dispatch($questRun->id);

        return response()->json(status: 202);
    }

    /**
     * @return array{id: int, title: string, description: string, questIds: array<int, int>, ending: ?array<string, mixed>, endingStatus: ?string}
     */
    private function campaignView(Campaign $campaign, ?WorldSessionCampaign $ending): array
    {
        return [
            'id' => $campaign->id,
            'title' => $campaign->title,
            'description' => $campaign->description(),
            'questIds' => $campaign->quests->pluck('id')->all(),
            'ending' => $ending?->ending === null ? null : [
                'tier' => $ending->ending['tier'] ?? null,
                'title' => $ending->ending['title'] ?? '',
                'epilogue' => $ending->ending['epilogue'] ?? '',
                'scores' => $ending->ending['scores'] ?? [],
            ],
            'endingStatus' => $ending?->ending_status->value,
        ];
    }
}
