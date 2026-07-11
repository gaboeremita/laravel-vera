<?php

namespace App\Contracts;

use App\DTOs\LlmResponse;
use App\Models\AiModel;

interface LlmProvider
{
	/**
	 * Send a chat request to the LLM and return a unified response.
	 *
	 * @param array<int, array{role: string, content: string|null, images?: array}> $messages
	 */
	public function chat(array $messages): LlmResponse;

	public static function fromModel(AiModel $aiModel): static;
}