<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ResolvesAssistantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AgentProgressController extends Controller
{
    use ResolvesAssistantUser;

    public function show(Request $request, int $assistant, int $id): JsonResponse
    {
        $conversation = $this->resolveAssistantUser($request, $assistant)
            ->conversations()
            ->findOrFail($id);

        $status = Cache::get("agent-progress:{$conversation->id}");

        return response()->json([
            'in_progress' => $status !== null,
            'status' => $status,
        ]);
    }
}
