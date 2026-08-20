<?php

namespace Database\Seeders;

use App\Enums\VoiceProviderFormat;
use App\Models\VoiceProvider;
use Illuminate\Database\Seeder;

class VoiceProviderSeeder extends Seeder
{
	public function run(): void
	{
		$orpheus = VoiceProvider::updateOrCreate(
			['name' => 'Orpheus'],
			[
				'url' => 'http://127.0.0.1:5005/v1/audio/speech',
				'format' => VoiceProviderFormat::OpenAiCompatible,
				'instructions' => "Start two local services before selecting this model:\n\n"
					."1. llama-server -m <path-to-orpheus.gguf> --host 127.0.0.1 --port 8081 -c 8192\n"
					."2. cd Orpheus-FastAPI && source venv/bin/activate && python app.py   # serves :5005\n\n"
					.'Full setup: README → Voice Mode.',
			]
		);

		$orpheus->models()->updateOrCreate(
			['endpoint' => 'orpheus'],
			[
				'name' => 'Orpheus 3B',
				'voices' => ['tara', 'leah', 'jess', 'leo', 'dan', 'mia', 'zac', 'zoe'],
				'config' => ['timeout' => 120],
			]
		);

		$kitten = VoiceProvider::updateOrCreate(
			['name' => 'KittenTTS'],
			[
				'url' => 'http://127.0.0.1:8005/v1/audio/speech',
				'format' => VoiceProviderFormat::OpenAiCompatible,
				'instructions' => "Start the server before selecting this model:\n\n"
					."brew install espeak-ng\n"
					."cd Kitten-TTS-Server && source venv/bin/activate && python server.py   # serves :8005\n\n"
					."To switch which KittenTTS model is loaded (Nano/Micro/Mini), use the wrapper's own UI:\n"
					.'http://127.0.0.1:8005',
			]
		);

		$kitten->models()->updateOrCreate(
			['endpoint' => 'kitten-tts'],
			[
				'name' => 'Kitten TTS',
				'voices' => ['Bella', 'Jasper', 'Luna', 'Bruno', 'Rosie', 'Hugo', 'Kiki', 'Leo'],
				'config' => ['timeout' => 30],
			]
		);

		$openaiTts = VoiceProvider::updateOrCreate(
			['name' => 'OpenAI TTS'],
			[
				'url' => 'https://api.openai.com/v1/audio/speech',
				'format' => VoiceProviderFormat::OpenAiTts,
				'api_key' => env('OPENAI_API_KEY'),
				'instructions' => "Requires an OpenAI API key. Add OPENAI_API_KEY to your .env file.",
			]
		);

		$openaiTts->models()->updateOrCreate(
			['endpoint' => 'gpt-4o-mini-tts'],
			[
				'name' => 'GPT-4o Mini TTS',
				'voices' => ['alloy', 'ash', 'ballad', 'coral', 'echo', 'fable', 'nova', 'onyx', 'sage', 'shimmer', 'verse', 'marin', 'cedar'],
				'config' => ['timeout' => 30],
				'prompt' => [
					'tts instructions' => "Tone: Warm, breathy, and intimate. Soft and affectionate, never harsh or clipped.\nPunctuation: Natural, unhurried pauses. Let sentences breathe — no rushing.\nDelivery: Gentle and measured. Slightly hushed and sultry. Lean into warmth and femininity.",
				],
			]
		);
	}
}
