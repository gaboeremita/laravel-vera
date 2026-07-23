<?php

namespace App\Actions;

use App\Builders\PromptBuilder;
use App\Models\Conversation;
use App\Services\LlmProviders\LlmManager;

class SummarizeConversation
{
	private const BATCH_SIZE = 50;

	private const MAX_BACKLOG_MESSAGES = 200;

	private const FALLBACK_INSTRUCTIONS = 'Write a brief narrative summary of this conversation segment.';

	public function __construct(private LlmManager $llmManager) {}

	private function buildInstructions(Conversation $conversation): string
	{
		$sections = $conversation->assistantUser->memory_prompt ?? [];

		$builder = new PromptBuilder();

		foreach ($sections as $key => $value) {
			$builder->section($key, $value);
		}

		$instructions = $builder->build();

		return $instructions !== '' ? $instructions : self::FALLBACK_INSTRUCTIONS;
	}

	public function handle(Conversation $conversation, int $upToMessageId): void
	{
		$checkpoint = $conversation->memory_checkpoint_message_id ?? 0;

		// Never reach further back than this many messages — older backlog is
		// skipped over (checkpoint fast-forwarded) rather than summarized.
		if ($upToMessageId - $checkpoint > self::MAX_BACKLOG_MESSAGES) {
			$checkpoint = $upToMessageId - self::MAX_BACKLOG_MESSAGES;
		}

		$llm = null;
		$userName = $conversation->assistantUser->user->name;
		$assistantName = $conversation->assistantUser->assistant->name;
		$instructions = $this->buildInstructions($conversation);

		while ($checkpoint < $upToMessageId) {
			$batchEnd = min($checkpoint + self::BATCH_SIZE, $upToMessageId);

			$messages = $conversation->messages()
				->whereBetween('id', [$checkpoint + 1, $batchEnd])
				->orderBy('id')
				->get(['role', 'content']);

			if ($messages->isEmpty()) {
				$checkpoint = $batchEnd;
				continue;
			}

			$transcript = $messages
				->map(fn ($message) => match ($message->role) {
					'user' => "{$userName}: {$message->content}",
					'assistant' => "{$assistantName}: {$message->content}",
					default => "{$message->role}: {$message->content}",
				})
				->implode("\n");

			$existingMemory = $conversation->long_term_memory ?: 'Nothing yet.';

			$llm ??= $this->llmManager->forAssistantUser($conversation->assistantUser);

			$response = $llm->chat([
				['role' => 'system', 'content' => $instructions],
				['role' => 'user', 'content' => "Established so far:\n{$existingMemory}\n\nNew scene:\n{$transcript}"],
			]);

			$summary = trim($response->content ?? '');

			if ($summary !== '') {
				$existing = $conversation->long_term_memory;

				$conversation->update([
					'long_term_memory' => $existing ? "{$summary}\n\n---\n\n{$existing}" : $summary,
				]);
			}

			$checkpoint = $batchEnd;
			$conversation->update(['memory_checkpoint_message_id' => $checkpoint]);
		}
	}
}
