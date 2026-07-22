<?php

namespace App\Providers\Stt;

use App\Contracts\SttProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhisperSttProvider implements SttProvider
{
	public function __construct(
		private readonly ?string $baseUrl,
		private readonly ?string $model,
		private readonly int $timeout,
	) {}

	public function transcribe(string $audio): string
	{
		try {
			$response = Http::baseUrl($this->baseUrl)
				->timeout($this->timeout)
				->attach('file', $audio, 'audio.wav')
				->post('/inference', [
					'model' => $this->model,
				]);
		} catch (ConnectionException $e) {
			throw new RuntimeException('Failed to connect to whisper.cpp: '.$e->getMessage());
		}

		if ($response->failed()) {
			throw new RuntimeException('Whisper transcription request failed: '.$response->body());
		}

		$text = $response->json('text');

		if ($text === null) {
			throw new RuntimeException('Unexpected transcription response from whisper.cpp');
		}

		return trim($text);
	}
}
