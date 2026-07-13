<?php

namespace App\Retrieval;

use App\Contracts\EmbeddingProvider;
use App\Models\ArchiveEntry;
use Illuminate\Support\Collection;

class RetrievalService
{
	public function __construct(
		private readonly EmbeddingProvider $provider,
	) {}

	/**
	 * Retrieve archive entries relevant to the given text.
	 *
	 * @return Collection<int, ArchiveEntry>
	 */
	public function retrieve(string $text, int $archiveId, int $limit = 5, float $minSimilarity = 0.5): Collection
	{
		$embedding = $this->provider->embed($text);

		return ArchiveEntry::query()
			->where('archive_id', $archiveId)
			->whereNotNull('embedding')
			->whereVectorSimilarTo('embedding', $embedding, minSimilarity: $minSimilarity)
			->limit($limit)
			->get();
	}
}