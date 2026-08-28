<?php

use App\Contracts\EmbeddingProvider;
use App\Models\Archive;
use App\Models\ArchiveEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fakeEmbeddingProvider(): void
{
    app()->instance(EmbeddingProvider::class, new class implements EmbeddingProvider
    {
        public function embed(string $text): array
        {
            return array_fill(0, 768, 0.0);
        }

        public function embedBatch(array $texts): array
        {
            return array_map(fn () => $this->embed(''), $texts);
        }
    });
}

it('returns ranked results for a valid query', function () {
    fakeEmbeddingProvider();
    [$user, , $archive] = setUpAssistantWithArchive();

    $entry = ArchiveEntry::factory()->create([
        'archive_id' => $archive->id,
        'title' => 'Harbor Lighthouse',
        'content' => 'A tall lighthouse guiding ships into the harbor.',
    ]);

    $response = $this->actingAs($user)->getJson(
        route('archives.search', ['id' => $archive->id]).'?q=lighthouse'
    );

    $response->assertSuccessful();
    $response->assertJsonStructure(['results' => [['id', 'score']]]);
    expect($response->json('results.0.id'))->toBe($entry->id);
});

it('returns 404 for an archive not owned by the authenticated user', function () {
    fakeEmbeddingProvider();
    [, , $archive] = setUpAssistantWithArchive();
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser)->getJson(
        route('archives.search', ['id' => $archive->id]).'?q=lighthouse'
    );

    $response->assertNotFound();
});

it('returns 422 when the query is under 2 characters', function () {
    fakeEmbeddingProvider();
    [$user, , $archive] = setUpAssistantWithArchive();

    $response = $this->actingAs($user)->getJson(
        route('archives.search', ['id' => $archive->id]).'?q=a'
    );

    $response->assertUnprocessable();
});

it('finds an entry with no embedding yet via the keyword leg alone', function () {
    fakeEmbeddingProvider();
    [$user, , $archive] = setUpAssistantWithArchive();

    $entry = ArchiveEntry::factory()->create([
        'archive_id' => $archive->id,
        'title' => 'Whispering Pines',
        'content' => 'A dense forest of tall pines.',
        'embedding' => null,
    ]);

    $response = $this->actingAs($user)->getJson(
        route('archives.search', ['id' => $archive->id]).'?q=pines'
    );

    $response->assertSuccessful();
    expect(collect($response->json('results'))->pluck('id'))->toContain($entry->id);
});

it('excludes entries belonging to a different archive owned by the same user', function () {
    fakeEmbeddingProvider();
    [$user, , $archive] = setUpAssistantWithArchive();
    $otherArchive = Archive::factory()->create(['user_id' => $user->id]);

    ArchiveEntry::factory()->create([
        'archive_id' => $archive->id,
        'title' => 'Crimson Bridge',
        'content' => 'A bridge painted a deep crimson.',
    ]);
    $otherEntry = ArchiveEntry::factory()->create([
        'archive_id' => $otherArchive->id,
        'title' => 'Crimson Tower',
        'content' => 'A crimson tower on the hill.',
    ]);

    $response = $this->actingAs($user)->getJson(
        route('archives.search', ['id' => $archive->id]).'?q=crimson'
    );

    $response->assertSuccessful();
    expect(collect($response->json('results'))->pluck('id'))->not->toContain($otherEntry->id);
});
