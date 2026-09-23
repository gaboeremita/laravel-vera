<?php

namespace App\Http\Controllers\Api;

use App\Enums\Posture;
use App\Http\Controllers\Controller;
use App\Models\WorldSessionResident;
use App\Traits\ResolvesWorldUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ResidentStateController extends Controller
{
    use ResolvesWorldUser;

    public function update(Request $request, int $world, int $session, int $resident): Response
    {
        $worldUser = $this->resolveWorldUser($request, $world);
        $worldSession = $worldUser->sessions()->findOrFail($session);
        $worldResident = $worldUser->world->residents()->findOrFail($resident);

        $validated = $request->validate([
            'position' => ['required', 'array:x,y,z'],
            'position.*' => ['required', 'numeric'],
            'rotation' => ['nullable', 'array:y'],
            'rotation.y' => ['required_with:rotation', 'numeric'],
            'spotId' => ['nullable', 'string', 'max:100'],
            'activityId' => ['nullable', 'string', 'max:100'],
            'posture' => ['sometimes', Rule::enum(Posture::class)],
            'exitPosition' => ['nullable', 'array:x,y,z'],
            'exitPosition.*' => ['required', 'numeric'],
        ]);

        WorldSessionResident::updateOrCreate(
            ['world_session_id' => $worldSession->id, 'world_resident_id' => $worldResident->id],
            [
                'position' => $validated['position'],
                'rotation' => $validated['rotation'] ?? null,
                'spot_id' => $validated['spotId'] ?? null,
                'activity_id' => $validated['activityId'] ?? null,
                'posture' => $validated['posture'] ?? Posture::Standing->value,
                'exit_position' => $validated['exitPosition'] ?? null,
            ],
        );

        return response()->noContent();
    }
}
