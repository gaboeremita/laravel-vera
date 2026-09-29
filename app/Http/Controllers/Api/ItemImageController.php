<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\World;
use App\Traits\ManagesRoleImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemImageController extends Controller
{
    use ManagesRoleImages;

    public function storeCard(Request $request, World $world, Item $item): JsonResponse
    {
        Gate::authorize('update', $world);

        return $this->storeRoleImage($request, $item, 'card', "worlds/{$world->id}/items/{$item->id}");
    }

    public function destroyCard(World $world, Item $item): JsonResponse
    {
        Gate::authorize('update', $world);

        return $this->destroyRoleImage($item, 'card');
    }
}
