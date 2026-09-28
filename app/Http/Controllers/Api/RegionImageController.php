<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\World;
use App\Traits\ManagesRoleImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RegionImageController extends Controller
{
    use ManagesRoleImages;

    public function storeCard(Request $request, World $world, Region $region): JsonResponse
    {
        Gate::authorize('update', $world);

        return $this->storeRoleImage($request, $region, 'card', "regions/{$region->id}");
    }

    public function destroyCard(World $world, Region $region): JsonResponse
    {
        Gate::authorize('update', $world);

        return $this->destroyRoleImage($region, 'card');
    }

    public function storePortrait(Request $request, World $world, Region $region): JsonResponse
    {
        Gate::authorize('update', $world);

        return $this->storeRoleImage($request, $region, 'portrait', "regions/{$region->id}");
    }

    public function destroyPortrait(World $world, Region $region): JsonResponse
    {
        Gate::authorize('update', $world);

        return $this->destroyRoleImage($region, 'portrait');
    }
}
