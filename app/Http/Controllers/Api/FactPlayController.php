<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KnownFact;
use App\Models\RevealAttempt;
use App\Traits\ResolvesWorldSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FactPlayController extends Controller
{
    use ResolvesWorldSession;

    /**
     * The facts the player knows in the session, newest first.
     */
    public function index(Request $request, int $world, int $session): JsonResponse
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);

        return response()->json($worldSession->knownFacts()->with('fact')->latest('id')->get()
            ->map(fn (KnownFact $known) => $known->toPayload()));
    }

    /**
     * Every attempt to make a fact known in the session, oldest first.
     */
    public function revealAttempts(Request $request, int $world, int $session): JsonResponse
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);

        return response()->json($worldSession->revealAttempts()->oldest('id')->get()
            ->map(fn (RevealAttempt $attempt) => [
                'id' => $attempt->id,
                'factTopic' => $attempt->fact_topic,
                'holderName' => $attempt->holder_name,
                'source' => $attempt->source->value,
                'reason' => $attempt->reason,
                'reviewed' => $attempt->reviewed,
                'approved' => $attempt->approved,
                'verdict' => $attempt->verdict,
                'createdAt' => $attempt->created_at->toIso8601String(),
            ]));
    }
}
