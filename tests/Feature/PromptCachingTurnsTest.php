<?php

use App\Models\AiModel;
use App\Models\AssistantUser;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function chatModelOf(User $user): AiModel
{
    return AiModel::findOrFail(Settings::where('user_id', $user->id)->firstOrFail()->data['ai_model_id']);
}

/**
 * @return list<array<string, mixed>>
 */
function messagesOfRequest(int $index): array
{
    return Http::recorded()[$index][0]['messages'];
}

it('sends two web turns that match from the start through the first turn\'s messages, under the conversation key', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    chatModelOf($user)->update(['conversation_key_field' => 'session_id']);
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hello!'))]);
    $send = fn (string $content) => $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => $content]],
    )->assertOk();

    $send('Hi there.');
    $send('How are you?');

    $first = messagesOfRequest(0);
    $second = messagesOfRequest(1);
    expect($second[0])->toBe($first[0])
        ->and($second[1])->toBe(['role' => 'user', 'content' => 'Hi there.'])
        ->and($second[2])->toBe(['role' => 'assistant', 'content' => 'Hello!'])
        ->and(Http::recorded()[0][0]['session_id'])->toBe($conversation->providerSessionKey())
        ->and(Http::recorded()[1][0]['session_id'])->toBe($conversation->providerSessionKey())
        ->and(Http::recorded()[1][0]['tools'])->toBe(Http::recorded()[0][0]['tools']);
});

it('sends at most 100 previous messages on Discord, however long the channel history is', function () {
    [$user, $assistant] = setUpAgentAssistant('assistant');
    $assistantUser = AssistantUser::where('user_id', $user->id)->where('assistant_id', $assistant->id)->firstOrFail();
    $conversation = $assistantUser->conversations()->create(['title' => 'Discord', 'discord_channel_id' => 'chan-150']);
    foreach (range(1, 149) as $number) {
        $conversation->messages()->create(['role' => $number % 2 === 1 ? 'user' : 'assistant', 'content' => "line {$number}"]);
    }
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hello!'))]);

    $this->actingAs($user)->postJson(route('conversations.sendDiscordMessage', $assistant), [
        'channel_id' => 'chan-150',
        'content' => 'Anyone here?',
    ])->assertSuccessful();

    $messages = messagesOfRequest(0);
    expect($messages)->toHaveCount(1 + 99 + 1)
        ->and($messages[1]['content'])->toBe('line 51')
        ->and(end($messages)['content'])->toBe('Anyone here?');
});

it('puts the note of a scene change under CURRENT STATE instead of a trailing system message', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    configureImageGenModel($user, $assistant, 'https://fake-image.test/generate');
    Queue::fake();
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('The scenery shifts.'))]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => '/change-background a futuristic park']],
    )->assertSuccessful();

    $messages = messagesOfRequest(0);
    expect(collect($messages)->where('role', 'system'))->toHaveCount(1)
        ->and($messages[0]['role'])->toBe('system')
        ->and(end($messages)['role'])->toBe('user')
        ->and(end($messages)['content'])->toContain("# CURRENT STATE\n[The scene has just moved to a new location: \"a futuristic park\"]")
        ->toContain('/change-background a futuristic park');
});
