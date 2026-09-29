<?php

namespace App\Http\Controllers\Api;

use App\Actions\Quests\AnnounceZonesEntered;
use App\Actions\Quests\SyncSessionQuests;
use App\Actions\Quests\WithdrawQuestOffers;
use App\Events\Quests\PlayerEnteredRegion;
use App\Actions\StockSession;
use App\Actions\TravelThroughPassage;
use App\Enums\HandoverRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\TravelRequest;
use App\Models\World;
use App\Models\WorldSession;
use App\Models\WorldSessionResident;
use App\Traits\ResolvesWorldUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorldSessionController extends Controller
{
    use ResolvesWorldUser;

    public function index(Request $request, int $world): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);

        $sessions = $worldUser->sessions()
            ->with('residentStates')
            ->withCount('conversations')
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'region_id', 'position', 'arrival_facing', 'updated_at'])
            ->map(fn (WorldSession $session) => [
                'id' => $session->id,
                'title' => $session->title,
                'regionId' => $session->region_id,
                'position' => $session->position,
                'arrivalFacing' => $session->arrival_facing,
                'hasConversations' => $session->conversations_count > 0,
                'updated_at' => $session->updated_at,
                'residentStates' => $session->residentStates->mapWithKeys(fn (WorldSessionResident $state) => [$state->world_resident_id => [
                    'regionId' => $state->region_id,
                    'position' => $state->position,
                    'rotation' => $state->rotation,
                    'spotId' => $state->spot_id,
                    'activityId' => $state->activity_id,
                    'posture' => $state->posture->value,
                    'exitPosition' => $state->exit_position,
                ]]),
            ]);

        return response()->json($sessions);
    }

    public function store(Request $request, int $world, StockSession $stockSession, SyncSessionQuests $syncSessionQuests, AnnounceZonesEntered $announceZonesEntered): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);
        $spawn = $this->requireSpawn($worldUser->world);

        $session = DB::transaction(function () use ($worldUser, $spawn, $stockSession): WorldSession {
            $session = $worldUser->sessions()->create(['title' => 'New session', 'region_id' => $worldUser->world->spawn_region_id, 'position' => $spawn['arrival'], 'arrival_facing' => $spawn['facing']]);
            $stockSession->handle($session);

            return $session;
        });

        $syncSessionQuests->handle($session);
        PlayerEnteredRegion::dispatch($session->id, $session->region_id);
        $announceZonesEntered->handle($session->id, $worldUser->world->spawnRegion, null, $spawn['arrival']);

        return response()->json($session, 201);
    }

    /**
     * Puts a session whose region no longer exists in front of the spawn point.
     */
    public function resume(Request $request, int $world, int $session, SyncSessionQuests $syncSessionQuests, WithdrawQuestOffers $withdrawQuestOffers): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);
        $worldSession = $worldUser->sessions()->findOrFail($session);
        $worldSession->handoverRequests()->where('status', HandoverRequestStatus::Pending)->update(['status' => HandoverRequestStatus::Cancelled, 'answered_at' => now()]);
        $withdrawQuestOffers->handle($worldSession->questOffers());

        if ($worldSession->region_id === null) {
            $spawn = $this->requireSpawn($worldUser->world);
            $worldSession->update(['region_id' => $worldUser->world->spawn_region_id, 'position' => $spawn['arrival'], 'arrival_facing' => $spawn['facing']]);
        }

        $syncSessionQuests->handle($worldSession);

        return response()->json(['regionId' => $worldSession->region_id, 'position' => $worldSession->position, 'arrivalFacing' => $worldSession->arrival_facing]);
    }

    public function travel(TravelRequest $request, int $world, int $session, TravelThroughPassage $travelThroughPassage): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);
        $worldSession = $worldUser->sessions()->findOrFail($session);
        $followerIds = $request->validated('followerIds', []);
        $followers = $worldUser->world->residents()->with('assistant')->findMany($followerIds);
        abort_if($followers->count() !== count($followerIds), 404);

        return response()->json($travelThroughPassage->handle($worldSession, $request->integer('regionId'), $request->validated('passageId'), $followers));
    }

    public function update(Request $request, int $world, int $session): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
        ]);

        $worldSession = $worldUser->sessions()->findOrFail($session);
        $worldSession->update(['title' => $validated['title']]);

        return response()->json($worldSession);
    }

    public function updatePosition(Request $request, int $world, int $session, AnnounceZonesEntered $announceZonesEntered): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);

        $validated = $request->validate([
            'position' => ['required', 'array:x,y,z'],
            'position.x' => ['required', 'numeric'],
            'position.y' => ['required', 'numeric'],
            'position.z' => ['required', 'numeric'],
        ]);

        $worldSession = $worldUser->sessions()->findOrFail($session);
        $from = $worldSession->position;
        $worldSession->update(['position' => $validated['position'], 'arrival_facing' => null]);
        $region = $worldSession->region()->first();
        if ($region !== null) {
            $announceZonesEntered->handle($worldSession->id, $region, $from, $validated['position']);
        }

        return response()->json($worldSession);
    }

    public function destroy(Request $request, int $world, int $session): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);

        $worldSession = $worldUser->sessions()->findOrFail($session);
        $worldSession->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array{id: string, name: string, arrival: array{x: float, y: float, z: float}, facing: float}
     *
     * @throws ValidationException
     */
    private function requireSpawn(World $world): array
    {
        return $world->spawnPassage() ?? throw ValidationException::withMessages(['world' => 'Choose a spawn point for this world first.']);
    }
}
