<?php

use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\User;
use App\Models\World;
use App\Models\WorldSession;
use App\Models\WorldUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function setUpWorldSessionAssistant(): array
{
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $worldUser = WorldUser::where('world_id', $world->id)->where('user_id', $user->id)->firstOrFail();
    $assistant = Assistant::factory()->create();
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $assistant->id]);
    $world->residents()->create(['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);

    return [$user, $world, $worldUser, $assistant];
}

it('creates a conversation scoped to a world session', function () {
    [$user, $world, $worldUser, $assistant] = setUpWorldSessionAssistant();
    $session = WorldSession::factory()->for($worldUser)->create();

    $response = $this->actingAs($user)->postJson(route('conversations.store', $assistant), [
        'worldId' => $world->id,
        'worldSessionId' => $session->id,
    ])->assertCreated();

    $conversation = Conversation::findOrFail($response->json('id'));
    expect($conversation->world_session_id)->toBe($session->id);
});

it('finds the same session-scoped conversation on a repeat store call', function () {
    [$user, $world, $worldUser, $assistant] = setUpWorldSessionAssistant();
    $session = WorldSession::factory()->for($worldUser)->create();

    $first = $this->actingAs($user)->postJson(route('conversations.store', $assistant), [
        'worldId' => $world->id,
        'worldSessionId' => $session->id,
    ])->assertCreated();

    $second = $this->actingAs($user)->postJson(route('conversations.store', $assistant), [
        'worldId' => $world->id,
        'worldSessionId' => $session->id,
    ])->assertCreated();

    expect($second->json('id'))->toBe($first->json('id'));
});

it('creates a separate conversation for a different session with the same resident', function () {
    [$user, $world, $worldUser, $assistant] = setUpWorldSessionAssistant();
    $sessionOne = WorldSession::factory()->for($worldUser)->create();
    $sessionTwo = WorldSession::factory()->for($worldUser)->create();

    $first = $this->actingAs($user)->postJson(route('conversations.store', $assistant), [
        'worldId' => $world->id,
        'worldSessionId' => $sessionOne->id,
    ])->assertCreated();

    $second = $this->actingAs($user)->postJson(route('conversations.store', $assistant), [
        'worldId' => $world->id,
        'worldSessionId' => $sessionTwo->id,
    ])->assertCreated();

    expect($second->json('id'))->not->toBe($first->json('id'));
});

it('filters the conversations index by worldSessionId', function () {
    [$user, $world, $worldUser, $assistant] = setUpWorldSessionAssistant();
    $sessionOne = WorldSession::factory()->for($worldUser)->create();
    $sessionTwo = WorldSession::factory()->for($worldUser)->create();

    $assistantUser = AssistantUser::where('assistant_id', $assistant->id)->where('user_id', $user->id)->firstOrFail();
    $conversationOne = Conversation::factory()->for($assistantUser)->forWorldSession($sessionOne)->create();
    Conversation::factory()->for($assistantUser)->forWorldSession($sessionTwo)->create();
    Conversation::factory()->for($assistantUser)->create();

    $response = $this->actingAs($user)->getJson(route('conversations.index', ['assistant' => $assistant->id, 'worldSessionId' => $sessionOne->id]))
        ->assertSuccessful();

    $response->assertJsonCount(1)->assertJsonPath('0.id', $conversationOne->id);
});
