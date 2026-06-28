<?php

namespace App\Services\LlmProviders;

use App\Contracts\LlmProvider;
use InvalidArgumentException;

class LlmManager
{
	public function resolve(?string $name = null): LlmProvider
	{
		$name = $name ?? config('ai.default');
		$config = config("ai.providers.{$name}");
		$defaults = config('ai.defaults');

		if (! $config) {
			throw new InvalidArgumentException("LLM provider [{$name}] is not configured.");
		}

		$timeout = $defaults['timeout'];
		$stream = $defaults['stream'];

		return match ($name) {
			'ollama' => new OllamaProvider(
				url: $config['url'],
				model: $config['llm_model'],
				timeout: $timeout,
				stream: $stream,
				think: $config['think'],
			),
			'openrouter' => new OpenRouterProvider(
				url: $config['url'],
				model: $config['llm_model'],
				key: $config['key'],
				timeout: $timeout,
				stream: $stream,
				maxTokens: $config['max_tokens'],
				reasoningEnabled: $config['reasoning']['enabled'],
				reasoningMaxTokens: $config['reasoning']['max_tokens'],
			),
			'anthropic' => new AnthropicProvider(
				url: $config['url'],
				model: $config['llm_model'],
				key: $config['key'],
				version: $config['version'],
				timeout: $timeout,
				stream: $stream,
				maxTokens: $config['max_tokens'],
				thinkingEnabled: $config['thinking']['enabled'],
				thinkingBudget: $config['thinking']['budget_tokens'],
			),
			default => throw new InvalidArgumentException("Unknown LLM provider [{$name}]."),
		};
	}
}