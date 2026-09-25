<?php

use App\Actions\DeleteAssistantAssets;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('makes the user the owner and the assistant the counterpart of their chats', function () {
    $assistantUser = AssistantUser::factory()->create();

    $conversation = $assistantUser->conversations()->create(['title' => 'Hello']);

    expect($conversation->owner->is($assistantUser->user))->toBeTrue()
        ->and($conversation->counterpart->is($assistantUser->assistant))->toBeTrue()
        ->and($conversation->assistantUser()->is($assistantUser))->toBeTrue();
});

it('lists only the chats with that one assistant', function () {
    $assistantUser = AssistantUser::factory()->create();
    $otherAssistant = AssistantUser::factory()->create(['user_id' => $assistantUser->user_id]);
    $mine = Conversation::factory()->forAssistantUser($assistantUser)->create();
    Conversation::factory()->forAssistantUser($otherAssistant)->create();

    expect($assistantUser->conversations()->pluck('id')->all())->toBe([$mine->id]);
});

it('has no user and assistant pair between two assistants', function () {
    $conversation = Conversation::factory()->betweenAssistants(Assistant::factory()->create(), Assistant::factory()->create())->create();

    expect($conversation->assistantUser())->toBeNull();
});

it('shows a user their own chats and conversations between their assistants', function () {
    $user = User::factory()->create();
    $yinlin = Assistant::factory()->create();
    $vera = Assistant::factory()->create();
    $stranger = Assistant::factory()->create();
    $user->assistants()->attach([$yinlin->id, $vera->id]);
    $theirs = Conversation::factory()->betweenAssistants($yinlin, $vera)->create();
    $chat = Conversation::factory()->forAssistantUser(AssistantUser::where('user_id', $user->id)->where('assistant_id', $yinlin->id)->first())->create();
    $unrelated = Conversation::factory()->betweenAssistants($stranger, Assistant::factory()->create())->create();

    expect($theirs->isVisibleTo($user))->toBeTrue()
        ->and($chat->isVisibleTo($user))->toBeTrue()
        ->and($unrelated->isVisibleTo($user))->toBeFalse()
        ->and(Conversation::visibleTo($user)->pluck('id')->sort()->values()->all())->toBe(collect([$theirs->id, $chat->id])->sort()->values()->all());
});

it('finds a conversation between two parties whichever of them started it', function () {
    $yinlin = Assistant::factory()->create();
    $vera = Assistant::factory()->create();
    $conversation = Conversation::factory()->betweenAssistants($vera, $yinlin)->create();

    expect(Conversation::between($yinlin, $vera)->first()?->is($conversation))->toBeTrue();
});

it('deletes the conversations of a deleted assistant', function () {
    $yinlin = Assistant::factory()->create();
    $vera = Assistant::factory()->create();
    $between = Conversation::factory()->betweenAssistants($vera, $yinlin)->create();
    $chat = Conversation::factory()->forAssistantUser(AssistantUser::factory()->create(['assistant_id' => $yinlin->id]))->create();

    app(DeleteAssistantAssets::class)->handle($yinlin);

    expect(Conversation::whereKey([$between->id, $chat->id])->exists())->toBeFalse();
});
