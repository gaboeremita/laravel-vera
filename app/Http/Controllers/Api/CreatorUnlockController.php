<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ResolvesWorldSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class CreatorUnlockController extends Controller
{
    use ResolvesWorldSession;

    public function store(Request $request, int $world, int $session): Response|JsonResponse
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        $validated = $request->validate(['password' => ['required', 'string']]);

        $hash = $request->user()->creator_password;
        if ($hash === null) {
            return response()->json(['message' => 'Set a creator password in Settings first.'], 422);
        }
        if (! Hash::check($validated['password'], $hash)) {
            return response()->json(['message' => 'That password is wrong.'], 422);
        }

        if ($worldSession->creator_mode_at === null) {
            $worldSession->update(['creator_mode_at' => now()]);
        }

        return response()->noContent();
    }
}
