<?php

namespace App\Http\Controllers\Api;

use App\Enums\ImageGenProviderFormat;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class ImageGenProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $providers = $request->user()
            ->imageGenProviders()
            ->with('models')
            ->get();

        return response()->json($providers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'url', 'max:255'],
            'api_key' => ['nullable', 'string'],
            'prompt' => ['nullable', 'string'],
            'format' => ['required', new Enum(ImageGenProviderFormat::class)],
            'config_schema' => ['nullable', 'array'],
        ]);

        $provider = $request->user()
            ->imageGenProviders()
            ->create($validated);

        return response()->json($provider, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $provider = $request->user()
            ->imageGenProviders()
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'string', 'url', 'max:255'],
            'api_key' => ['sometimes', 'string'],
            'prompt' => ['nullable', 'string'],
            'format' => ['required', new Enum(ImageGenProviderFormat::class)],
            'config_schema' => ['nullable', 'array'],
        ]);

        $provider->update($validated);

        return response()->json($provider);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()
            ->imageGenProviders()
            ->findOrFail($id)
            ->delete();

        return response()->json(['message' => 'Provider deleted']);
    }
}
