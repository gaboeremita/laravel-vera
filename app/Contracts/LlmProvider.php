<?php

namespace App\Contracts;

use App\DTOs\LlmResponse;

interface LlmProvider
{
	/**
	 * Send a chat request to the LLM and return a unified response.
	 *
	 * @param  array<int, array{role: string, content: string|null, images?: array}>  $messages
	 */
	public function chat(array $messages): LlmResponse;
}