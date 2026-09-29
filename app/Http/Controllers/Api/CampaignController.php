<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCampaignRequest;
use App\Models\Campaign;
use App\Models\World;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CampaignController extends Controller
{
    public function index(World $world): JsonResponse
    {
        Gate::authorize('view', $world);

        return response()->json($world->campaigns()->with('quests:id,campaign_id')->orderBy('title')->get()->map(fn (Campaign $campaign) => $this->view($campaign)));
    }

    public function store(SaveCampaignRequest $request, World $world): JsonResponse
    {
        Gate::authorize('update', $world);

        $campaign = DB::transaction(function () use ($request, $world): Campaign {
            $campaign = $world->campaigns()->create($request->attributesForCampaign());
            $world->quests()->whereKey($request->questIds())->update(['campaign_id' => $campaign->id]);

            return $campaign;
        });

        return response()->json($this->view($campaign->load('quests:id,campaign_id')), 201);
    }

    public function update(SaveCampaignRequest $request, World $world, Campaign $campaign): JsonResponse
    {
        Gate::authorize('update', $world);

        DB::transaction(function () use ($request, $world, $campaign): void {
            $campaign->update($request->attributesForCampaign());
            $campaign->quests()->whereKeyNot($request->questIds())->update(['campaign_id' => null]);
            $world->quests()->whereKey($request->questIds())->update(['campaign_id' => $campaign->id]);
        });

        return response()->json($this->view($campaign->load('quests:id,campaign_id')));
    }

    public function destroy(World $world, Campaign $campaign): JsonResponse
    {
        Gate::authorize('update', $world);

        $campaign->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array{id: int, key: string, title: string, definition: array<string, mixed>, questIds: array<int, int>}
     */
    private function view(Campaign $campaign): array
    {
        return ['id' => $campaign->id, 'key' => $campaign->key, 'title' => $campaign->title, 'definition' => $campaign->definition, 'questIds' => $campaign->quests->pluck('id')->all()];
    }
}
