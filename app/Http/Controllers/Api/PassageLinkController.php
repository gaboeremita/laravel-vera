<?php

namespace App\Http\Controllers\Api;

use App\Actions\LinkPassages;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePassageLinkRequest;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use App\Models\World;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PassageLinkController extends Controller
{
    public function update(UpdatePassageLinkRequest $request, World $world, Region $region, string $passage, LinkPassages $linkPassages): JsonResponse
    {
        Gate::authorize('update', $world);
        $target = Region::findOrFail($request->integer('targetRegionId'));

        $linkPassages->link($region, $passage, $target, $request->validated('targetPassageId'));

        return response()->json([
            'regions' => collect([$region, $target])
                ->map(fn (Region $linked) => (new RegionResource($linked->fresh(['passageLinks.targetRegion'])))->resolve()),
        ]);
    }

    public function destroy(World $world, Region $region, string $passage, LinkPassages $linkPassages): JsonResponse
    {
        Gate::authorize('update', $world);

        $linkPassages->unlink($region, $passage);

        return response()->json(status: 204);
    }
}
