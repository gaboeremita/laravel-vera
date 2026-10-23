<?php

namespace App\Http\Controllers\Api;

use App\Actions\ResolveInventory;
use App\Actions\ResolveResidentRegion;
use App\Actions\ResolveWorldState;
use App\Http\Controllers\Controller;
use App\Models\ActivityTerms;
use App\Models\AssistantUser;
use App\Models\Fact;
use App\Models\Item;
use App\Models\Quest;
use App\Models\Region;
use App\Models\ResidentActivity;
use App\Models\Sentiment;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionObject;
use App\Models\WorldSessionResident;
use App\Traits\ResolvesCreatorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Everything the Creator menu shows about the world and this session.
 */
class CreatorOverviewController extends Controller
{
    use ResolvesCreatorSession;

    private const RECENT_ACTIVITIES = 5;

    public function show(Request $request, int $world, int $session, ResolveResidentRegion $resolveResidentRegion, ResolveWorldState $resolveWorldState, ResolveInventory $resolveInventory): JsonResponse
    {
        $worldSession = $this->resolveCreatorSession($request, $world, $session);
        $worldModel = $worldSession->worldUser->world;
        $regions = $worldModel->regions()->with('activityTerms')->orderBy('id')->get();
        $objectStates = $worldSession->objectStates()->get()->keyBy(fn (WorldSessionObject $state) => "{$state->region_id}:{$state->object_id}");
        $residentStates = $worldSession->residentStates()->get()->keyBy('world_resident_id');

        return response()->json([
            'regions' => $regions->map(fn (Region $region) => $this->regionView($region, $objectStates->all()))->values(),
            'spawn' => ['regionId' => $worldModel->spawn_region_id, 'passageId' => $worldModel->spawn_passage_id],
            'residents' => $worldModel->residents()->with('assistant.cardImage')->get()
                ->sortBy(fn (WorldResident $resident) => $resident->assistant->name)
                ->map(fn (WorldResident $resident) => $this->residentView($worldSession, $resident, $residentStates->get($resident->id), $resolveResidentRegion, $resolveWorldState))
                ->values(),
            'sentimentNames' => $worldModel->sentimentNames(),
            'quests' => $worldModel->quests()->orderBy('title')->get()->map(fn (Quest $quest) => $this->questView($worldSession, $quest))->values(),
            'facts' => Fact::with('holder.assistant')->whereHas('holder', fn ($query) => $query->where('world_id', $worldModel->id))->orderBy('topic')->get()
                ->map(fn (Fact $fact) => [
                    'id' => $fact->id,
                    'label' => $fact->label(),
                    'known' => $worldSession->knownFacts()->where('fact_id', $fact->id)->exists(),
                ])->values(),
            'items' => Item::availableIn($worldSession)->with('cardImage')->orderBy('name')->get()
                ->map(fn (Item $item) => ['id' => $item->id, 'name' => $item->name, 'cardImageUrl' => $item->cardImage?->url])->values(),
            'credits' => $resolveInventory->forPlayer($worldSession)->credits,
        ]);
    }

    /**
     * @param  array<string, WorldSessionObject>  $objectStates  by "regionId:objectId"
     * @return array<string, mixed>
     */
    private function regionView(Region $region, array $objectStates): array
    {
        $layout = $region->layout ?? [];
        $termsByObject = $region->activityTerms->groupBy('object_id');

        return [
            'id' => $region->id,
            'name' => $region->name,
            'zones' => collect($layout['zones'] ?? [])->map(fn (array $zone) => [
                'id' => $zone['id'],
                'name' => $zone['name'],
                'parentId' => $zone['parentId'] ?? null,
                'outline' => $zone['outline'] ?? [],
            ])->values(),
            'passages' => collect($layout['passages'] ?? [])->map(fn (array $passage) => ['id' => $passage['id'], 'name' => $passage['name']])->values(),
            'objects' => collect($layout['objects'] ?? [])->map(function (array $object) use ($region, $termsByObject, $objectStates) {
                $terms = $termsByObject->get($object['id'], collect());

                return [
                    'id' => $object['id'],
                    'name' => $object['name'] ?? $object['id'],
                    'blocks' => $terms->contains(fn (ActivityTerms $activityTerms) => $this->opens($activityTerms)),
                    'passable' => (bool) ($objectStates["{$region->id}:{$object['id']}"]?->state['passable'] ?? false),
                    'activities' => collect($region->objectActivities($object['id']))->map(fn (array $activity) => [
                        'id' => $activity['id'],
                        'name' => $activity['name'] ?? $activity['id'],
                        'responses' => collect($terms->firstWhere('activity_id', $activity['id'])?->responseList() ?? [])
                            ->map(fn (array $response, int $index) => ['index' => $index, 'summary' => $this->summary($response)])
                            ->values(),
                    ])->values(),
                ];
            })->values(),
        ];
    }

