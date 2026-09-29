<?php

namespace App\Http\Controllers\Api;

use App\Actions\Quests\BroadcastQuestRuns;
use App\Actions\Quests\PlayerRunView;
use App\Actions\Quests\RecordQuestEvent;
use App\Actions\Quests\StartQuestRun;
use App\Actions\Quests\WithdrawQuestOffers;
use App\Enums\QuestEventType;
use App\Enums\QuestOfferStatus;
use App\Enums\QuestStatus;
use App\Events\Quests\QuestStateChanged;
use App\Http\Controllers\Controller;
use App\Models\QuestOffer;
use App\Traits\ResolvesWorldSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestOfferController extends Controller
{
    use ResolvesWorldSession;

    public function __construct(
        private readonly StartQuestRun $startQuestRun,
        private readonly RecordQuestEvent $recordQuestEvent,
        private readonly BroadcastQuestRuns $broadcastQuestRuns,
        private readonly PlayerRunView $playerRunView,
    ) {}

    /**
     * The player accepts or declines a giver's offer; the line tells the
     * giver as the player's next message.
     */
    public function answer(Request $request, int $world, int $session, int $offer): JsonResponse
    {
        $accept = $request->validate(['accept' => ['required', 'boolean']])['accept'];
        $worldSession = $this->resolveWorldSession($request, $world, $session);

        $answered = DB::transaction(function () use ($worldSession, $offer, $accept): ?QuestOffer {
            $pending = $worldSession->questOffers()->with('run.quest')->lockForUpdate()->findOrFail($offer);
            if ($pending->status !== QuestOfferStatus::Pending || $pending->run->status !== QuestStatus::Available) {
                return null;
            }

            $pending->update(['status' => $accept ? QuestOfferStatus::Accepted : QuestOfferStatus::Declined, 'answered_at' => now()]);
            if ($accept) {
                $this->startQuestRun->handle($pending->run, 'offer');
            } else {
                $this->recordQuestEvent->handle($pending->run, QuestEventType::OfferDeclined);
                QuestStateChanged::dispatch($worldSession->id, "The user declined \"{$pending->run->quest->title}\"");
            }

            return $pending;
        });

        if ($answered === null) {
            return response()->json(['message' => 'This offer is no longer waiting for an answer.'], 409);
        }

        $run = $answered->run->fresh('quest');
        $title = $run->quest->title;
        if ($accept) {
            $this->broadcastQuestRuns->handle($worldSession->id, [$run], [BroadcastQuestRuns::notice('questStarted', $run, $run->quest->description())]);
        }

        return response()->json([
            'status' => $answered->status->value,
            'line' => $accept ? "[You accept \"{$title}\"]" : "[You decline \"{$title}\" for now]",
            'run' => $this->playerRunView->handle($run),
        ]);
    }

    /**
     * Offers still waiting when the player leaves the conversation are withdrawn.
     */
    public function withdraw(Request $request, int $world, int $session, int $conversation, WithdrawQuestOffers $withdrawQuestOffers): JsonResponse
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        $withdrawQuestOffers->handle($worldSession->questOffers()->where('conversation_id', $conversation));

        return response()->json(status: 204);
    }
}
