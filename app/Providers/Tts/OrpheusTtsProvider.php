<?php

namespace App\Providers\Tts;

use App\Contracts\TtsProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OrpheusTtsProvider implements TtsProvider
{
	public function __construct(
		private readonly string $url,
		private readonly string $model,
		private readonly string $defaultVoice,
		private readonly int $timeout,
	) {}

	public function synthesize(string $text, ?string $voice = null): string
	{
		try {
			$response = Http::timeout($this->timeout)
				->post($this->url, [
					'model' => $this->model,
					'input' => $text,
					'voice' => $voice ?? $this->defaultVoice,
				]);
		} catch (ConnectionException $e) {
			throw new RuntimeException('Failed to connect to Orpheus: '.$e->getMessage());
		}

		if ($response->failed()) {
			throw new RuntimeException('Orpheus synthesis request failed: '.$response->body());
		}

		return $response->body();
	}
}
