<?php

return [
	'ollama' => [
		'url' => env('OLLAMA_URL', 'http://localhost:11434'),
		'model' => env('OLLAMA_MODEL', 'gemma4:latest'),
	],
];