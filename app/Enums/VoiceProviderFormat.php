<?php

namespace App\Enums;

use App\Services\TtsProviders\DeepgramTtsProvider;
use App\Services\TtsProviders\OpenAiCompatibleTtsProvider;
use App\Services\TtsProviders\OpenAiTtsProvider;

enum VoiceProviderFormat: string
{
	case OpenAiCompatible = 'openai_compatible';
	case OpenAiTts = 'openai_tts';
	case Deepgram = 'deepgram';

	public function providerClass(): string
	{
		return match ($this) {
			self::OpenAiCompatible => OpenAiCompatibleTtsProvider::class,
			self::OpenAiTts => OpenAiTtsProvider::class,
			self::Deepgram => DeepgramTtsProvider::class,
		};
	}
}
