<?php

use App\Actions\AppendWorldConversationContext;
use App\Enums\AssistantKind;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\User;
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

it('adds a resident-specific custom prompt on top of the world context', function () {
    $world = World::factory()->create(['assistant_context_prompt' => 'Assistant world context']);
    $assistant = Assistant::factory()->create(['prompt' => ['identity' => ['Base identity']]]);
    $world->residents()->create([
        'assistant_id' => $assistant->id,
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
        'custom_prompt' => 'Only this placement knows about the hidden door.',
    ]);

    $action = new AppendWorldConversationContext;

    expect($action->handle($assistant, $world)['world_context'])->toBe([
        'Assistant world context',
        'Only this placement knows about the hidden door.',
    ]);
});

it('uses a resident-specific opening message when starting a fresh world conversation', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $assistant = Assistant::factory()->create(['opening_message' => 'Base greeting']);
    AssistantUser::factory()->create(['assistant_id' => $assistant->id, 'user_id' => $user->id]);
    $world->residents()->create([
        'assistant_id' => $assistant->id,
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
        'opening_message' => 'World-specific greeting',
    ]);

    $response = $this->actingAs($user)->postJson(route('conversations.store', $assistant), ['worldId' => $world->id]);

    $response->assertCreated();
    $conversation = Conversation::findOrFail($response->json('id'));
    expect($conversation->messages()->first()->content)->toBe('World-specific greeting');
});

it('uses an empty opening message in a world when the resident has no override, never the assistant\'s own', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $assistant = Assistant::factory()->create(['opening_message' => 'Base greeting']);
    AssistantUser::factory()->create(['assistant_id' => $assistant->id, 'user_id' => $user->id]);
    $world->residents()->create(['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);

    $response = $this->actingAs($user)->postJson(route('conversations.store', $assistant), ['worldId' => $world->id]);

    $response->assertCreated();
    $conversation = Conversation::findOrFail($response->json('id'));
    expect($conversation->messages()->first()->content)->toBe('');
});

it('rejects a character that is not a resident of the requested world', function () {
    $world = World::factory()->create();
    $assistant = Assistant::factory()->create();

    expect(fn () => (new AppendWorldConversationContext)->handle($assistant, $world))
        ->toThrow(AuthorizationException::class);
});
