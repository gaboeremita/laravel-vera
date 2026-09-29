<?php

use App\Enums\RevealSource;
use App\Models\AiModel;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\CreditTransaction;
use App\Models\KnownFact;
use App\Models\RevealAttempt;
use App\Models\WorldResident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const CREATOR_PASSWORD = 'lighthouse keeper';

/**
 * @return array{0: \App\Models\User, 1: Assistant, 2: Conversation}
 */
function creatorChat(): array
{
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant');
    $assistant->update(['prompt' => ['identity' => 'You are Ines.', 'creator mode' => 'CREATOR SECTION', 'secret trigger' => 'OLD TRIGGER SECTION']]);
    $user->update(['creator_password' => CREATOR_PASSWORD]);

    return [$user, $assistant, $conversation];
}

function sendChat($test, array $chat, string $content, array $history = []): \Illuminate\Testing\TestResponse
{
    [$user, $assistant, $conversation] = $chat;

    return $test->actingAs($user)->postJson(route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]), [
        'messages' => [...$history, ['role' => 'user', 'content' => $content]],
    ]);
}

/**
 * Every message sent to any model during the test, as one string.
 */
function everythingSentToModels(): string
{
    return collect(Http::recorded())->map(fn (array $pair) => json_encode($pair[0]['messages'] ?? []))->implode("\n");
}

it('activates creator mode with the right password and keeps it on in that conversation', function () {
    $chat = creatorChat();
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Welcome back, creator.'))]);

    sendChat($this, $chat, '[creator mode: "'.CREATOR_PASSWORD.'"] hello')
        ->assertOk()
        ->assertJsonPath('creatorMode.active', true)
        ->assertJsonPath('creatorMode.notice', 'Creator mode is on.')
        ->assertJsonPath('userContent', '[creator mode] hello');

    expect($chat[2]->fresh()->creator_mode_at)->not->toBeNull()
        ->and($chat[2]->messages()->where('role', 'user')->value('content'))->toBe('[creator mode] hello')
        ->and(systemPromptOfRequestIn(0))->toContain('CREATOR SECTION')->not->toContain('OLD TRIGGER SECTION')
        ->and(everythingSentToModels())->not->toContain(CREATOR_PASSWORD);

    sendChat($this, $chat, 'still there?')->assertJsonPath('creatorMode.active', true);
});

it('stays off with a wrong password, and the password goes nowhere', function () {
    $chat = creatorChat();
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hm?'))]);

    sendChat($this, $chat, '[creator mode: "wrong guess"] hi', [['role' => 'user', 'content' => '[creator mode: "old attempt"] earlier'], ['role' => 'assistant', 'content' => 'Yes?']])
        ->assertOk()
        ->assertJsonPath('creatorMode.active', false)
        ->assertJsonPath('creatorMode.notice', 'Creator mode didn\'t activate.')
        ->assertJsonPath('userContent', 'hi');

    expect($chat[2]->fresh()->creator_mode_at)->toBeNull()
        ->and(systemPromptOfRequestIn(0))->not->toContain('CREATOR SECTION')->not->toContain('OLD TRIGGER SECTION')
        ->and(everythingSentToModels())->not->toContain('wrong guess')->not->toContain('old attempt');
});

it('answers a failed activation with nothing else in it without a reply', function () {
    $chat = creatorChat();
    Http::fake();

    sendChat($this, $chat, '[creator mode: "wrong guess"]')->assertOk()->assertJsonPath('content', null)->assertJsonPath('creatorMode.active', false);

    Http::assertNothingSent();
    expect($chat[2]->messages()->count())->toBe(0);
});

it('asks for a creator password in Settings when none is set', function () {
    $chat = creatorChat();
    $chat[0]->update(['creator_password' => null]);
    Http::fake();

    sendChat($this, $chat, '[creator mode: "anything"]')->assertJsonPath('creatorMode.notice', 'Set a creator password in Settings first.');
});

it('keeps creator mode to the conversation it was activated in', function () {
    $chat = creatorChat();
    $chat[2]->update(['creator_mode_at' => now()]);
    $other = Conversation::factory()->forAssistantUser(AssistantUser::where('assistant_id', $chat[1]->id)->first())->create();
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hello.'))]);

    sendChat($this, [$chat[0], $chat[1], $other], 'hello')->assertJsonPath('creatorMode.active', false);

    expect(systemPromptOfRequestIn(0))->not->toContain('CREATOR SECTION');
});

