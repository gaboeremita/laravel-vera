<?php

namespace App\Http\Controllers\Api;

use App\Actions\GenerateResidentConversationTurn;
use App\Actions\StartResidentConversation;
use App\Enums\ConversationStatus;
use App\Enums\Posture;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResidentConversationRequest;
use App\Http\Requests\StoreResidentConversationTurnRequest;
use App\Http\Resources\ConversationTranscriptResource;
use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\WorldSession;
use App\Traits\ResolvesWorldUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Conversations between residents while the user is in the world: their
 * turns, the user stopping them, and the user listening in.
 */
class ResidentConversationController extends Controller
{
    use ResolvesWorldUser;

    /**
     * She has reached the resident she wanted to talk to: their conversation
     * starts, or picks up where it stopped, with her opening line.
     */
    public function store(StoreResidentConversationRequest $request, int $world, int $session, int $resident, StartResidentConversation $startConversation): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);
        $worldSession = $worldUser->sessions()->findOrFail($session);
        $starter = $worldUser->world->residents()->findOrFail($resident);
        $other = $worldUser->world->residents()->with('assistant')->whereKeyNot($starter->id)->findOrFail($request->validated('with'));
        $starter->loadMissing('assistant');

        $otherIsTalking = Conversation::liveInSession($worldSession->id)
            ->involving($other->assistant)
            ->get()
            ->contains(fn (Conversation $conversation) => ! $conversation->involves($starter->assistant));
        if ($otherIsTalking) {
            return response()->json(['message' => "{$other->assistant->name} is busy talking with someone else."], 409);
        }

        $conversation = $startConversation->handle($worldSession, $starter, $other, $request->validated('line'), $request->validated('expression'));

        return response()->json(['conversationId' => $conversation->id], 201);
    }

    public function show(Request $request, int $world, int $session, int $conversation): ConversationTranscriptResource
    {
        [, $model] = $this->resolve($request, $world, $session, $conversation);
        $model->load('messages');

        return new ConversationTranscriptResource($model);
    }

    public function turn(StoreResidentConversationTurnRequest $request, int $world, int $session, int $conversation, GenerateResidentConversationTurn $generateTurn): JsonResponse
    {
        [$worldSession, $model] = $this->resolve($request, $world, $session, $conversation);

        $residents = $worldSession->worldUser->world->residents()->get(['id', 'assistant_id']);
        $posturesByAssistantId = collect($request->validated('postures') ?? [])
            ->mapWithKeys(fn (string $posture, int|string $residentId) => [$residents->firstWhere('id', (int) $residentId)?->assistant_id => Posture::from($posture)])
            ->filter(fn (Posture $posture, int|string $assistantId) => $assistantId !== '')
            ->all();

        try {
            $result = $generateTurn->handle($model, $worldSession, $request->validated('positions'), $posturesByAssistantId);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $message = $result['message'] ?? null;
        $residentIds = $residents->pluck('id', 'assistant_id');

        return response()->json([
            'status' => $result['status'],
            'retryIn' => $result['retryIn'] ?? null,
            'message' => $message === null ? null : [
                'id' => $message->id,
                'residentId' => $residentIds[$message->speaker_id] ?? null,
                'content' => $message->content,
                'pose' => $result['pose'] ?? null,
            ],
        ], $result['status'] === 'wait' ? 429 : 200);
    }

    public function pause(Request $request, int $world, int $session, int $conversation): JsonResponse
    {
        [, $model] = $this->resolve($request, $world, $session, $conversation);
        $model->update(['status' => ConversationStatus::Paused]);

        return response()->json(['status' => $model->status->value]);
    }

    public function observe(Request $request, int $world, int $session, int $conversation): JsonResponse
    {
        [, $model] = $this->resolve($request, $world, $session, $conversation);
        $model->observers()->firstOrCreate([
            'observer_type' => $request->user()->getMorphClass(),
            'observer_id' => $request->user()->id,
        ]);

        return response()->json(['observing' => true], 201);
    }

    /**
     * @return array{0: WorldSession, 1: Conversation}
     */
    private function resolve(Request $request, int $world, int $session, int $conversation): array
    {
        $worldSession = $this->resolveWorldUser($request, $world)->sessions()->findOrFail($session);
        $model = Conversation::where('world_session_id', $worldSession->id)
            ->where('owner_type', (new Assistant)->getMorphClass())
            ->where('counterpart_type', (new Assistant)->getMorphClass())
            ->findOrFail($conversation);

        return [$worldSession, $model];
    }
}
