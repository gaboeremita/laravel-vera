<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCreatorPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorPasswordController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['isSet' => $request->user()->creator_password !== null]);
    }

    public function update(UpdateCreatorPasswordRequest $request): JsonResponse
    {
        $request->user()->update(['creator_password' => $request->validated('password')]);

        return response()->json(status: 204);
    }
}
