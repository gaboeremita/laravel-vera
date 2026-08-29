<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAvatarBackground;
use App\Traits\ResolvesAssistantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AvatarBackgroundController extends Controller
{
    use ResolvesAssistantUser;

    public function show(Request $request, int $assistant, int $id): JsonResponse
    {
        $conversation = $this->resolveAssistantUser($request, $assistant)
            ->conversations()
            ->findOrFail($id);

        $status = Cache::get(GenerateAvatarBackground::progressKeyFor($conversation->id));

        return response()->json([
            'in_progress' => $status !== null,
            'status' => $status,
            'background' => Cache::get(GenerateAvatarBackground::cacheKeyFor($conversation->id)),
        ]);
    }
}
