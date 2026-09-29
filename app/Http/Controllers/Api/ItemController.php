<?php

namespace App\Http\Controllers\Api;

use App\Actions\Quests\FindQuestReferences;
use App\Actions\DeleteItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Models\World;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ItemController extends Controller
{
    public function index(World $world, DeleteItem $deleteItem): JsonResponse
    {
        Gate::authorize('view', $world);

        $items = $world->items()->with(['cardImage', 'sound'])->orderBy('name')->get()
            ->each(fn (Item $item) => $item->usage = $deleteItem->usage($item));

        return response()->json(ItemResource::collection($items)->resolve());
    }

    public function store(SaveItemRequest $request, World $world): JsonResponse
    {
        Gate::authorize('update', $world);

        $item = $world->items()->create($request->attributesForItem());

        return response()->json((new ItemResource($item->load(['cardImage', 'sound'])))->resolve(), 201);
    }

    public function update(SaveItemRequest $request, World $world, Item $item): JsonResponse
    {
        Gate::authorize('update', $world);

        $item->update($request->attributesForItem());

        return response()->json((new ItemResource($item->load(['cardImage', 'sound'])))->resolve());
    }

    public function destroy(World $world, Item $item, DeleteItem $deleteItem, FindQuestReferences $findQuestReferences): JsonResponse
    {
        Gate::authorize('update', $world);
        $findQuestReferences->ensureItemUnused($item);

        $deleteItem->handle($item);

        return response()->json(status: 204);
    }
}
