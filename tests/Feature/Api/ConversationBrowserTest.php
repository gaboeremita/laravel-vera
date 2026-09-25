<?php

use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists the user\'s chats and their assistants\' conversations with each other', function () {
    $user = User::factory()->create();
    $yinlin = Assistant::factory()->create(['name' => 'Yinlin']);
    $vera = Assistant::factory()->create(['name' => 'Vera']);
    $user->assistants()->attach([$yinlin->id, $vera->id]);
    $theirs = Conversation::factory()->betweenAssistants($yinlin, $vera)->create();
    $theirs->messages()->create(['role' => 'assistant', 'content' => 'Hi.', 'speaker_type' => $yinlin->getMorphClass(), 'speaker_id' => $yinlin->id]);
    $chat = Conversation::factory()->forAssistantUser(AssistantUser::where('user_id', $user->id)->where('assistant_id', $vera->id)->first())->create();
    $chat->messages()->create(['role' => 'user', 'content' => 'Hello']);
    $strangers = Conversation::factory()->betweenAssistants(Assistant::factory()->create(), Assistant::factory()->create())->create();
    $strangers->messages()->create(['role' => 'assistant', 'content' => 'Private.']);

    $ids = $this->actingAs($user)->getJson(route('conversation-browser.index'))->assertSuccessful()->json('data.*.id');

    expect($ids)->toEqualCanonicalizing([$theirs->id, $chat->id]);
});

it('shows who said each line', function () {
    $user = User::factory()->create(['name' => 'Gabriel']);
    $vera = Assistant::factory()->create(['name' => 'Vera']);
    $chat = Conversation::factory()->forAssistantUser(AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $vera->id]))->create();
    $chat->messages()->create(['role' => 'user', 'content' => 'Hello']);
    $chat->messages()->create(['role' => 'assistant', 'content' => 'Hi there.']);

    $this->actingAs($user)->getJson(route('conversation-browser.show', ['conversation' => $chat->id]))
        ->assertSuccessful()
        ->assertJsonPath('data.owner.name', 'Gabriel')
        ->assertJsonPath('data.counterpart.name', 'Vera')
        ->assertJsonPath('data.messages.0.speaker.name', 'Gabriel')
        ->assertJsonPath('data.messages.1.speaker.name', 'Vera');
});

it('keeps conversations the user cannot see out of reach', function () {
    $conversation = Conversation::factory()->betweenAssistants(Assistant::factory()->create(), Assistant::factory()->create())->create();

    $this->actingAs(User::factory()->create())->getJson(route('conversation-browser.show', ['conversation' => $conversation->id]))->assertNotFound();
});
