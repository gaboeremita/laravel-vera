<?php

return [

	'stream' => (bool) env('AI_STREAM', false),

	'default' => [
		'url' => env('AI_DEFAULT_URL'),
		'key' => env('AI_DEFAULT_API_KEY', ''),
		'model' => env('AI_DEFAULT_MODEL'),
		'format' => env('AI_DEFAULT_FORMAT', 'generic'),
		'thinking' => (bool) env('AI_DEFAULT_THINKING', false),
		'config' => [
			'max_tokens' => (int) env('AI_DEFAULT_MAX_TOKENS', 4096),
			'thinking_budget' => (int) env('AI_DEFAULT_THINKING_BUDGET', 10000),
			'thinking_key' => env('AI_DEFAULT_THINKING_KEY', 'reasoning'),
			'timeout' => (int) env('AI_DEFAULT_TIMEOUT', 600),
			'version' => env('AI_DEFAULT_VERSION', '2023-06-01'),
		],
	],

	'embedding' => [
		'url' => env('AI_EMBEDDING_URL'),
		'model' => env('AI_EMBEDDING_MODEL'),
	],

	'telegram' => [
		'url' => env('TELEGRAM_URL'),
		'token' => env('TELEGRAM_BOT_TOKEN'),
		'user_id' => (int) env('TELEGRAM_USER_ID'),
		'poll_timeout' => (int) env('TELEGRAM_POLL_TIMEOUT', 30),
		'chat_id' => (int) env('TELEGRAM_CHAT_ID'),
		'assistant_id' => (int) env('TELEGRAM_ASSISTANT_ID')
	],
];