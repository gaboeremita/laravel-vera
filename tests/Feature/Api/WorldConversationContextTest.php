<?php

use App\Actions\AppendWorldConversationContext;
use App\Enums\AssistantKind;
use App\Models\Assistant;
use App\Models\World;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('appends the appropriate world context without mutating the base prompt', function () {
    $world = World::factory()->create([
        'assistant_context_prompt' => 'Assistant world context',
        'npc_context_prompt' => 'NPC world context',
    ]);
    $assistant = Assistant::factory()->create(['prompt' => ['identity' => ['Base identity']]]);
    $npc = Assistant::factory()->create(['kind' => AssistantKind::WorldNpc, 'prompt' => ['identity' => ['NPC identity']]]);
    $world->residents()->createMany([
        ['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary'],
        ['assistant_id' => $npc->id, 'position' => ['x' => 1, 'y' => 0, 'z' => 0], 'behavior' => 'stationary'],
    ]);

    $action = new AppendWorldConversationContext;

    expect($action->handle($assistant, $world)['world_context'])->toBe(['Assistant world context']);
    expect($action->handle($npc, $world)['world_context'])->toBe(['NPC world context']);
    expect($action->handle($assistant, null))->toBe(['identity' => ['Base identity']]);
    expect($assistant->fresh()->prompt)->toBe(['identity' => ['Base identity']]);
});

it('rejects a character that is not a resident of the requested world', function () {
    $world = World::factory()->create();
    $assistant = Assistant::factory()->create();

    expect(fn () => (new AppendWorldConversationContext)->handle($assistant, $world))
        ->toThrow(AuthorizationException::class);
});
