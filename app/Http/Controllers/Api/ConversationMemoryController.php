<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SummarizeConversation;
use App\Traits\ResolvesAssistantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationMemoryController extends Controller
{
	use ResolvesAssistantUser;

	public function show(Request $request, int $assistant, int $id): JsonResponse
	{
		$conversation = $this->resolveAssistantUser($request, $assistant)
			->conversations()
			->findOrFail($id);

		$checkpoint = $conversation->memory_checkpoint_message_id ?? 0;
		$pendingCount = $conversation->messages()->where('id', '>', $checkpoint)->count();

		return response()->json([
			'long_term_memory' => $conversation->long_term_memory,
			'pending_count' => $pendingCount,
			'is_summarizing' => $conversation->memory_summarizing_at !== null,
			'auto_summarize_enabled' => $conversation->auto_summarize_enabled,
		]);
	}

	public function update(Request $request, int $assistant, int $id): JsonResponse
	{
		$validated = $request->validate([
			'long_term_memory' => ['sometimes', 'nullable', 'string'],
			'auto_summarize_enabled' => ['sometimes', 'boolean'],
		]);

		$conversation = $this->resolveAssistantUser($request, $assistant)
			->conversations()
			->findOrFail($id);

		if ($conversation->memory_summarizing_at !== null) {
			return response()->json(['message' => 'Memory is being summarized in the background.'], 409);
		}

		$conversation->update($validated);

		return response()->json([
			'long_term_memory' => $conversation->long_term_memory,
			'auto_summarize_enabled' => $conversation->auto_summarize_enabled,
		]);
	}

	public function summarize(Request $request, int $assistant, int $id): JsonResponse
	{
		$validated = $request->validate([
			'mode' => ['sometimes', 'string', 'in:since_last,full'],
		]);

		$conversation = $this->resolveAssistantUser($request, $assistant)
			->conversations()
			->findOrFail($id);

		if ($conversation->memory_summarizing_at !== null) {
			return response()->json(['queued' => false, 'already_summarizing' => true]);
		}

		if (($validated['mode'] ?? 'since_last') === 'full') {
			$conversation->update(['memory_checkpoint_message_id' => 0]);
		}

		$upToMessageId = $conversation->messages()->max('id');
		$checkpoint = $conversation->memory_checkpoint_message_id ?? 0;

		if (! $upToMessageId || $upToMessageId <= $checkpoint) {
			return response()->json(['queued' => false]);
		}

		$conversation->update(['memory_summarizing_at' => now()]);

		SummarizeConversation::dispatch($conversation, $upToMessageId);

		return response()->json(['queued' => true]);
	}

	public function unlock(Request $request, int $assistant, int $id): JsonResponse
	{
		$conversation = $this->resolveAssistantUser($request, $assistant)
			->conversations()
			->findOrFail($id);

		$conversation->update(['memory_summarizing_at' => null]);

		return response()->json(['long_term_memory' => $conversation->long_term_memory]);
	}
}
