<?php

namespace App\Enums;

use App\Services\LlmProviders\AnthropicProvider;
use App\Services\LlmProviders\GenericProvider;

enum AiProviderFormat: string
{
	case Generic = 'generic';
	case Anthropic = 'anthropic';

	public function providerClass(): string
	{
		return match ($this) {
			self::Generic => GenericProvider::class,
			self::Anthropic => AnthropicProvider::class,
		};
	}
}
