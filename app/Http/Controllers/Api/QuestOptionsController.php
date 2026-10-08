<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Fact;
use App\Models\Item;
use App\Models\Quest;
use App\Models\Region;
use App\Models\World;
use App\Models\WorldResident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Everything the quest editor's pickers choose from, in one call.
 */
class QuestOptionsController extends Controller
{
    public function __invoke(Request $request, World $world): JsonResponse
    {
        Gate::authorize('view', $world);

        return response()->json([
            'regions' => $world->regions()->orderBy('name')->get(['id', 'name', 'layout'])->map(fn (Region $region) => [
                'id' => $region->id,
                'name' => $region->name,
                'zones' => collect($region->layout['zones'] ?? [])->map(fn (array $zone) => ['id' => $zone['id'], 'name' => $zone['name']])->values()->all(),
                'objects' => collect($region->layout['objects'] ?? [])->map(fn (array $object) => [
                    'id' => $object['id'],
                    'name' => $object['name'],
                    'activities' => collect($region->objectActivities($object['id']))->map(fn (array $activity) => ['id' => $activity['id'], 'name' => $activity['name']])->values()->all(),
                ])->values()->all(),
            ]),
            'residents' => $world->residents()->with('assistant')->get()->map(fn (WorldResident $resident) => [
                'id' => $resident->id,
                'name' => $resident->assistant->name,
                'toolsUnsupported' => ! $resident->canCallToolsFor($request->user()),
            ])->sortBy('name')->values(),
            'sentiments' => $world->sentimentNames(),
            'items' => $world->items()->orderBy('name')->get(['id', 'name'])->map(fn (Item $item) => ['id' => $item->id, 'name' => $item->name]),
            'facts' => Fact::with('holder.assistant')->whereHas('holder', fn ($query) => $query->where('world_id', $world->id))->orderBy('topic')->get()
                ->map(fn (Fact $fact) => ['id' => $fact->id, 'topic' => $fact->topic, 'holderName' => $fact->holder->assistant->name]),
            'quests' => $world->quests()->orderBy('title')->get()->map(fn (Quest $quest) => [
                'key' => $quest->key,
                'title' => $quest->title,
                'flags' => collect($quest->beats())->flatMap(fn (array $beat) => collect($beat['grants'] ?? [])->pluck('flag'))->unique()->values()->all(),
                'tiers' => $quest->rubric()['tiers'] ?? [],
            ]),
            'campaigns' => $world->campaigns()->orderBy('title')->get()->map(fn (Campaign $campaign) => [
                'key' => $campaign->key,
                'title' => $campaign->title,
                'tiers' => $campaign->rubric()['tiers'] ?? [],
            ]),
        ]);
    }
}
