<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ResolvesWorldUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorldSessionController extends Controller
{
    use ResolvesWorldUser;

    public function index(Request $request, int $world): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);

        $sessions = $worldUser->sessions()
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'position', 'updated_at']);

        return response()->json($sessions);
    }

    public function store(Request $request, int $world): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);

        $session = $worldUser->sessions()->create(['title' => 'New session']);

        return response()->json($session, 201);
    }

    public function update(Request $request, int $world, int $session): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
        ]);

        $worldSession = $worldUser->sessions()->findOrFail($session);
        $worldSession->update(['title' => $validated['title']]);

        return response()->json($worldSession);
    }

    public function updatePosition(Request $request, int $world, int $session): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);

        $validated = $request->validate([
            'position' => ['required', 'array:x,y,z'],
            'position.x' => ['required', 'numeric'],
            'position.y' => ['required', 'numeric'],
            'position.z' => ['required', 'numeric'],
        ]);

        $worldSession = $worldUser->sessions()->findOrFail($session);
        $worldSession->update(['position' => $validated['position']]);

        return response()->json($worldSession);
    }

    public function destroy(Request $request, int $world, int $session): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);

        $worldSession = $worldUser->sessions()->findOrFail($session);
        $worldSession->delete();

        return response()->json(status: 204);
    }
}
