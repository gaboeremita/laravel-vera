<?php

namespace App\Http\Controllers\Api;

use App\Contracts\SttProvider;
use App\Contracts\TtsProvider;
use App\Enums\TtsVoice;
use App\Http\Controllers\Controller;
use App\Models\Settings;
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

		try {
			$text = $stt->transcribe(file_get_contents($validated['audio']->getRealPath()));
		} catch (\RuntimeException $e) {
			return response()->json(['message' => $e->getMessage()], 502);
		}

		return response()->json(['text' => $text]);
	}

	public function synthesize(Request $request, int $assistant, TtsProvider $tts): Response
	{
		$assistantUser = $this->resolveAssistantUser($request, $assistant);

		$validated = $request->validate([
			'text' => ['required', 'string'],
		]);

		$voice = Cache::rememberForever(
			Settings::ttsVoiceCacheKey($assistantUser->user_id, $assistantUser->assistant_id),
			fn () => $assistantUser->user->settings()
				->where('assistant_id', $assistantUser->assistant_id)
				->first()?->data['tts_voice'] ?? TtsVoice::Tara->value
		);

		try {
			$audio = $tts->synthesize(
				text: $validated['text'],
				voice: $voice,
			);
		} catch (\RuntimeException $e) {
			return response()->json(['message' => $e->getMessage()], 502);
		}

		return response($audio, 200)->header('Content-Type', 'audio/wav');
	}
}
