<?php

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Regression coverage for a real bug: generateMany() originally paired
 * responses with prompts by zipping two arrays positionally
 * (array_map($callback, $responses, $prompts)). Http::pool() keys its
 * result array by request index, but populates it in whichever order the
 * requests actually complete — under real network conditions that can
 * differ from send order, silently swapping which image ends up labeled
 * "floor" vs "surroundings". The fix looks each response up by its
 * explicit key ($responses[$index]) instead, which is correct regardless
 * of completion order.
 *
 * Http::fake() resolves pooled requests synchronously/deterministically,
 * so it cannot reproduce genuine out-of-order network completion — this
 * test only proves each image ends up correctly paired with the prompt
 * that requested it under normal conditions. The completion-order safety
 * itself follows from $responses[$index] being a key lookup (order-
 * independent by construction), not from this test's timing.
 */
test('the floor and surroundings images stay correctly paired with the prompts that produced them', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    configureImageGenModel($user, $assistant, 'https://fake-image.test/generate');

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse("FLOOR: FLOOR_MARKER a tiled stone floor\nSURROUNDINGS: SURROUNDINGS_MARKER a wide plaza")),
        'fake-image.test/*' => function ($request) {
            $prompt = $request['prompt'] ?? '';
            $marker = str_contains($prompt, 'FLOOR_MARKER') ? 'floor-image-bytes' : 'surroundings-image-bytes';

            return Http::response(imageGenHttpResponse($marker));
        },
    ]);

    GenerateAvatarBackground::dispatchFor($conversation->assistantUser, $conversation, 'a plaza');

    $background = Cache::get(GenerateAvatarBackground::cacheKeyFor($conversation->id));
    expect($background)->not->toBeNull();

    $floorPath = parse_url($background['floor_url'], PHP_URL_PATH);
    $surroundingsPath = parse_url($background['surroundings_url'], PHP_URL_PATH);
    $floorRelative = preg_replace('#^.*/storage/#', '', $floorPath);
    $surroundingsRelative = preg_replace('#^.*/storage/#', '', $surroundingsPath);

    expect(Storage::disk('public')->get($floorRelative))->toBe('floor-image-bytes');
    expect(Storage::disk('public')->get($surroundingsRelative))->toBe('surroundings-image-bytes');
});
