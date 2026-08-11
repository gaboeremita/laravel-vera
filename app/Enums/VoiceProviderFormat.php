<?php

namespace App\Enums;

use App\Services\TtsProviders\OpenAiCompatibleTtsProvider;
use App\Services\TtsProviders\OpenAiTtsProvider;

enum VoiceProviderFormat: string
{
	case OpenAiCompatible = 'openai_compatible';
	case OpenAiTts = 'openai_tts';

	public function providerClass(): string
	{
		return match ($this) {
			self::OpenAiCompatible => OpenAiCompatibleTtsProvider::class,
			self::OpenAiTts => OpenAiTtsProvider::class,
		};
	}
}
