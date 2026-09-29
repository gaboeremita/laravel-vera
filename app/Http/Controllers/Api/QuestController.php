<?php

namespace App\Http\Controllers\Api;

use App\Actions\Quests\ReconcileQuestRuns;
use App\Actions\Quests\ValidateQuestDefinition;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveQuestRequest;
use App\Http\Resources\QuestResource;
use App\Models\Quest;
use App\Models\World;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class QuestController extends Controller
{
    public function __construct(private readonly ValidateQuestDefinition $validateQuestDefinition) {}

    /**
     * Every quest of the world, each re-checked against the world as it is
     * now, since a new region layout can remove what a quest names.
     */
    public function index(Request $request, World $world): JsonResponse
    {
        Gate::authorize('view', $world);

        $quests = $this->withSessionCount($world->quests())->orderBy('title')->get()
            ->map(fn (Quest $quest) => (new QuestResource($quest, $this->problems($world, $quest)))->resolve());

        return response()->json($quests);
    }

    public function store(SaveQuestRequest $request, World $world): JsonResponse
    {
        Gate::authorize('update', $world);

        $quest = $world->quests()->create($request->attributesForQuest());

        return response()->json(['quest' => (new QuestResource($quest))->resolve(), 'warnings' => $request->warnings()], 201);
    }

    public function update(SaveQuestRequest $request, World $world, Quest $quest, ReconcileQuestRuns $reconcileQuestRuns): JsonResponse
    {
        Gate::authorize('update', $world);

        DB::transaction(function () use ($request, $quest, $reconcileQuestRuns): void {
            $quest->update($request->attributesForQuest());
            $reconcileQuestRuns->handle($quest);
        });

        $quest = $this->withSessionCount($world->quests())->findOrFail($quest->id);

        return response()->json(['quest' => (new QuestResource($quest))->resolve(), 'warnings' => $request->warnings()]);
    }

    public function destroy(World $world, Quest $quest): JsonResponse
    {
        Gate::authorize('update', $world);

        $quest->delete();

        return response()->json(status: 204);
    }

    /**
     * @param  Builder<Quest>|HasMany<Quest, World>  $query
     * @return Builder<Quest>|HasMany<Quest, World>
     */
    private function withSessionCount($query)
    {
        return $query->withCount(['runs as session_count' => fn (Builder $runs) => $runs->select(DB::raw('count(distinct world_session_id)'))]);
    }

    /**
     * @return array<int, string>
     */
    private function problems(World $world, Quest $quest): array
    {
        return collect($this->validateQuestDefinition->handle($world, $quest->key, $quest->definition, $quest)['errors'])
            ->flatMap(fn (array $messages, string $path) => array_map(fn (string $message) => $path === '' ? $message : "{$path}: {$message}", $messages))
            ->values()
            ->all();
    }
}
