<?php

use App\Contracts\EmbeddingProvider;
use App\Jobs\GenerateAvatarBackground;
use App\Models\Archive;
use App\Models\ArchiveEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('a requested location matching an archive entry grounds the generated prompts in its documented details', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    configureImageGenModel($user, $assistant, 'https://fake-image.test/generate');

    $archive = Archive::factory()->create(['user_id' => $user->id]);
    $assistant->update(['archive_id' => $archive->id]);

    $embedding = array_fill(0, 768, 0.5);

    ArchiveEntry::factory()->create([
        'archive_id' => $archive->id,
        'title' => 'The Neon Bar',
        'content' => 'Red neon signage, rain-slicked street outside, jazz playing softly.',
        'embedding' => $embedding,
    ]);

    $this->mock(EmbeddingProvider::class)
        ->shouldReceive('embed')
        ->andReturn($embedding);

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse("FLOOR: wet reflective pavement with red neon glow\nSURROUNDINGS: a rain-slicked street lined with neon signage, jazz atmosphere")),
        'fake-image.test/*' => Http::response(imageGenHttpResponse()),
    ]);

    GenerateAvatarBackground::dispatch($conversation->assistantUser, $conversation, 'the neon bar');

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://fake-llm.test/chat/completions') {
            return true;
        }

        $systemContent = collect($request['messages'] ?? [])->firstWhere('role', 'system')['content'] ?? '';

        return str_contains($systemContent, 'Red neon signage')
            && str_contains($systemContent, 'The Neon Bar');
    });

    $background = Cache::get(GenerateAvatarBackground::cacheKeyFor($conversation->id));
    expect($background)->not->toBeNull();
    expect($background['floor_url'])->toBeString();
    expect($background['surroundings_url'])->toBeString();
});
