<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\World;
use App\Traits\ManagesRoleImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorldImageController extends Controller
{
    use ManagesRoleImages;

    public function storeCard(Request $request, World $world): JsonResponse
    {
        Gate::authorize('update', $world);

        return $this->storeRoleImage($request, $world, 'card', "worlds/{$world->id}");
    }

    public function destroyCard(World $world): JsonResponse
    {
        Gate::authorize('update', $world);

        return $this->destroyRoleImage($world, 'card');
    }

    public function storePortrait(Request $request, World $world): JsonResponse
    {
        Gate::authorize('update', $world);

        return $this->storeRoleImage($request, $world, 'portrait', "worlds/{$world->id}");
    }

    public function destroyPortrait(World $world): JsonResponse
    {
        Gate::authorize('update', $world);

        return $this->destroyRoleImage($world, 'portrait');
    }
}
