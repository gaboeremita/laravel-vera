<?php

use App\Models\Assistant;
use App\Models\Emotion;
use App\Models\Pose;
use App\Services\LlmResponseTagParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('identified response tags are extracted in any position while OOC remains visible', function () {
    $assistant = Assistant::factory()->create(['portrait_type' => 'avatar3d']);
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'thoughtful']);

    $parsed = app(LlmResponseTagParser::class)->parse(
        "[OOC: Keep this visible]\nHello [scene: the archive] there. [pose: 'thoughtful'] [camera: close-up]",
        $assistant,
    );

    expect($parsed['content'])->toBe("[OOC: Keep this visible]\nHello there.")
        ->and($parsed['pose'])->toBe('thoughtful')
        ->and($parsed['scene'])->toBe('the archive')
        ->and($parsed['emotion'])->toBeNull()
        ->and($parsed['tags']['camera'])->toBe(['close-up']);
});

test('emotion and intimate tags are extracted independently of order', function () {
    $assistant = Assistant::factory()->create(['portrait_type' => 'image']);
    Emotion::factory()->create(['assistant_id' => $assistant->id, 'name' => 'thoughtful']);
    Emotion::factory()->create(['assistant_id' => $assistant->id, 'name' => 'seduced', 'restricted' => true]);

    $parsed = app(LlmResponseTagParser::class)->parse(
        'Before [intimate: true] the middle [emotion: thoughtful] after.',
        $assistant,
    );

    expect($parsed['content'])->toBe('Before the middle after.')
        ->and($parsed['emotion'])->toBe('thoughtful')
        ->and($parsed['intimate'])->toBeTrue();
});

test('legacy bare expression tags remain supported for existing saved messages', function (string $portraitType, string $tag, string $expectedKey) {
    $assistant = Assistant::factory()->create(['portrait_type' => $portraitType]);

    if ($portraitType === 'avatar3d') {
        Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => $tag]);
    } else {
        Emotion::factory()->create(['assistant_id' => $assistant->id, 'name' => $tag]);
    }

    $parsed = app(LlmResponseTagParser::class)->parse("Text before [{$tag}] text after.", $assistant);

    expect($parsed['content'])->toBe('Text before text after.')
        ->and($parsed[$expectedKey])->toBe($tag);
})->with([
    'legacy pose' => ['avatar3d', 'thoughtful', 'pose'],
    'legacy emotion' => ['image', 'happy', 'emotion'],
]);

test('unrecognized bare brackets are preserved as ordinary text', function () {
    $assistant = Assistant::factory()->create(['portrait_type' => 'avatar3d']);

    $parsed = app(LlmResponseTagParser::class)->parse('Keep [this ordinary aside] intact.', $assistant);

    expect($parsed['content'])->toBe('Keep [this ordinary aside] intact.');
});
