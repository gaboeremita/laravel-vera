<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationTranscriptResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Every conversation the user can see, theirs and their assistants' with
 * each other, for reading on the web.
 */
class ConversationBrowserController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): AnonymousResourceCollection
    {
        $conversations = Conversation::visibleTo($request->user())
            ->has('messages')
            ->with(['owner', 'counterpart', 'worldSession.worldUser.world'])
            ->latest('updated_at')
            ->paginate(self::PER_PAGE);

        return ConversationTranscriptResource::collection($conversations);
    }

    public function show(Request $request, int $conversation): ConversationTranscriptResource
    {
        $model = Conversation::visibleTo($request->user())->with('messages')->findOrFail($conversation);

        return new ConversationTranscriptResource($model);
    }
}
