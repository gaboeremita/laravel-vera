<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveFactRequest;
use App\Http\Resources\FactResource;
use App\Models\Fact;
use App\Models\World;
use App\Models\WorldResident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FactController extends Controller
{
    /**
     * Every fact of the world, for pickers that link items and activities to one.
     */
    public function world(World $world): JsonResponse
    {
        Gate::authorize('view', $world);

        return response()->json(Fact::with('holder.assistant')->whereHas('holder', fn ($query) => $query->where('world_id', $world->id))->orderBy('topic')->get()
            ->map(fn (Fact $fact) => ['id' => $fact->id, 'topic' => $fact->topic, 'holderName' => $fact->holder->assistant->name]));
    }

    public function index(Request $request, World $world, WorldResident $resident): JsonResponse
    {
        Gate::authorize('view', $world);

        $facts = $resident->facts()->with('relays')->withCount('knownFacts')->orderBy('topic')->get();

        return response()->json([
            'facts' => FactResource::collection($facts)->resolve(),
            'toolsUnsupported' => $facts->isNotEmpty() && ! $resident->canCallToolsFor($request->user()) ? SaveFactRequest::toolsUnsupportedReason($resident) : null,
        ]);
    }

    public function store(SaveFactRequest $request, World $world, WorldResident $resident): JsonResponse
    {
        Gate::authorize('update', $world);

        $fact = DB::transaction(function () use ($request, $resident): Fact {
            $fact = $resident->facts()->create($request->attributesForFact());
            $fact->relays()->sync($request->relayResidentIds());

            return $fact;
        });

        return response()->json((new FactResource($fact->load('relays')->loadCount('knownFacts')))->resolve(), 201);
    }

    public function update(SaveFactRequest $request, World $world, WorldResident $resident, Fact $fact): JsonResponse
    {
        Gate::authorize('update', $world);

        DB::transaction(function () use ($request, $fact): void {
            $fact->update($request->attributesForFact());
            $fact->relays()->sync($request->relayResidentIds());
        });

        return response()->json((new FactResource($fact->load('relays')->loadCount('knownFacts')))->resolve());
    }

    public function destroy(World $world, WorldResident $resident, Fact $fact): JsonResponse
    {
        Gate::authorize('update', $world);

        $fact->delete();

        return response()->json(status: 204);
    }
}
