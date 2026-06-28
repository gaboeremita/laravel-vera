<?php

return [
	'default' => env('AI_PROVIDER', 'ollama'),

	'defaults' => [
		'timeout' => (int) env('AI_TIMEOUT', 600),
		'stream' => (bool) env('AI_STREAM', false),
	],

	'providers' => [
		'ollama' => [
			'url' => env('OLLAMA_URL'),
			'llm_model' => env('OLLAMA_LLM_MODEL'),
			'think' => (bool) env('OLLAMA_THINK', true),
			'embedding_model' => env('OLLAMA_EMBEDDING_MODEL')
		],

		'openrouter' => [
			'url' => env('OPENROUTER_URL'),
			'key' => env('OPENROUTER_API_KEY'),
			'llm_model' => env('OPENROUTER_LLM_MODEL'),
			'max_tokens' => (int) env('OPENROUTER_MAX_TOKENS', 4096),
			'reasoning' => [
				'enabled' => (bool) env('OPENROUTER_REASONING', false),
				'max_tokens' => (int) env('OPENROUTER_REASONING_MAX_TOKENS', 10000),
			],
		],

		'anthropic' => [
			'url' => env('ANTHROPIC_URL'),
			'key' => env('ANTHROPIC_API_KEY'),
			'llm_model' => env('ANTHROPIC_LLM_MODEL'),
			'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
			'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 4096),
			'thinking' => [
				'enabled' => (bool) env('ANTHROPIC_THINKING', true),
				'budget_tokens' => (int) env('ANTHROPIC_THINKING_BUDGET', 10000),
			],
		],
	],

	'telegram' => [
		'url' => env('TELEGRAM_URL'),
		'token' => env('TELEGRAM_BOT_TOKEN'),
		'user_id' => (int) env('TELEGRAM_USER_ID', 1),
		'poll_timeout' => (int) env('TELEGRAM_POLL_TIMEOUT', 30),
		'chat_id' => (int) env('TELEGRAM_CHAT_ID'),
	],
];