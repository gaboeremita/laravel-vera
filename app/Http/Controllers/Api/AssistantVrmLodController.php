<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The low-detail version of an assistant's VRM, drawn in worlds when she is
 * far from the user.
 */
class AssistantVrmLodController extends Controller
{
    public function store(Request $request, int $id): JsonResponse
    {
        $assistant = $request->user()->assistants()->findOrFail($id);
        $vrm = $assistant->vrm;
        abort_if($vrm === null, 422, 'Upload the full VRM model before its low-detail version.');

        $request->validate([
            'vrm' => ['required', 'file', 'extensions:vrm', 'max:51200'],
        ]);

        $file = $request->file('vrm');
        $path = $file->store("vrm/{$assistant->id}", $vrm->disk);
        $previousPath = $vrm->lod_path;

        try {
            $vrm->update(['lod_path' => $path, 'lod_size' => $file->getSize()]);
        } catch (\Throwable $e) {
            Storage::disk($vrm->disk)->delete($path);
            throw $e;
        }

        if ($previousPath !== null) {
            Storage::disk($vrm->disk)->delete($previousPath);
        }

        return response()->json(['vrm_lod_url' => $vrm->lod_url], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $vrm = $request->user()->assistants()->findOrFail($id)->vrm;
        abort_if($vrm?->lod_path === null, 404, 'No low-detail VRM file found.');

        $vrm->forgetLod();

        return response()->json(['message' => 'Low-detail VRM file deleted']);
    }
}
