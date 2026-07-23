<?php

namespace App\Enums;

use App\Services\TtsProviders\OpenAiCompatibleTtsProvider;

enum VoiceProviderFormat: string
{
	case OpenAiCompatible = 'openai_compatible';

	public function providerClass(): string
	{
		return match ($this) {
			self::OpenAiCompatible => OpenAiCompatibleTtsProvider::class,
		};
	}
}
