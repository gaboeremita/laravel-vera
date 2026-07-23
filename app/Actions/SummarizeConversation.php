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

	private function lockStillHeld(Conversation $conversation, string $lockedAt): bool
	{
		return $conversation->newQuery()
			->whereKey($conversation->id)
			->where('memory_summarizing_at', $lockedAt)
			->exists();
	}

	public function handle(Conversation $conversation, int $upToMessageId, string $lockedAt): void
	{
		$checkpoint = $conversation->memory_checkpoint_message_id ?? 0;

		$pendingQuery = $conversation->messages()
			->where('id', '>', $checkpoint)
			->where('id', '<=', $upToMessageId);

		$pendingCount = $pendingQuery->count();

		if ($pendingCount > self::MAX_BACKLOG_MESSAGES) {
			$skipToId = (clone $pendingQuery)
				->orderByDesc('id')
				->skip(self::MAX_BACKLOG_MESSAGES)
				->value('id');

			$checkpoint = $skipToId ?? $checkpoint;
		}

		$llm = null;
		$userName = $conversation->assistantUser->user->name;
		$assistantName = $conversation->assistantUser->assistant->name;
		$instructions = $this->buildInstructions($conversation);

		while ($checkpoint < $upToMessageId) {
			// A force-unlock (or a newer run) may have cleared or replaced this
			// lock while we were mid-batch — stop writing rather than clobber it.
			if (! $this->lockStillHeld($conversation, $lockedAt)) {
				break;
			}

			$messages = $conversation->messages()
				->where('id', '>', $checkpoint)
				->where('id', '<=', $upToMessageId)
				->orderBy('id')
				->limit(self::BATCH_SIZE)
				->get(['id', 'role', 'content']);

			if ($messages->isEmpty()) {
				break;
			}

			$batchEnd = (int) $messages->last()->id;

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

			if ($summary === '') {
				throw new \RuntimeException('Conversation summarization returned an empty response.');
			}

			$existing = $conversation->long_term_memory;

			$conversation->update([
				'long_term_memory' => $existing ? "{$summary}\n\n---\n\n{$existing}" : $summary,
				'memory_checkpoint_message_id' => $batchEnd,
			]);

			$checkpoint = $batchEnd;
		}
	}
}
