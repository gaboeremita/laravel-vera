<?php

namespace App\Http\Controllers\Api;

use App\Enums\Theme;
use App\Http\Controllers\Controller;
use App\Models\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            'tts_model_id' => $settings?->data['tts_model_id'] ?? null,
            'tts_voice' => $settings?->data['tts_voice'] ?? null,
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

    public function selectVoiceModel(Request $request, int $assistant): JsonResponse
    {
        $validated = $request->validate([
            'tts_model_id' => ['nullable', 'integer', 'exists:voice_models,id'],
        ]);

        $settings = $request->user()->settings()
            ->where('assistant_id', $assistant)
            ->firstOrCreate(
                ['user_id' => $request->user()->id, 'assistant_id' => $assistant],
                ['data' => []]
            );

        $data = $settings->data ?? [];
        $data['tts_model_id'] = $validated['tts_model_id'];
        $settings->update(['data' => $data]);

        $this->cacheVoiceSettings($request->user()->id, $assistant, $data);

        return response()->json(['tts_model_id' => $validated['tts_model_id']]);
    }

    public function updateVoice(Request $request, int $assistant): JsonResponse
    {
        $validated = $request->validate([
            'tts_voice' => ['nullable', 'string', 'max:100'],
        ]);

        $settings = $request->user()->settings()
            ->where('assistant_id', $assistant)
            ->firstOrCreate(
                ['user_id' => $request->user()->id, 'assistant_id' => $assistant],
                ['data' => []]
            );

        $data = $settings->data ?? [];
        $data['tts_voice'] = $validated['tts_voice'];
        $settings->update(['data' => $data]);

        $this->cacheVoiceSettings($request->user()->id, $assistant, $data);

        return response()->json(['tts_voice' => $validated['tts_voice']]);
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

    private function cacheVoiceSettings(int $userId, int $assistantId, array $data): void
    {
        Cache::forever(Settings::voiceCacheKey($userId, $assistantId), [
            'tts_model_id' => $data['tts_model_id'] ?? null,
            'tts_voice' => $data['tts_voice'] ?? null,
        ]);
    }
}
