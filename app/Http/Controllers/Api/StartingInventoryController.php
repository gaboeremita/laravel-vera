<?php

namespace App\Http\Controllers\Api;

use App\Actions\SaveStartingInventory;
use App\Enums\InventoryHolder;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateObjectStartingInventoryRequest;
use App\Http\Requests\UpdatePlayerStartingInventoryRequest;
use App\Http\Requests\UpdateResidentStartingInventoryRequest;
use App\Models\Region;
use App\Models\StartingInventory;
use App\Models\World;
use App\Models\WorldResident;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class StartingInventoryController extends Controller
{
    public function index(World $world): JsonResponse
    {
        Gate::authorize('view', $world);

        $startings = $world->startingInventories()->with('items')->get();

        return response()->json([
            'player' => SaveStartingInventory::present($startings->firstWhere('holder', InventoryHolder::Player)),
            'residents' => $startings->where('holder', InventoryHolder::Resident)
                ->mapWithKeys(fn (StartingInventory $starting) => [$starting->world_resident_id => SaveStartingInventory::present($starting)])
                ->all(),
            'objects' => $startings->where('holder', InventoryHolder::Object)
                ->groupBy('region_id')
                ->map(fn ($inRegion) => $inRegion->mapWithKeys(fn (StartingInventory $starting) => [$starting->object_id => SaveStartingInventory::present($starting)]))
                ->all(),
        ]);
    }

    public function updatePlayer(UpdatePlayerStartingInventoryRequest $request, World $world, SaveStartingInventory $save): JsonResponse
    {
        Gate::authorize('update', $world);

        $starting = $save->handle($world, ['holder' => InventoryHolder::Player, 'world_resident_id' => null, 'region_id' => null, 'object_id' => null], $request->credits(), $request->items());

        return response()->json(SaveStartingInventory::present($starting));
    }

    public function updateResident(UpdateResidentStartingInventoryRequest $request, World $world, WorldResident $resident, SaveStartingInventory $save): JsonResponse
    {
        Gate::authorize('update', $world);

        $starting = $save->handle($world, ['holder' => InventoryHolder::Resident, 'world_resident_id' => $resident->id, 'region_id' => null, 'object_id' => null], $request->credits(), $request->items());

        return response()->json(SaveStartingInventory::present($starting));
    }

    public function updateObject(UpdateObjectStartingInventoryRequest $request, World $world, Region $region, string $object, SaveStartingInventory $save): JsonResponse
    {
        Gate::authorize('update', $world);

        $starting = $save->handle($world, ['holder' => InventoryHolder::Object, 'world_resident_id' => null, 'region_id' => $region->id, 'object_id' => $object], $request->credits(), $request->items());

        return response()->json(SaveStartingInventory::present($starting));
    }
}
