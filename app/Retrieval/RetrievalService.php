<?php

namespace App\Retrieval;

use App\Contracts\EmbeddingProvider;
use App\Models\LoreEntry;
use Illuminate\Support\Collection;

class RetrievalService
{
	public function __construct(
		private readonly EmbeddingProvider $provider,
	) {}

	/**
	 * Retrieve lore entries relevant to the given text.
	 *
	 * @return Collection<int, LoreEntry>
	 */
	public function retrieve(string $text, int $lorebookId, int $limit = 5, float $minSimilarity = 0.5): Collection
	{
		$embedding = $this->provider->embed($text);

		return LoreEntry::query()
			->where('lorebook_id', $lorebookId)
			->whereNotNull('embedding')
			->whereVectorSimilarTo('embedding', $embedding, minSimilarity: $minSimilarity)
			->limit($limit)
			->get();
	}
}