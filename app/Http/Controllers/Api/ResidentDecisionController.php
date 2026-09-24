<?php

namespace App\Http\Controllers\Api;

use App\Actions\AppendWorldConversationContext;
use App\Actions\BuildResidentWorldPrompt;
use App\Actions\ResolveUserActivity;
use App\Actions\ResolveWorldState;
use App\Directors\PromptDirector;
use App\DTOs\AgentRunResult;
use App\Enums\AssistantKind;
use App\Enums\Posture;
use App\Enums\WorldResidentBehavior;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResidentDecisionRequest;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\ResidentActivity;
use App\Services\AgentLoop\AgentLoopRunner;
use App\Services\AgentLoop\Tools\World\WorldToolbox;
use App\Services\LlmProviders\LlmManager;
use App\Services\LlmResponseTagParser;
use App\Traits\ResolvesWorldUser;
use Illuminate\Http\JsonResponse;

class ResidentDecisionController extends Controller
{
    use ResolvesWorldUser;

    private const DECISION_FLOOR_SECONDS = 8;

    public function store(
        StoreResidentDecisionRequest $request,
        int $world,
        int $session,
        int $resident,
        ResolveWorldState $resolveWorldState,
        BuildResidentWorldPrompt $buildResidentWorldPrompt,
        ResolveUserActivity $resolveUserActivity,
    ): JsonResponse {
        $worldUser = $this->resolveWorldUser($request, $world);
        $worldSession = $worldUser->sessions()->findOrFail($session);
        $worldModel = $worldUser->world;
        $worldResident = $worldModel->residents()->with('assistant')->findOrFail($resident);
        $assistant = $worldResident->assistant;
        $validated = $request->validated();

        if ($worldResident->behavior !== WorldResidentBehavior::Autonomous) {
            return response()->json(['message' => 'Only autonomous residents decide what to do on their own.'], 422);
        }

        $activities = ResidentActivity::where('world_session_id', $worldSession->id)->where('world_resident_id', $worldResident->id);

        if (isset($validated['previous'])) {
            (clone $activities)->whereNull('outcome')->find($validated['previous']['activityId'])?->update([
                'outcome' => $validated['previous']['outcome'],
                'outcome_reason' => $validated['previous']['reason'] ?? null,
                'finished_at' => now(),
            ]);
        }

        $lastDecision = (clone $activities)->where('source', 'idle')->latest('created_at')->first();
        if ($lastDecision !== null && $lastDecision->created_at->gt(now()->subSeconds(self::DECISION_FLOOR_SECONDS))) {
            return response()->json(['message' => 'She decided something moments ago.'], 429);
        }

        $assistantUser = AssistantUser::where('assistant_id', $assistant->id)->where('user_id', $request->user()->id)->firstOrFail();
        $llmManager = new LlmManager;
        $aiModel = $llmManager->resolveModelForAssistantUser($assistantUser);
        if ($assistant->kind !== AssistantKind::WorldNpc && ! $aiModel?->supports_tools) {
            return response()->json(['message' => 'Assistants living in a world need a model that supports tool calling. Choose one in this assistant\'s settings.'], 422);
        }

        $conversation = $assistantUser->conversations()->firstOrCreate(
            ['world_session_id' => $worldSession->id],
            ['title' => 'New conversation'],
        );

        $positions = $validated['positions'] ?? null;
        $residentPoint = $positions['residents'][$worldResident->id] ?? null;
        $location = $residentPoint !== null
            ? $resolveWorldState->locate($worldModel->layout ?? [], $residentPoint)
            : ['floor' => null, 'zone' => null, 'zoneChain' => []];
        $occupiedSpots = $validated['occupiedSpots'] ?? [];
        $posture = Posture::from($validated['residentPosture'] ?? Posture::Standing->value);

        $userActivity = $resolveUserActivity->handle($worldModel, $validated['userState'] ?? null);

        $director = new PromptDirector(app(AppendWorldConversationContext::class)->handle($assistant, $worldModel, $positions, $worldSession, $userActivity));
        $director->append('available activities', $buildResidentWorldPrompt->availableActivities($worldModel, $assistant, $location, $occupiedSpots, $posture));
        $recentConversation = $buildResidentWorldPrompt->recentConversation($conversation);
        if ($recentConversation !== null) {
            $director->append('recent conversation', $recentConversation);
        }
        $director->append('next step', $buildResidentWorldPrompt->idleInstruction());
        $director->except(['opening_message', 'voice mode', 'image handling', 'OOC mode', 'emotion tags', 'pose tags']);
        $director->withLongTermMemory($conversation);

        $toolbox = new WorldToolbox($worldModel, $location['zoneChain'], $occupiedSpots, $assistant->posturesByPoseName());

        try {
            $llm = $aiModel ? $llmManager->fromModel($aiModel) : $llmManager->fromConfig();
            $result = (new AgentLoopRunner($llm, $toolbox->tools()))->run(
                assistant: $assistant,
                messages: [
                    ['role' => 'system', 'content' => $director->build()],
                    ['role' => 'user', 'content' => '[A moment passes in the world.]'],
                ],
                conversation: $conversation,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return $this->recordDecision($result, $toolbox, $assistant, $conversation, $worldSession->id, $worldResident->id, $location);
    }

    /**
     * @param  array{floor: ?array, zone: ?array, zoneChain: array<int, array>}  $location
     */
    private function recordDecision(AgentRunResult $result, WorldToolbox $toolbox, Assistant $assistant, Conversation $conversation, int $sessionId, int $residentId, array $location): JsonResponse
    {
        $parsed = app(LlmResponseTagParser::class)->parse($result->content, $assistant);
        $line = trim($parsed['content']);
        $action = $toolbox->chosenAction();
        $pose = $action === null ? $parsed['pose'] : null;
        $reason = preg_match('/^\s*\((.+?)\)/su', $line, $match) === 1 ? trim($match[1]) : null;

        $activity = ResidentActivity::create([
            'world_session_id' => $sessionId,
            'world_resident_id' => $residentId,
            'source' => 'idle',
            'verb' => $action['verb'] ?? ($pose !== null ? 'pose' : 'stay'),
            'target' => $action['target'] ?? $pose,
            'activity' => $action['activity'] ?? null,
            'reason' => $reason !== null ? mb_substr($reason, 0, 500) : null,
            'zone_id' => $location['zone']['id'] ?? null,
        ]);

        $message = $line !== '' ? $conversation->messages()->create(['role' => 'assistant', 'content' => $line]) : null;

        return response()->json([
            'line' => $line,
            'action' => $action,
            'pose' => $pose,
            'activityId' => $activity->id,
            'messageId' => $message?->id,
        ], 201);
    }
}
