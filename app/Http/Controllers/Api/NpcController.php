<?php

namespace App\Http\Controllers\Api;

use App\Actions\DeleteAssistantAssets;
use App\Enums\AssistantKind;
use App\Http\Controllers\Controller;
use App\Models\Assistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NpcController extends Controller
{
    public function __construct(private readonly AssistantController $assistantController) {}

    public function index(Request $request): JsonResponse
    {
        $npcs = $request->user()->assistants()->where('kind', AssistantKind::WorldNpc)->with(['vrm', 'cardImage'])->get()
            ->map(fn (Assistant $npc) => [
                'id' => $npc->id,
                'name' => $npc->name,
                'slug' => $npc->slug,
                'description' => $npc->description,
                'kind' => $npc->kind->value,
                'portrait_type' => $npc->portrait_type->value,
                'vrm_url' => $npc->vrm?->url,
                'image_url' => $npc->cardImage?->url,
            ]);

        return response()->json($npcs);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'slug' => Str::slug($request->string('name')->toString()).'-'.Str::lower(Str::random(6)),
            'mode' => 'assistant',
            'portrait_type' => 'avatar3d',
        ]);

        return $this->assistantController->store($request, AssistantKind::WorldNpc);
    }

    public function show(Request $request, Assistant $npc): JsonResponse
    {
        return response()->json($this->npcFor($request, $npc));
    }

    public function update(Request $request, Assistant $npc): JsonResponse
    {
        $npc = $this->npcFor($request, $npc);
        $npc->update($request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'prompt' => ['nullable', 'array'], 'archive_id' => ['nullable', 'integer', 'exists:archives,id']]));

        return response()->json($npc->fresh());
    }

    public function destroy(Request $request, Assistant $npc, DeleteAssistantAssets $deleteAssistantAssets): JsonResponse
    {
        $deleteAssistantAssets->handle($this->npcFor($request, $npc));

        return response()->json(status: 204);
    }

    private function npcFor(Request $request, Assistant $assistant): Assistant
    {
        abort_unless($assistant->kind === AssistantKind::WorldNpc && $assistant->users()->whereKey($request->user())->exists(), 404);

        return $assistant;
    }
}
