<?php

namespace App\Jobs;

use App\Actions\SummarizeConversation as SummarizeConversationAction;
use App\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SummarizeConversation implements ShouldQueue
{
	use Dispatchable, Queueable;

	public int $tries = 3;

	public int $backoff = 10;

	public int $timeout = 180;

	public function __construct(
		public Conversation $conversation,
		public int $upToMessageId,
	) {}

	public function handle(SummarizeConversationAction $action): void
	{
		$action->handle($this->conversation, $this->upToMessageId);

		$this->conversation->update(['memory_summarizing_at' => null]);
	}

	public function failed(\Throwable $exception): void
	{
		$this->conversation->update(['memory_summarizing_at' => null]);

		Log::error('Failed to summarize conversation', [
			'conversation_id' => $this->conversation->id,
			'up_to_message_id' => $this->upToMessageId,
			'error' => $exception->getMessage(),
		]);
	}
}
