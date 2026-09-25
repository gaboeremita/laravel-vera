<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResidentObservationRequest;
use App\Models\AssistantUser;
use App\Traits\ResolvesWorldUser;
use Illuminate\Http\JsonResponse;

class ResidentObservationController extends Controller
{
    use ResolvesWorldUser;

    /**
     * Records, in her own voice, something she saw the user do while nobody was talking to her.
     */
    public function store(StoreResidentObservationRequest $request, int $world, int $session, int $resident): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);
        $worldSession = $worldUser->sessions()->findOrFail($session);
        $worldResident = $worldUser->world->residents()->findOrFail($resident);

        $assistantUser = AssistantUser::where('assistant_id', $worldResident->assistant_id)->where('user_id', $request->user()->id)->firstOrFail();
        $conversation = $assistantUser->conversations()->firstOrCreate(
            ['world_session_id' => $worldSession->id],
            ['title' => 'New conversation'],
        );

        $message = $conversation->messages()->create(['role' => 'assistant', 'content' => $request->validated('line'), 'expression' => $request->validated('expression')]);

        return response()->json(['messageId' => $message->id], 201);
    }
}
