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

	'stt' => [
		'url' => env('AI_STT_URL'),
		'model' => env('AI_STT_MODEL'),
		'format' => env('AI_STT_FORMAT', 'whisper'),
		'timeout' => (int) env('AI_STT_TIMEOUT', 60),
	],

	'tts' => [
		'url' => env('AI_TTS_URL'),
		'key' => env('AI_TTS_API_KEY', ''),
		'model' => env('AI_TTS_MODEL'),
		'format' => env('AI_TTS_FORMAT', 'openai_compatible'),
		'voice' => env('AI_TTS_VOICE', 'tara'),
		'timeout' => (int) env('AI_TTS_TIMEOUT', 120),
	],

	'telegram' => [
		'url' => env('TELEGRAM_URL'),
		'token' => env('TELEGRAM_BOT_TOKEN'),
		'user_id' => (int) env('TELEGRAM_USER_ID'),
		'poll_timeout' => (int) env('TELEGRAM_POLL_TIMEOUT', 30),
		'send_timeout' => (int) env('TELEGRAM_SEND_TIMEOUT', 15),
		'typing_timeout' => (int) env('TELEGRAM_TYPING_TIMEOUT', 10),
		'file_timeout' => (int) env('TELEGRAM_FILE_TIMEOUT', 15),
		'download_timeout' => (int) env('TELEGRAM_DOWNLOAD_TIMEOUT', 30),
		'chat_id' => (int) env('TELEGRAM_CHAT_ID'),
		'assistant_id' => (int) env('TELEGRAM_ASSISTANT_ID')
	],
];