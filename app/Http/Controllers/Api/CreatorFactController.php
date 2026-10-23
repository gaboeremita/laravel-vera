<?php

namespace App\Http\Controllers\Api;

use App\Actions\Creator\SetFactKnown;
use App\Http\Controllers\Controller;
use App\Models\Fact;
use App\Traits\ResolvesCreatorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorFactController extends Controller
{
    use ResolvesCreatorSession;

    public function update(Request $request, int $world, int $session, int $fact, SetFactKnown $setFactKnown): JsonResponse
    {
        $worldSession = $this->resolveCreatorSession($request, $world, $session);
        $known = $request->validate(['known' => ['required', 'boolean']])['known'];
        $factModel = Fact::with('holder.assistant')->whereHas('holder', fn ($query) => $query->where('world_id', $worldSession->worldUser->world_id))->findOrFail($fact);

        return response()->json(['learnedFact' => $setFactKnown->handle($worldSession, $factModel, $known)?->toPayload()]);
    }
}
