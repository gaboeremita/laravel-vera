<?php

use App\Actions\AppendWorldConversationContext;
use App\Directors\PromptDirector;
use App\Enums\AssistantKind;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\Region;
use App\Models\User;
use App\Models\World;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function worldContextPrompt(Assistant $assistant, ?Region $region): string
{
    $director = new PromptDirector($assistant->prompt);
    (new AppendWorldConversationContext)->handle($director, $assistant, $region);

    return $director->build()->unchanging();
}

it('layers the world prompt, the region prompt and the region name without mutating the base prompt', function () {
    $world = Region::factory()->for(World::factory()->state([
        'assistant_context_prompt' => 'Assistant world prompt',
        'npc_context_prompt' => 'NPC world prompt',
    ]))->create([
        'name' => 'Lua Building',
        'assistant_context_prompt' => 'Assistant world context',
        'npc_context_prompt' => 'NPC world context',
    ]);
    $assistant = Assistant::factory()->create(['prompt' => ['identity' => ['Base identity']]]);
    $npc = Assistant::factory()->create(['kind' => AssistantKind::WorldNpc, 'prompt' => ['identity' => ['NPC identity']]]);
    $world->residents()->createMany([
        ['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary'],
        ['assistant_id' => $npc->id, 'position' => ['x' => 1, 'y' => 0, 'z' => 0], 'behavior' => 'stationary'],
    ]);

    expect(worldContextPrompt($assistant, $world))->toContain("# WORLD CONTEXT\nAssistant world prompt, Assistant world context, You are in Lua Building.");
    expect(worldContextPrompt($npc, $world))->toContain("# WORLD CONTEXT\nNPC world prompt, NPC world context, You are in Lua Building.");
    expect(worldContextPrompt($assistant, null))->toStartWith("# IDENTITY\nBase identity")->not->toContain('# WORLD CONTEXT');
    expect($assistant->fresh()->prompt)->toBe(['identity' => ['Base identity']]);
});

it('adds a resident-specific custom prompt on top of the world context', function () {
    $world = Region::factory()->for(World::factory()->state(['assistant_context_prompt' => 'Assistant world prompt']))->create(['name' => 'Lua Building', 'assistant_context_prompt' => 'Assistant world context']);
    $assistant = Assistant::factory()->create(['prompt' => ['identity' => ['Base identity']]]);
    $world->residents()->create([
        'assistant_id' => $assistant->id,
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
        'custom_prompt' => 'Only this placement knows about the hidden door.',
    ]);

    expect(worldContextPrompt($assistant, $world))->toContain("# WORLD CONTEXT\nAssistant world prompt, Assistant world context, You are in Lua Building., Only this placement knows about the hidden door.");
});

it('tells a resident the short line known about each other resident of their region, and nothing about the rest', function () {
    $region = Region::factory()->create();
    $elsewhere = Region::factory()->create(['world_id' => $region->world_id]);
    $place = fn (Region $in, string $name, ?string $publicDescription) => $in->residents()->create([
        'assistant_id' => Assistant::factory()->create(['name' => $name, 'kind' => AssistantKind::WorldNpc])->id,
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
        'public_description' => $publicDescription,
    ]);
    $listener = $place($region, 'Trompo', 'The taquera at the Heap.');
    $place($region, 'Oxygen', 'The diver of the Sump.');
    $place($region, 'Stranger', 'A strange man who roams Pipe Street at night.');
    $place($region, 'Secret', null);
    $place($elsewhere, 'Faraway', 'Lives in another region.');

    expect(worldContextPrompt($listener->assistant, $region))->toContain("# NEIGHBOURS\nPeople around here, as far as you know them:\n- Oxygen: The diver of the Sump.\n- Stranger: A strange man who roams Pipe Street at night.\n\n");
});

it('leaves out who is around when nobody else in the region has a known line', function () {
    $region = Region::factory()->create();
    $assistant = Assistant::factory()->create(['kind' => AssistantKind::WorldNpc]);
    $region->residents()->create(['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary', 'public_description' => 'Known to others.']);

    expect(worldContextPrompt($assistant, $region))->not->toContain('# NEIGHBOURS');
});

it('uses a resident-specific opening message when starting a fresh world conversation', function () {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $assistant = Assistant::factory()->create(['opening_message' => 'Base greeting']);
    AssistantUser::factory()->create(['assistant_id' => $assistant->id, 'user_id' => $user->id]);
    $world->residents()->create([
        'assistant_id' => $assistant->id,
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
        'opening_message' => 'World-specific greeting',
    ]);

    $response = $this->actingAs($user)->postJson(route('conversations.store', $assistant), ['worldId' => $world->world_id, 'regionId' => $world->id]);

    $response->assertCreated();
    $conversation = Conversation::findOrFail($response->json('id'));
    expect($conversation->messages()->first()->content)->toBe('World-specific greeting');
});

it('uses an empty opening message in a world when the resident has no override, never the assistant\'s own', function () {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $assistant = Assistant::factory()->create(['opening_message' => 'Base greeting']);
    AssistantUser::factory()->create(['assistant_id' => $assistant->id, 'user_id' => $user->id]);
    $world->residents()->create(['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);

    $response = $this->actingAs($user)->postJson(route('conversations.store', $assistant), ['worldId' => $world->world_id, 'regionId' => $world->id]);

    $response->assertCreated();
    $conversation = Conversation::findOrFail($response->json('id'));
    expect($conversation->messages()->first()->content)->toBe('');
});

it('rejects a character that is not a resident of the requested world', function () {
    $world = Region::factory()->create();
    $assistant = Assistant::factory()->create();

    expect(fn () => worldContextPrompt($assistant, $world))
        ->toThrow(AuthorizationException::class);
});
