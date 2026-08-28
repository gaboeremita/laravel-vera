<?php

use App\Models\AssistantUser;
use App\Services\AgentLoop\Tools\ImageGenerationTool;
use App\Services\ImageGenProviders\ImageGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('timeoutSeconds reflects the resolved per-assistant image-gen configuration, not just the global default', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    $assistantUser = AssistantUser::where('user_id', $user->id)->where('assistant_id', $assistant->id)->firstOrFail();

    $service = new ImageGenerationService;
    $defaultTool = new ImageGenerationTool($service, $assistantUser, $conversation);
    $defaultTimeout = $defaultTool->timeoutSeconds();

    expect($defaultTimeout)->toBe(config('ai.image_gen.timeout') + 30);

    configureImageGenModel($user, $assistant, 'https://custom-image-gen.test/images', timeout: 45);
    $customTool = new ImageGenerationTool($service, $assistantUser, $conversation);

    expect($customTool->timeoutSeconds())->toBe(75);
    expect($customTool->timeoutSeconds())->not->toBe($defaultTimeout);
});

test('retryAttempts is 1 — no retry — unlike the shared global default', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    $assistantUser = AssistantUser::where('user_id', $user->id)->where('assistant_id', $assistant->id)->firstOrFail();

    $tool = new ImageGenerationTool(new ImageGenerationService, $assistantUser, $conversation);

    expect($tool->retryAttempts())->toBe(1);
    expect(config('agent.tool_retry_attempts'))->toBeGreaterThan(1);
});
