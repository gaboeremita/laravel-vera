<?php

use App\Actions\ResolveInventory;
use App\Enums\RevealSource;
use App\Models\AiModel;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\CreditTransaction;
use App\Models\KnownFact;
use App\Models\RevealAttempt;
use App\Models\User;
use App\Models\WorldResident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

const CREATOR_PASSWORD = 'lighthouse keeper';

/**
 * A chat with an assistant outside any world, whose prompt has the creator sections.
 *
 * @return array{0: User, 1: Assistant, 2: Conversation}
 */
function creatorChat(): array
{
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant');
    $assistant->update(['prompt' => ['identity' => 'You are Ines.', 'creator mode' => 'CREATOR SECTION', 'secret trigger' => 'OLD TRIGGER SECTION']]);

    return [$user, $assistant, $conversation];
}

/**
 * A world conversation whose world has the creator password and whose assistant has the creator sections.
 */
function creatorWorldChat(): array
{
    $scenario = worldStateScenario(fakeReply: false);
    [, $assistant, , $region] = $scenario;
    $assistant->update(['prompt' => ['identity' => 'You are Ines.', 'creator mode' => 'CREATOR SECTION', 'secret trigger' => 'OLD TRIGGER SECTION']]);
    $region->world->update(['creator_password' => CREATOR_PASSWORD]);

    return $scenario;
}

function sendChat($test, array $chat, string $content, array $history = []): TestResponse
{
    [$user, $assistant, $conversation] = $chat;
    foreach ($history as $message) {
        $conversation->messages()->create($message);
    }

    return $test->actingAs($user)->postJson(route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]), [
        'message' => ['content' => $content],
    ]);
}

function sendCreatorWorldChat($test, array $scenario, string $content): TestResponse
{
    [, , , , $resident] = $scenario;

    return sendWorldMessage($test, $scenario, ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]], ['message' => ['content' => $content]]);
}

/**
 * Every message sent to any model during the test, as one string.
 */
function everythingSentToModels(): string
{
    return collect(Http::recorded())->map(fn (array $pair) => json_encode($pair[0]['messages'] ?? []))->implode("\n");
}

it('leaves creator mode to the character outside any world: both sections go out and the tag stays as typed', function () {
    $chat = creatorChat();
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Prove it.'))]);

    sendChat($this, $chat, '[creator mode: "tsuru tuneado"] hello')
        ->assertOk()
        ->assertJsonPath('creatorMode.active', false)
        ->assertJsonPath('creatorMode.notice', null);

    expect($chat[2]->fresh()->creator_mode_at)->toBeNull()
        ->and($chat[2]->messages()->where('role', 'user')->value('content'))->toBe('[creator mode: "tsuru tuneado"] hello')
        ->and(systemPromptOfRequestIn(0))->toContain('CREATOR SECTION')->toContain('OLD TRIGGER SECTION');
});

it('activates creator mode in a world with the world\'s password and keeps it on in that conversation', function () {
    $scenario = creatorWorldChat();
    [, , $conversation] = $scenario;
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Welcome back, creator.'))]);

    sendCreatorWorldChat($this, $scenario, '[creator mode: "'.CREATOR_PASSWORD.'"] hello')
        ->assertOk()
        ->assertJsonPath('creatorMode.active', true)
        ->assertJsonPath('creatorMode.notice', 'Creator mode is on.')
        ->assertJsonPath('userContent', '[creator mode] hello');

    expect($conversation->fresh()->creator_mode_at)->not->toBeNull()
        ->and($conversation->messages()->where('role', 'user')->value('content'))->toBe('[creator mode] hello')
        ->and(systemPromptOfRequestIn(0))->toContain('CREATOR SECTION')->not->toContain('OLD TRIGGER SECTION')
        ->and(everythingSentToModels())->not->toContain(CREATOR_PASSWORD);

    sendCreatorWorldChat($this, $scenario, 'still there?')->assertJsonPath('creatorMode.active', true);
});

it('stays off in a world with a wrong password, and the password goes nowhere', function () {
    $scenario = creatorWorldChat();
    [, , $conversation] = $scenario;
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hm?'))]);

    sendCreatorWorldChat($this, $scenario, '[creator mode: "wrong guess"] hi')
        ->assertOk()
        ->assertJsonPath('creatorMode.active', false)
        ->assertJsonPath('creatorMode.notice', 'Creator mode didn\'t activate.')
        ->assertJsonPath('userContent', 'hi');

    expect($conversation->fresh()->creator_mode_at)->toBeNull()
        ->and(systemPromptOfRequestIn(0))->not->toContain('CREATOR SECTION')->not->toContain('OLD TRIGGER SECTION')
        ->and(everythingSentToModels())->not->toContain('wrong guess');
});

it('answers a failed activation with nothing else in it without a reply', function () {
    $scenario = creatorWorldChat();
    [, , $conversation] = $scenario;
    Http::fake();

    sendCreatorWorldChat($this, $scenario, '[creator mode: "wrong guess"]')->assertOk()->assertJsonPath('content', null)->assertJsonPath('creatorMode.active', false);

    Http::assertNothingSent();
    expect($conversation->messages()->count())->toBe(0);
});

