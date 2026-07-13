<?php

namespace App\Http\Controllers\Api;

use App\Enums\Theme;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class SettingsController extends Controller
{
    public function show(Request $request, int $assistant): JsonResponse
    {
        $settings = $request->user()->settings()
            ->where('assistant_id', $assistant)
            ->first();

        return response()->json([
            'selected_theme' => $settings?->data['theme'] ?? 'default',
            'available_themes' => array_column(Theme::cases(), 'value'),
            'ai_model_id' => $settings?->data['ai_model_id'] ?? null,
        ]);
    }

    public function selectModel(Request $request, int $assistant): JsonResponse
    {
        $validated = $request->validate([
            'ai_model_id' => ['nullable', 'integer', 'exists:ai_models,id'],
        ]);

        $settings = $request->user()->settings()
            ->where('assistant_id', $assistant)
            ->firstOrCreate(
                ['user_id' => $request->user()->id, 'assistant_id' => $assistant],
                ['data' => []]
            );

        $data = $settings->data ?? [];
        $data['ai_model_id'] = $validated['ai_model_id'];
        $settings->update(['data' => $data]);

        return response()->json(['ai_model_id' => $validated['ai_model_id']]);
    }

    public function update(Request $request, int $assistant): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', new Enum(Theme::class)],
        ]);

        $settings = $request->user()->settings()
            ->where('assistant_id', $assistant)
            ->firstOrCreate(
                ['user_id' => $request->user()->id, 'assistant_id' => $assistant],
                ['data' => []]
            );

        $data = $settings->data ?? [];
        $data['theme'] = $validated['theme'];
        $settings->update(['data' => $data]);

        return response()->json($settings->data);
    }
}
