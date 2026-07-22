<?php

namespace App\Http\Controllers\Api;

use App\Enums\Theme;
use App\Enums\TtsVoice;
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
            'tts_voice' => $settings?->data['tts_voice'] ?? TtsVoice::Tara->value,
            'available_voices' => array_column(TtsVoice::cases(), 'value'),
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
            'tts_voice' => ['sometimes', 'string', new Enum(TtsVoice::class)],
        ]);

        $settings = $request->user()->settings()
            ->where('assistant_id', $assistant)
            ->firstOrCreate(
                ['user_id' => $request->user()->id, 'assistant_id' => $assistant],
                ['data' => []]
            );

        $data = $settings->data ?? [];
        $data['theme'] = $validated['theme'];

        if (array_key_exists('tts_voice', $validated)) {
            $data['tts_voice'] = $validated['tts_voice'];
            Cache::forever(
                Settings::ttsVoiceCacheKey($request->user()->id, $assistant),
                $validated['tts_voice']
            );
        }

        $settings->update(['data' => $data]);

        return response()->json($settings->data);
    }
}