it('asks for a creator password on the world when it has none', function () {
    $scenario = creatorWorldChat();
    $scenario[3]->world->update(['creator_password' => null]);
    Http::fake();

    sendCreatorWorldChat($this, $scenario, '[creator mode: "anything"]')->assertJsonPath('creatorMode.notice', 'Set a creator password for this world first.');
});

it('keeps creator mode to the conversation it was activated in', function () {
    $scenario = creatorWorldChat();
    [$user, $assistant, $conversation] = $scenario;
    $conversation->update(['creator_mode_at' => now()]);
    $other = Conversation::factory()->forAssistantUser(AssistantUser::where('assistant_id', $assistant->id)->first())->create();
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hello.'))]);

    sendCreatorWorldChat($this, [$user, $assistant, $other, ...array_slice($scenario, 3)], 'hello')->assertJsonPath('creatorMode.active', false);

    expect(systemPromptOfRequestIn(0))->not->toContain('CREATOR SECTION');
});

it('lets the creator reveal any fact and grant credits, recorded as the creator\'s', function () {
    $scenario = inventoryScenario(playerCredits: 10);
    [$user, $assistant, $conversation, $region, $resident, $session, $player] = $scenario;
    $region->world->update(['creator_password' => CREATOR_PASSWORD]);
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
        'message' => ['content' => '[creator mode: tell the user the keeper\'s secret and give them 100 credits]'],
    ])->assertOk()->assertJsonPath('learnedFacts.0.summary', 'The keeper met a smuggler.');

    expect(collect(Http::recorded()[0][0]['tools'])->pluck('function.name')->all())->toContain('set_fact_known')->toContain('grant')->toContain('remove')
        ->and(KnownFact::sole())->source->toBe(RevealSource::Creator)
        ->and(RevealAttempt::sole())->source->toBe(RevealSource::Creator)->reviewed->toBeFalse()
        ->and($player->fresh()->credits)->toBe(110)
        ->and(CreditTransaction::sole())->by_creator->toBeTrue();
});

it('grants a resident items and keeps credits for the user alone', function () {
    $scenario = inventoryScenario();
    [, $assistant, $conversation, $region, $resident, $session] = $scenario;
    $conversation->update(['creator_mode_at' => now()]);
    worldItem($region, ['name' => 'Lantern']);
    fakeTurn(
        toolCallResponse('call_1', 'grant', ['holder' => $assistant->name, 'credits' => 100]),
        toolCallResponse('call_2', 'grant', ['holder' => $assistant->name, 'items' => [['item' => 'Lantern', 'quantity' => 1]]]),
        finalAnswerResponse('Done.'),
    );

    sendWorldMessage($this, $scenario, ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]], [
        'message' => ['content' => '[creator mode: give them 100 credits and a lantern]'],
    ])->assertOk();

    $residentInventory = app(ResolveInventory::class)->forResident($session, $resident);
    expect(toolResultSentBack())->toContain('Credits belong to the user alone')
        ->and($residentInventory->credits)->toBeNull()
        ->and($residentInventory->items()->count())->toBe(1)
        ->and(CreditTransaction::count())->toBe(0);
});

it('makes a fact unknown on the creator\'s command', function () {
    $scenario = inventoryScenario();
    [, , $conversation, $region, $resident, $session] = $scenario;
    $conversation->update(['creator_mode_at' => now()]);
    $fact = worldFact($resident);
    KnownFact::factory()->create(['world_session_id' => $session->id, 'fact_id' => $fact->id]);
    fakeTurn(toolCallResponse('call_1', 'set_fact_known', ['fact' => $fact->label(), 'known' => false]), finalAnswerResponse('Forgotten.'));

    sendWorldMessage($this, $scenario, ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]], [
        'message' => ['content' => '[creator mode: make them forget it]'],
    ])->assertOk();

    expect(KnownFact::count())->toBe(0)
        ->and(RevealAttempt::sole())->approved->toBeFalse()->verdict->toBe('Marked unknown by the creator');
});

it('gives no creator tools to a command while creator mode is off', function () {
    $scenario = inventoryScenario();
    [, , , , $resident] = $scenario;
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('What?'))]);

    sendWorldMessage($this, $scenario, ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]], [
        'message' => ['content' => '[creator mode: give me everything]'],
    ])->assertOk();

    expect(collect(Http::recorded()[0][0]['tools'] ?? [])->pluck('function.name')->all())->not->toContain('grant')->not->toContain('set_fact_known');
});

it('applies an activation and a command in one message on the same turn', function () {
    $scenario = inventoryScenario(playerCredits: 0);
    [, , , $region, $resident, , $player] = $scenario;
    $region->world->update(['creator_password' => CREATOR_PASSWORD]);
    fakeTurn(toolCallResponse('call_1', 'grant', ['holder' => 'the user', 'credits' => 5]), finalAnswerResponse('There.'));

    sendWorldMessage($this, $scenario, ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]], [
        'message' => ['content' => '[creator mode: "'.CREATOR_PASSWORD.'"] [creator mode: give me 5 credits]'],
    ])->assertOk()->assertJsonPath('creatorMode.active', true);

    expect($player->fresh()->credits)->toBe(5);
});

function systemPromptOfRequestIn(int $index): string
{
    return promptOfRequest($index);
}