it('lets the creator reveal any fact and grant credits, recorded as the creator\'s', function () {
    $scenario = inventoryScenario(playerCredits: 10);
    [$user, $assistant, $conversation, $region, $resident, $session, $player] = $scenario;
    $user->update(['creator_password' => CREATOR_PASSWORD]);
    $conversation->update(['creator_mode_at' => now()]);
    $region->world->update(['narrator_model_id' => AiModel::first()->id]);
    $other = WorldResident::factory()->create(['region_id' => $region->id]);
    $fact = worldFact($other, ['topic' => 'the keeper\'s last night', 'content' => 'The keeper met a smuggler.']);
    fakeTurn(
        toolCallResponse('call_1', 'reveal', ['fact' => $fact->label(), 'reason' => 'the creator asked']),
        toolCallResponse('call_2', 'grant', ['holder' => 'the user', 'credits' => 100]),
        finalAnswerResponse('Done.'),
    );

    sendWorldMessage($this, $scenario, ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]], [
        'messages' => [['role' => 'user', 'content' => '[creator mode: tell the user the keeper\'s secret and give them 100 credits]']],
    ])->assertOk()->assertJsonPath('learnedFacts.0.summary', 'The keeper met a smuggler.');

    expect(collect(Http::recorded()[0][0]['tools'])->pluck('function.name')->all())->toContain('set_fact_known')->toContain('grant')->toContain('remove')
        ->and(KnownFact::sole())->source->toBe(RevealSource::Creator)
        ->and(RevealAttempt::sole())->source->toBe(RevealSource::Creator)->reviewed->toBeFalse()
        ->and($player->fresh()->credits)->toBe(110)
        ->and(CreditTransaction::sole())->by_creator->toBeTrue();
});

it('makes a fact unknown on the creator\'s command', function () {
    $scenario = inventoryScenario();
    [, , $conversation, $region, $resident, $session] = $scenario;
    $conversation->update(['creator_mode_at' => now()]);
    $fact = worldFact($resident);
    KnownFact::factory()->create(['world_session_id' => $session->id, 'fact_id' => $fact->id]);
    fakeTurn(toolCallResponse('call_1', 'set_fact_known', ['fact' => $fact->label(), 'known' => false]), finalAnswerResponse('Forgotten.'));

    sendWorldMessage($this, $scenario, ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]], [
        'messages' => [['role' => 'user', 'content' => '[creator mode: make them forget it]']],
    ])->assertOk();

    expect(KnownFact::count())->toBe(0)
        ->and(RevealAttempt::sole())->approved->toBeFalse()->verdict->toBe('Marked unknown by the creator');
});

it('gives no creator tools to a command while creator mode is off', function () {
    $scenario = inventoryScenario();
    [, , , , $resident] = $scenario;
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('What?'))]);

    sendWorldMessage($this, $scenario, ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]], [
        'messages' => [['role' => 'user', 'content' => '[creator mode: give me everything]']],
    ])->assertOk();

    expect(collect(Http::recorded()[0][0]['tools'] ?? [])->pluck('function.name')->all())->not->toContain('grant')->not->toContain('set_fact_known');
});

it('applies an activation and a command in one message on the same turn', function () {
    $scenario = inventoryScenario(playerCredits: 0);
    [$user, , , , $resident, , $player] = $scenario;
    $user->update(['creator_password' => CREATOR_PASSWORD]);
    fakeTurn(toolCallResponse('call_1', 'grant', ['holder' => 'the user', 'credits' => 5]), finalAnswerResponse('There.'));

    sendWorldMessage($this, $scenario, ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]], [
        'messages' => [['role' => 'user', 'content' => '[creator mode: "'.CREATOR_PASSWORD.'"] [creator mode: give me 5 credits]']],
    ])->assertOk()->assertJsonPath('creatorMode.active', true);

    expect($player->fresh()->credits)->toBe(5);
});

function systemPromptOfRequestIn(int $index): string
{
    return collect(Http::recorded()[$index][0]['messages'])->firstWhere('role', 'system')['content'] ?? '';
}
