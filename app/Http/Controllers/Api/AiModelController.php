<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\ValidModelConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiModelController extends Controller
{
    public function store(Request $request, int $providerId): JsonResponse
    {
        $provider = $request->user()
            ->aiProviders()
            ->findOrFail($providerId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'endpoint' => ['required', 'string', 'max:255'],
            'thinking_key' => ['nullable', 'string', 'max:100'],
            'config' => ['nullable', 'array', new ValidModelConfig($provider->config_schema ?? [])],
            'additional_config' => ['nullable', 'array'],
            'prompt' => ['nullable', 'string'],
        ]);

        $model = $provider->models()->create($validated);

        return response()->json($model, 201);
    }

    public function update(Request $request, int $providerId, int $id): JsonResponse
    {
        $provider = $request->user()
            ->aiProviders()
            ->findOrFail($providerId);

        $model = $provider->models()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'endpoint' => ['sometimes', 'string', 'max:255'],
            'thinking_key' => ['nullable', 'string', 'max:100'],
            'config' => ['nullable', 'array', new ValidModelConfig($provider->config_schema ?? [])],
            'additional_config' => ['nullable', 'array'],
            'prompt' => ['nullable', 'string'],
        ]);

        $model->update($validated);

        return response()->json($model);
    }

    public function destroy(Request $request, int $providerId, int $id): JsonResponse
    {
        $provider = $request->user()
            ->aiProviders()
            ->findOrFail($providerId);

        $provider->models()->findOrFail($id)->delete();

        return response()->json(['message' => 'Model deleted']);
    }
}
