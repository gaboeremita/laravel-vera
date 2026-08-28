<?php

namespace App\Actions;

use App\Contracts\EmbeddingProvider;
use App\Models\ArchiveEntry;

class SearchArchiveEntries
{
    private const int RESULT_LIMIT = 20;

    private const int RRF_K = 60;

    private const float MIN_SIMILARITY = 0.50;

    public function __construct(private readonly EmbeddingProvider $embeddingProvider) {}

    /**
     * Search an archive's entries by full-text keyword match and semantic
     * similarity, merging both ranked lists via Reciprocal Rank Fusion.
     *
     * @return array<int, array{id: int, score: float}>
     */
    public function handle(int $archiveId, string $query): array
    {
        $keywordIds = ArchiveEntry::query()
            ->where('archive_id', $archiveId)
            ->whereFullText(['title', 'content'], $query)
            ->orderBy('id')
            ->limit(self::RESULT_LIMIT)
            ->pluck('id');

        $embedding = $this->embeddingProvider->embed($query);

        $vectorIds = ArchiveEntry::query()
            ->where('archive_id', $archiveId)
            ->whereNotNull('embedding')
            ->whereVectorSimilarTo('embedding', $embedding, minSimilarity: self::MIN_SIMILARITY)
            ->limit(self::RESULT_LIMIT)
            ->pluck('id');

        $scores = [];

        foreach ([$keywordIds, $vectorIds] as $rankedIds) {
            foreach ($rankedIds as $rank => $id) {
                $scores[$id] = ($scores[$id] ?? 0) + 1 / (self::RRF_K + $rank + 1);
            }
        }

        arsort($scores);

        return collect($scores)
            ->map(fn (float $score, int $id) => ['id' => $id, 'score' => $score])
            ->values()
            ->all();
    }
}
