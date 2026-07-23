<?php

namespace App\Http\Controllers\Api;

use App\Contracts\SttProvider;
use App\Http\Controllers\Controller;
use App\Models\Settings;
use App\Models\VoiceModel;
use App\Services\TtsProviders\TtsManager;
use App\Traits\ResolvesAssistantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VoiceController extends Controller
{
	use ResolvesAssistantUser;

	public function transcribe(Request $request, int $assistant, SttProvider $stt): JsonResponse
	{
		$this->resolveAssistantUser($request, $assistant);

		$validated = $request->validate([
			'audio' => ['required', 'file', 'max:10480', 'mimetypes:audio/wav,audio/x-wav,audio/webm,audio/ogg,audio/mpeg,audio/mp4'],
		]);

		$audio = file_get_contents($validated['audio']->getRealPath());

		if ($audio === false) {
			return response()->json(['message' => 'Failed to read uploaded audio'], 422);
		}

		try {
			$text = $stt->transcribe($audio);
		} catch (\RuntimeException $e) {
			return response()->json(['message' => $e->getMessage()], 502);
		}

		return response()->json(['text' => $text]);
	}

	public function synthesize(Request $request, int $assistant, TtsManager $ttsManager): Response
	{
		$assistantUser = $this->resolveAssistantUser($request, $assistant);

		$validated = $request->validate([
			'text' => ['required', 'string'],
		]);

		$voiceSettings = Cache::rememberForever(
			Settings::voiceCacheKey($assistantUser->user_id, $assistantUser->assistant_id),
			function () use ($assistantUser) {
				$data = $assistantUser->user->settings()
					->where('assistant_id', $assistantUser->assistant_id)
					->first()?->data ?? [];

				return [
					'tts_model_id' => $data['tts_model_id'] ?? null,
					'tts_voice' => $data['tts_voice'] ?? null,
				];
			}
		);

		try {
			$tts = $voiceSettings['tts_model_id']
				? $ttsManager->fromModel(VoiceModel::with('provider')->findOrFail($voiceSettings['tts_model_id']))
				: $ttsManager->fromConfig();

			$audio = $tts->synthesize(
				text: $validated['text'],
				voice: $voiceSettings['tts_voice'],
			);
		} catch (\RuntimeException $e) {
			return response()->json(['message' => $e->getMessage()], 502);
		}

		return response($audio, 200)->header('Content-Type', 'audio/wav');
	}
}
