<?php

return [
	'default' => env('AI_PROVIDER', 'ollama'),

	'defaults' => [
		'timeout' => (int) env('AI_TIMEOUT', 120),
		'stream' => (bool) env('AI_STREAM', false),
	],

	'providers' => [
		'ollama' => [
			'url' => env('OLLAMA_URL'),
			'model' => env('OLLAMA_MODEL'),
			'think' => (bool) env('OLLAMA_THINK', true),
		],

		'openrouter' => [
			'url' => env('OPENROUTER_URL'),
			'key' => env('OPENROUTER_API_KEY'),
			'model' => env('OPENROUTER_MODEL'),
			'max_tokens' => (int) env('OPENROUTER_MAX_TOKENS', 4096),
			'reasoning' => [
				'enabled' => (bool) env('OPENROUTER_REASONING', false),
				'max_tokens' => (int) env('OPENROUTER_REASONING_MAX_TOKENS', 10000),
			],
		],

		'anthropic' => [
			'url' => env('ANTHROPIC_URL'),
			'key' => env('ANTHROPIC_API_KEY'),
			'model' => env('ANTHROPIC_MODEL'),
			'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
			'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 4096),
			'thinking' => [
				'enabled' => (bool) env('ANTHROPIC_THINKING', true),
				'budget_tokens' => (int) env('ANTHROPIC_THINKING_BUDGET', 10000),
			],
		],
	],
];