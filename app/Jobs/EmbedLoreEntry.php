<?php

namespace App\Jobs;

use App\Contracts\EmbeddingProvider;
use App\Models\LoreEntry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmbedLoreEntry implements ShouldQueue
{
	use Dispatchable, Queueable;

	public int $tries = 3;

	public int $backoff = 10;

	public function __construct(
		public LoreEntry $entry,
	) {}

	public function handle(EmbeddingProvider $provider): void
	{
		$tags = $this->entry->tags->pluck('name')->implode(', ');

		$textToEmbed = $this->entry->title;

		if ($tags) {
			$textToEmbed .= " [{$tags}]";
		}

		$textToEmbed .= ": {$this->entry->content}";

		$embedding = $provider->embed($textToEmbed);
		$this->entry->update(compact('embedding'));
	}

	public function failed(\Throwable $exception): void
	{
		Log::error('Failed to embed lore entry', [
			'entry_id' => $this->entry->id,
			'error' => $exception->getMessage(),
		]);
	}
}
