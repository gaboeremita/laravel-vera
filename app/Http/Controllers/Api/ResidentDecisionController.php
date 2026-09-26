<?php

namespace App\Http\Controllers\Api;

use App\Actions\AppendWorldConversationContext;
use App\Actions\ApplyResidentZoneAccess;
use App\Actions\BuildResidentWorldPrompt;
use App\Actions\RecallResidentMemory;
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
use App\Models\Message;
use App\Models\ResidentActivity;
use App\Models\World;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Services\AgentLoop\AgentLoopRunner;
use App\Services\AgentLoop\Tools\World\WorldToolbox;
use App\Services\LlmProviders\LlmManager;
use App\Services\LlmResponseTagParser;
use App\Traits\ResolvesWorldUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class ResidentDecisionController extends Controller
{
    use ResolvesWorldUser;

    private const DECISION_FLOOR_SECONDS = 8;

    /** How long a resident waits after a conversation with an NPC before she starts another one with an NPC. */
    private const NPC_CONVERSATION_GAP_SECONDS = 300;

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
        $worldResident = $worldUser->world->residents()->with('assistant')->findOrFail($resident);
        $worldModel = app(ApplyResidentZoneAccess::class)->handle($worldUser->world, $worldResident);
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
        $residentActivity = $resolveUserActivity->handle($worldModel, $validated['residentState'] ?? null, 'residentState');

        $busyWith = isset($validated['userBusyWith']) && $validated['userBusyWith'] !== $worldResident->id
            ? $worldModel->residents()->with('assistant')->find($validated['userBusyWith'])?->assistant->name
            : null;
        $busyWithOthers = collect($validated['busyResidents'] ?? [])->mapWithKeys(fn (array $busy) => [(int) $busy['id'] => $busy['talkingWith'] ?? null])->all();
        $director = new PromptDirector(app(AppendWorldConversationContext::class)->handle($assistant, $worldModel, $positions, $worldSession, $userActivity, $validated['stackedSpots'] ?? [], $busyWith, $residentActivity, $busyWithOthers));
        $director->append('available activities', $buildResidentWorldPrompt->availableActivities($worldModel, $assistant, $location, $occupiedSpots, $posture));
        $companions = $this->companions($worldModel, $worldResident, $worldSession, $positions, array_keys($busyWithOthers));
        $userInSight = $residentPoint !== null && isset($positions['user']) && $resolveWorldState->sharesRoom($worldModel->layout ?? [], $residentPoint, $positions['user']);
        $recentConversation = $buildResidentWorldPrompt->recentConversation($conversation);
        if ($recentConversation !== null) {
            $director->append('recent conversation', $recentConversation);
        }
        $director->append('next step', $buildResidentWorldPrompt->idleInstruction());
        $director->except(['opening_message', 'voice mode', 'image handling', 'OOC mode', 'emotion tags', 'pose tags']);
        $director->withLongTermMemory($conversation);

        $toolbox = new WorldToolbox($worldModel, $location['zoneChain'], $occupiedSpots, $assistant->posturesByPoseName(), $companions, userAvailable: $busyWith === null, userInSight: $userInSight, residentPoint: $residentPoint, recall: fn () => app(RecallResidentMemory::class)->handle($assistant, $request->user()));

        try {
            $llm = $aiModel ? $llmManager->fromModel($aiModel) : $llmManager->fromConfig();
            $result = (new AgentLoopRunner($llm, $toolbox->idleTools()))->run(
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

        return $this->recordDecision($result, $toolbox, $assistant, $conversation, $worldSession, $worldResident, $location);
    }

    /**
     * The residents she can start talking to, resident id by name: everyone
     * in the same room as her who is not talking with someone, on the way to
     * talk to someone, or being walked over to.
     *
     * @param  ?array{user?: array{x: float, y: float, z: float}, residents?: array<int|string, array{x: float, y: float, z: float}>}  $positions
     * @param  array<int, int>  $busyResidentIds  residents the world reports busy
     * @return array<string, int>
     */
    private function companions(World $world, WorldResident $resident, WorldSession $session, ?array $positions, array $busyResidentIds): array
    {
        $own = $positions['residents'][$resident->id] ?? null;
        if ($own === null) {
            return [];
        }
        $resolveWorldState = new ResolveWorldState;

        $busyAssistantIds = Conversation::liveInSession($session->id)
            ->get(['owner_id', 'counterpart_id'])
            ->flatMap(fn (Conversation $conversation) => [$conversation->owner_id, $conversation->counterpart_id])
            ->all();

        $assistantMorph = (new Assistant)->getMorphClass();
        $recentPartnerIds = Conversation::involving($resident->assistant)
            ->where('world_session_id', $session->id)
            ->where('owner_type', $assistantMorph)
            ->where('counterpart_type', $assistantMorph)
            ->whereRaw('coalesce(resumed_at, created_at) >= ?', [now()->subSeconds(self::NPC_CONVERSATION_GAP_SECONDS)])
            ->get(['owner_id', 'counterpart_id'])
            ->flatMap(fn (Conversation $conversation) => [$conversation->owner_id, $conversation->counterpart_id]);
        $talkedToNpcLately = Assistant::whereIn('id', $recentPartnerIds)->whereKeyNot($resident->assistant_id)->where('kind', AssistantKind::WorldNpc)->exists();

        return $world->residents()->with('assistant')->whereKeyNot($resident->id)->get()
            ->reject(fn (WorldResident $other) => in_array($other->assistant_id, $busyAssistantIds, true) || in_array($other->id, $busyResidentIds, true))
            ->reject(fn (WorldResident $other) => $talkedToNpcLately && $other->assistant->kind === AssistantKind::WorldNpc)
            ->filter(fn (WorldResident $other) => isset($positions['residents'][$other->id]) && $resolveWorldState->sharesRoom($world->layout ?? [], $own, $positions['residents'][$other->id]))
            ->mapWithKeys(fn (WorldResident $other) => [$other->assistant->name => $other->id])
            ->all();
    }

    /**
     * @param  array{floor: ?array, zone: ?array, zoneChain: array<int, array>}  $location
     */
    private function recordDecision(AgentRunResult $result, WorldToolbox $toolbox, Assistant $assistant, Conversation $conversation, WorldSession $session, WorldResident $resident, array $location): JsonResponse
    {
        $sessionId = $session->id;
        $residentId = $resident->id;
        $parsed = app(LlmResponseTagParser::class)->parse($result->content, $assistant);
        $line = trim($parsed['content']);
        $action = $toolbox->chosenAction();
        $pose = $action === null ? $parsed['pose'] : null;
        if (($action['verb'] ?? null) === 'talk_to') {
            $tagParser = app(LlmResponseTagParser::class);
            $opening = $tagParser->parse($action['line'], $assistant);
            $action['line'] = $tagParser->stripStrayTags($opening['content']);
            $action['pose'] = $opening['pose'];
            $action['expression'] = Message::expressionFrom($opening, ['verb' => 'talk_to', 'target' => $action['target']], $tagParser->strayTags($opening['content']));
        }
        $thought = '/^\s*([*_]*)\((.+?)\)\1/su';
        $reason = preg_match($thought, $line, $match) === 1 ? trim($match[2]) : null;
        $narration = trim(preg_replace($thought, '', $line, 1));

        $activity = ResidentActivity::create([
            'world_session_id' => $sessionId,
            'world_resident_id' => $residentId,
            'source' => 'idle',
            'verb' => $action['verb'] ?? ($pose !== null ? 'pose' : 'stay'),
            'target' => $action['target'] ?? $pose,
            'activity' => $action['activity'] ?? null,
            'reason' => $reason !== null ? mb_substr($reason, 0, 500) : null,
            'narration' => $narration !== '' ? $narration : null,
            'zone_id' => $location['zone']['id'] ?? null,
        ]);

        $message = $line !== '' ? $conversation->messages()->create(['role' => 'assistant', 'content' => $line, 'expression' => Message::expressionFrom($parsed, $action === null ? null : Arr::except($action, ['expression']))]) : null;

        return response()->json([
            'line' => $line,
            'action' => $action,
            'pose' => $pose,
            'activityId' => $activity->id,
            'messageId' => $message?->id,
        ], 201);
    }
}