    private function opens(ActivityTerms $terms): bool
    {
        return collect($terms->responseList())->contains(fn (array $response) => collect($response['effects'] ?? [])->contains('type', 'makePassable'));
    }

    /**
     * @param  array{condition: ?array, effects: array<int, array<string, mixed>>}  $response
     */
    private function summary(array $response): string
    {
        $effects = collect($response['effects'] ?? [])->map(fn (array $effect) => Str::lower(Str::headline((string) ($effect['type'] ?? ''))))->filter();

        return $effects->isEmpty() ? 'nothing happens' : $effects->implode(', ');
    }

    /**
     * @return array<string, mixed>
     */
    private function residentView(WorldSession $session, WorldResident $resident, ?WorldSessionResident $state, ResolveResidentRegion $resolveResidentRegion, ResolveWorldState $resolveWorldState): array
    {
        $region = $resolveResidentRegion->handle($session, $resident);
        $position = $state?->position ?? $resident->position;
        $zone = $position !== null ? $resolveWorldState->locate($region->layout ?? [], $position)['zone'] : null;

        return [
            'id' => $resident->id,
            'name' => $resident->assistant->name,
            'portraitUrl' => $resident->assistant->cardImage?->url,
            'regionId' => $region->id,
            'position' => $position,
            'zoneName' => $zone['name'] ?? null,
            'frozen' => $session->isFrozen($resident),
            'sentiments' => $this->sentiments($session, $resident),
            'recentActivities' => ResidentActivity::where('world_session_id', $session->id)->where('world_resident_id', $resident->id)
                ->latest('id')->limit(self::RECENT_ACTIVITIES)->get()
                ->map(fn (ResidentActivity $activity) => [
                    'verb' => $activity->verb,
                    'target' => $activity->target,
                    'reason' => $activity->reason,
                    'outcome' => $activity->outcome,
                    'at' => $activity->created_at,
                ])->values(),
        ];
    }

    /**
     * The resident's sentiments in the session, without starting a conversation
     * with them when there is none yet.
     *
     * @return array<string, float>
     */
    private function sentiments(WorldSession $session, WorldResident $resident): array
    {
        $conversation = AssistantUser::where('assistant_id', $resident->assistant_id)->where('user_id', $session->worldUser->user_id)->first()
            ?->conversations()->where('world_session_id', $session->id)->first();
        $sentiment = $conversation !== null ? Sentiment::where('conversation_id', $conversation->id)->first() : null;

        return $sentiment?->scores() ?? collect($session->worldUser->world->sentimentNames())->mapWithKeys(fn (string $name) => [$name => 0.0])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function questView(WorldSession $session, Quest $quest): array
    {
        $run = $session->latestRunOf($quest);

        return [
            'id' => $quest->id,
            'title' => $quest->title,
            'status' => $run?->status->value ?? 'none',
            'beats' => collect($quest->beats())->map(fn (array $beat) => [
                'id' => $beat['id'],
                'text' => $beat['text'] ?? $beat['id'],
                'finished' => $run !== null && $run->hasFinished($beat['id']),
            ])->values(),
            'flags' => array_keys($run?->state['flags'] ?? []),
        ];
    }
}
