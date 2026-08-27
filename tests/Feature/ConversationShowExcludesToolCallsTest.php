<?php

use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tool_call messages never reach the conversation message listing', function () {
    $user = User::factory()->create();
    $assistant = Assistant::factory()->create();
    $assistantUser = AssistantUser::factory()->create([
        'user_id' => $user->id,
        'assistant_id' => $assistant->id,
    ]);
    $conversation = Conversation::factory()->create(['assistant_user_id' => $assistantUser->id]);

    $conversation->messages()->create(['role' => 'user', 'content' => 'What time is it?']);
    $conversation->messages()->create([
        'role' => 'tool_call',
        'tool_calls' => [['id' => 'call_1', 'name' => 'get_current_datetime', 'arguments' => [], 'result' => ['datetime' => 'now'], 'error' => null]],
    ]);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'It is now.']);

    $response = $this->actingAs($user)->getJson(
        route('conversations.show', ['assistant' => $assistant->id, 'id' => $conversation->id]),
    );

    $response->assertSuccessful();
    $roles = collect($response->json('messages'))->pluck('role');

    expect($roles)->not->toContain('tool_call');
    expect($roles)->toContain('user');
    expect($roles)->toContain('assistant');
});
