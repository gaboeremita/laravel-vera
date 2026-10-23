<?php

namespace App\Http\Controllers\Api;

use App\Actions\Creator\EndQuestAsCreator;
use App\Actions\Creator\ResetQuestAsCreator;
use App\Actions\Creator\SetQuestBeat;
use App\Actions\Creator\SetQuestFlag;
use App\Actions\Creator\StartQuestAsCreator;
use App\Enums\QuestStatus;
use App\Http\Controllers\Controller;
use App\Models\Quest;
use App\Models\WorldSession;
use App\Traits\ResolvesCreatorSession;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class CreatorQuestController extends Controller
{
    use ResolvesCreatorSession;

    public function start(Request $request, int $world, int $session, int $quest, StartQuestAsCreator $startQuest): Response|JsonResponse
    {
        return $this->attempt($request, $world, $session, $quest, function (WorldSession $worldSession, Quest $questModel) use ($startQuest) {
            $startQuest->handle($worldSession, $questModel);

            return response()->noContent();
        });
    }

    public function end(Request $request, int $world, int $session, int $quest, EndQuestAsCreator $endQuest): Response|JsonResponse
    {
        $outcome = $request->validate(['outcome' => ['required', 'in:completed,failed']])['outcome'];

        return $this->attempt($request, $world, $session, $quest, function (WorldSession $worldSession, Quest $questModel) use ($endQuest, $outcome) {
            $endQuest->handle($worldSession, $questModel, QuestStatus::from($outcome));

            return response()->noContent();
        });
    }

    public function reset(Request $request, int $world, int $session, int $quest, ResetQuestAsCreator $resetQuest): Response|JsonResponse
    {
        return $this->attempt($request, $world, $session, $quest, function (WorldSession $worldSession, Quest $questModel) use ($resetQuest) {
            $resetQuest->handle($worldSession, $questModel);

            return response()->noContent();
        });
    }

    public function updateBeat(Request $request, int $world, int $session, int $quest, string $beat, SetQuestBeat $setQuestBeat): Response|JsonResponse
    {
        $finished = $request->validate(['finished' => ['required', 'boolean']])['finished'];

        return $this->attempt($request, $world, $session, $quest, fn (WorldSession $worldSession, Quest $questModel) => response()->json([
            'undone' => $setQuestBeat->handle($worldSession, $questModel, $beat, $finished),
        ]));
    }

    public function updateFlag(Request $request, int $world, int $session, int $quest, string $flag, SetQuestFlag $setQuestFlag): Response|JsonResponse
    {
        $on = $request->validate(['on' => ['required', 'boolean']])['on'];

        return $this->attempt($request, $world, $session, $quest, function (WorldSession $worldSession, Quest $questModel) use ($setQuestFlag, $flag, $on) {
            $setQuestFlag->handle($worldSession, $questModel, $flag, $on);

            return response()->noContent();
        });
    }

    /**
     * @param  Closure(WorldSession, Quest): (Response|JsonResponse)  $change
     */
    private function attempt(Request $request, int $world, int $session, int $quest, Closure $change): Response|JsonResponse
    {
        $worldSession = $this->resolveCreatorSession($request, $world, $session);
        $questModel = $worldSession->worldUser->world->quests()->findOrFail($quest);

        try {
            return $change($worldSession, $questModel);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
