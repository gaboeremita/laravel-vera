<?php

namespace App\Http\Controllers\Api;

use App\Actions\StoreSound;
use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Models\World;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemSoundController extends Controller
{
    public function store(Request $request, World $world, Item $item, StoreSound $storeSound): JsonResponse
    {
        Gate::authorize('update', $world);
        $validated = $request->validate(['sound' => ['required', 'file', 'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/wave,audio/ogg,audio/webm,audio/aac,audio/mp4,audio/x-m4a', 'max:5120']]);

        $previous = $item->sound;
        $item->update(['sound_id' => $storeSound->handle($validated['sound'])->id]);
        if ($previous !== null && $previous->id !== $item->sound_id) {
            $storeSound->releaseIfUnused($previous);
        }

        return response()->json((new ItemResource($item->load(['cardImage', 'sound'])))->resolve(), 201);
    }

    public function destroy(World $world, Item $item, StoreSound $storeSound): JsonResponse
    {
        Gate::authorize('update', $world);

        $sound = $item->sound;
        $item->update(['sound_id' => null]);
        if ($sound !== null) {
            $storeSound->releaseIfUnused($sound);
        }

        return response()->json(status: 204);
    }
}
