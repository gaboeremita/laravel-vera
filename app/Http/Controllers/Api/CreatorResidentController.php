<?php

namespace App\Http\Controllers\Api;

use App\Actions\Creator\TravelAsCreator;
use App\Enums\ConversationStatus;
use App\Enums\Posture;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Sentiment;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionResident;
use App\Traits\ResolvesCreatorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Puts residents where the creator wants them, holds them still, and sets
 * how they feel about the player, for this session only.
 */
class CreatorResidentController extends Controller
{
    use ResolvesCreatorSession;

    public function summon(Request $request, int $world, int $session, int $resident): Response
    {
        [$worldSession, $worldResident] = $this->resolve($request, $world, $session, $resident);
        $validated = $request->validate([
            'position' => ['required', 'array:x,y,z'],
            'position.*' => ['required', 'numeric'],
            'facing' => ['required', 'numeric'],
        ]);

        $this->place($worldSession, $worldResident, $worldSession->region_id, $validated['position'], (float) $validated['facing']);

        return response()->noContent();
    }

    public function send(Request $request, int $world, int $session, int $resident): JsonResponse
    {
        [$worldSession, $worldResident] = $this->resolve($request, $world, $session, $resident);
        $validated = $request->validate([
            'regionId' => ['required', 'integer'],
            'zoneId' => ['required', 'string', 'max:100'],
            'position' => ['nullable', 'array:x,y,z'],
            'position.*' => ['required', 'numeric'],
        ]);
        $region = $worldSession->worldUser->world->regions()->findOrFail($validated['regionId']);
        $zone = collect($region->layout['zones'] ?? [])->firstWhere('id', $validated['zoneId']) ?? abort(404);
        $position = $validated['position'] ?? TravelAsCreator::zoneMiddle($zone);

        $this->place($worldSession, $worldResident, $region->id, $position, 0.0);

        return response()->json(['regionId' => $region->id, 'position' => $position]);
    }

    public function destroyState(Request $request, int $world, int $session, int $resident): Response
    {
        [$worldSession, $worldResident] = $this->resolve($request, $world, $session, $resident);
        $this->pauseConversations($worldSession, $worldResident);
        WorldSessionResident::where('world_session_id', $worldSession->id)->where('world_resident_id', $worldResident->id)->delete();

        return response()->noContent();
    }

    public function updateFrozen(Request $request, int $world, int $session, int $resident): Response
    {
        [$worldSession, $worldResident] = $this->resolve($request, $world, $session, $resident);
        $frozen = $request->validate(['frozen' => ['required', 'boolean']])['frozen'];

        $ids = array_values(array_diff($worldSession->frozen_resident_ids ?? [], [$worldResident->id]));
        $worldSession->update(['frozen_resident_ids' => $frozen ? [...$ids, $worldResident->id] : $ids]);

        return response()->noContent();
    }

    public function updateSentiments(Request $request, int $world, int $session, int $resident): JsonResponse
    {
        [$worldSession, $worldResident] = $this->resolve($request, $world, $session, $resident);
        $validated = $request->validate([
            'values' => ['required', 'array', 'min:1'],
            'values.*' => ['required', 'integer', 'between:-'.Sentiment::LIMIT.','.Sentiment::LIMIT],
        ]);
        $names = $worldSession->worldUser->world->sentimentNames();
        $unknown = array_diff(array_keys($validated['values']), $names);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['values' => 'This world has no sentiment called "'.reset($unknown).'".']);
        }

        $sentiment = Sentiment::inSession($worldSession, $worldResident);
        $scores = $sentiment->scores();
        $sentiment->adjust(collect($validated['values'])->map(fn (int $value, string $name) => $value - $scores[$name])->all());

        return response()->json(['sentiments' => $sentiment->scores()]);
    }

    /**
     * @return array{0: WorldSession, 1: WorldResident}
     */
    private function resolve(Request $request, int $world, int $session, int $resident): array
    {
        $worldSession = $this->resolveCreatorSession($request, $world, $session);

        return [$worldSession, $worldSession->worldUser->world->residents()->with('assistant')->findOrFail($resident)];
    }

    /**
     * @param  array{x: float, y: float, z: float}  $position
     */
    private function place(WorldSession $session, WorldResident $resident, int $regionId, array $position, float $facing): void
    {
        $this->pauseConversations($session, $resident);
        WorldSessionResident::updateOrCreate(
            ['world_session_id' => $session->id, 'world_resident_id' => $resident->id],
            ['region_id' => $regionId, 'position' => $position, 'rotation' => ['y' => $facing], 'spot_id' => null, 'activity_id' => null, 'posture' => Posture::Standing->value, 'exit_position' => null],
        );
    }

    /**
     * Ends, as a pause, any conversation the resident is having with another resident.
     */
    private function pauseConversations(WorldSession $session, WorldResident $resident): void
    {
        $assistant = $resident->assistant;
        Conversation::where('world_session_id', $session->id)
            ->where('status', ConversationStatus::Active)
            ->where('owner_type', $assistant->getMorphClass())
            ->where('counterpart_type', $assistant->getMorphClass())
            ->where(fn ($query) => $query->where('owner_id', $assistant->id)->orWhere('counterpart_id', $assistant->id))
            ->update(['status' => ConversationStatus::Paused]);
    }
}
