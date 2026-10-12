<?php

use App\Actions\BuildSentimentsPrompt;
use App\Enums\TurnMode;
use App\Events\Quests\ResidentSentimentsChanged;
use App\Models\Conversation;
use App\Models\Sentiment;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Services\AgentLoop\Tools\AdjustSentimentsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function sentimentsPositions(WorldResident $resident): array
{
    return ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]];
}

it('tells the resident how they feel about the user in each of the world\'s sentiments, starting neutral, and lets them change it in character', function () {
    $scenario = worldStateScenario(fakeReply: false);
    [, , $conversation, , $resident, $session] = $scenario;
    $resident->world->update(['sentiment_definitions' => [
        ['name' => 'awe', 'description' => '-10 is finding them utterly ordinary; 10 is being dazzled by them.'],
        ['name' => 'debt', 'description' => '-10 is them owing you everything; 10 is you owing them everything.'],
    ]]);
    $conversation->update(['world_session_id' => $session->id]);
    fakeTurn(toolCallResponse('feel_1', 'adjust_sentiments', ['awe' => 2, 'debt' => 1.5, 'reason' => 'They saved me.']), finalAnswerResponse('You are amazing.'));

    sendWorldMessage($this, $scenario, sentimentsPositions($resident))->assertOk();

    $tools = collect(Http::recorded()[0][0]['tools']);
    expect(promptOfRequest())->toContain('Everyone starts at 0, a neutral feeling either way.')
        ->toContain('- awe: -10 is finding them utterly ordinary; 10 is being dazzled by them.')
        ->toContain('- debt: -10 is them owing you everything; 10 is you owing them everything.')
        ->toContain('Right now: awe 0, debt 0. Read each value against its scale')
        ->toContain('Keep the numbers to yourself')
        ->toContain('call adjust_sentiments in that same reply')
        ->and(array_keys($tools->firstWhere('function.name', 'adjust_sentiments')['function']['parameters']['properties']))->toBe(['awe', 'debt', 'reason'])
        ->and(Sentiment::inSession($session, $resident)->scores())->toBe(['awe' => 2.0, 'debt' => 1.5]);
});

it('lets the user read and set the numbers out of character', function () {
    $scenario = worldStateScenario(fakeReply: false);
    [, , $conversation, , $resident, $session] = $scenario;
    worldSentiments($resident->world);
    $conversation->update(['world_session_id' => $session->id]);
    fakeTurn(toolCallResponse('feel_1', 'adjust_sentiments', ['romance' => 8, 'reason' => 'The user asked for romance 8.']), finalAnswerResponse('Done.'));

    sendWorldMessage($this, $scenario, sentimentsPositions($resident), ['message' => ['content' => '[OOC: set your romance to 8]']])->assertOk();

    expect(promptOfRequest())->toContain('tell them the numbers plainly')->not->toContain('Keep the numbers to yourself')
        ->and(Sentiment::inSession($session, $resident)->scores()['romance'])->toBe(8.0);
});

it('gives a world with no sentiments no sentiments prompt and no tool to change them', function () {
    $scenario = worldStateScenario(fakeReply: false);
    [, , $conversation, , $resident, $session] = $scenario;
    $conversation->update(['world_session_id' => $session->id]);
    fakeTurn(finalAnswerResponse('Hello.'));

    sendWorldMessage($this, $scenario, sentimentsPositions($resident))->assertOk();

    expect(promptOfRequest())->not->toContain('How you feel about the user right now')
        ->and(collect(Http::recorded()[0][0]['tools'])->pluck('function.name'))->not->toContain('adjust_sentiments');
});

it('moves a sentiment at most 3 points at a time in character, never past 10 either way, and ignores names the world lacks', function () {
    [, , , , $resident, $session] = worldStateScenario(fakeReply: false);
    worldSentiments($resident->world);
    $sentiment = Sentiment::inSession($session, $resident);
    $sentiment->update(['values' => ['trust' => 9, 'liking' => -9]]);

    (new AdjustSentimentsTool($sentiment, TurnMode::InCharacter))->handle(['romance' => 7, 'trust' => 3, 'liking' => -3, 'awe' => 2, 'reason' => 'A lot happened.']);

    expect($sentiment->fresh()->scores())->toBe(['romance' => 3.0, 'trust' => 10.0, 'liking' => -10.0])
        ->and($sentiment->fresh()->values)->not->toHaveKey('awe')
        ->and((new AdjustSentimentsTool($sentiment, TurnMode::InCharacter))->handle(['reason' => 'Nothing.'])['status'])->toBe('unchanged');
});

it('keeps sentiments to their session', function () {
    [, , , , $resident, $session] = worldStateScenario(fakeReply: false);
    worldSentiments($resident->world);
    $other = WorldSession::factory()->create(['world_user_id' => $session->world_user_id, 'region_id' => $session->region_id]);
    Sentiment::inSession($session, $resident)->adjust(['liking' => 5]);

    expect(Sentiment::inSession($other, $resident)->scores()['liking'])->toBe(0.0);
});

it('leaves sentiments out between residents', function () {
    [, , , , $resident, $session] = worldStateScenario(fakeReply: false);
    worldSentiments($resident->world);

    expect(app(BuildSentimentsPrompt::class)->handle(Sentiment::inSession($session, $resident), TurnMode::BetweenResidents))->toBeNull();
});

it('gives a chat outside any world the assistant\'s sentiments and lets them change them, without touching quests', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['sentiment_definitions' => [
        ['name' => 'warmth', 'description' => '-10 is ice cold toward them; 10 is glowing whenever they speak.'],
    ]]);
    Event::fake([ResidentSentimentsChanged::class]);
    fakeTurn(toolCallResponse('feel_1', 'adjust_sentiments', ['warmth' => 2, 'reason' => 'They remembered my birthday.']), finalAnswerResponse('Thank you.'));

    $this->actingAs($user)->postJson(route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]), ['message' => ['content' => 'Happy birthday!']])->assertOk();

    expect(promptOfRequest())->toContain('- warmth: -10 is ice cold toward them; 10 is glowing whenever they speak.')
        ->toContain('Right now: warmth 0.')
        ->and(collect(Http::recorded()[0][0]['tools'])->pluck('function.name'))->toContain('adjust_sentiments')
        ->and(Sentiment::of($conversation)->scores())->toBe(['warmth' => 2.0]);
    Event::assertNotDispatched(ResidentSentimentsChanged::class);
});

it('keeps a chat\'s sentiments to that conversation', function () {
    [, $assistant, $conversation] = setUpAgentAssistant('assistant', ['sentiment_definitions' => [['name' => 'warmth', 'description' => 'How warm.']]]);
    $other = Conversation::factory()->forAssistantUser($conversation->assistantUser())->create();
    Sentiment::of($conversation)->adjust(['warmth' => 5]);

    expect(Sentiment::of($other)->scores())->toBe(['warmth' => 0.0]);
});

it('gives a chat with an assistant that has no sentiments no prompt, no tool and no scores', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant');
    fakeTurn(finalAnswerResponse('Hello.'));

    $this->actingAs($user)->postJson(route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]), ['message' => ['content' => 'Hi']])->assertOk();

    expect(promptOfRequest())->not->toContain('How you feel about the user right now')
        ->and(Sentiment::where('conversation_id', $conversation->id)->exists())->toBeFalse();
});

it('uses the world\'s sentiments in a world session, even when the assistant has its own', function () {
    $scenario = worldStateScenario(fakeReply: false);
    [, $assistant, $conversation, , $resident, $session] = $scenario;
    $assistant->update(['sentiment_definitions' => [['name' => 'warmth', 'description' => 'How warm.']]]);
    worldSentiments($resident->world, ['trust']);
    $conversation->update(['world_session_id' => $session->id]);
    fakeTurn(finalAnswerResponse('Hello.'));

    sendWorldMessage($this, $scenario, sentimentsPositions($resident))->assertOk();

    expect(promptOfRequest())->toContain('Right now: trust 0.')->not->toContain('- warmth: How warm.');
});

it('gives a Discord chat the assistant\'s sentiments and lets them change them', function () {
    [$user, $assistant] = setUpAgentAssistant('assistant', ['sentiment_definitions' => [['name' => 'warmth', 'description' => 'How warm.']]]);
    fakeTurn(toolCallResponse('feel_1', 'adjust_sentiments', ['warmth' => 1, 'reason' => 'Kind words.']), finalAnswerResponse('Aw, thanks.'));

    $this->actingAs($user)->postJson(route('conversations.sendDiscordMessage', $assistant), ['channel_id' => 'chan-1', 'content' => 'You are lovely.'])->assertOk();

    $conversation = Conversation::where('discord_channel_id', 'chan-1')->firstOrFail();
    expect(promptOfRequest())->toContain('Right now: warmth 0.')->toContain('Keep the numbers to yourself')
        ->and(collect(Http::recorded()[0][0]['tools'])->pluck('function.name')->all())->toBe(['adjust_sentiments'])
        ->and(Sentiment::of($conversation)->scores())->toBe(['warmth' => 1.0]);
});

it('lets the user read the numbers out of character on Discord', function () {
    [$user, $assistant] = setUpAgentAssistant('assistant', ['sentiment_definitions' => [['name' => 'warmth', 'description' => 'How warm.']]]);
    fakeTurn(finalAnswerResponse('Warmth 0.'));

    $this->actingAs($user)->postJson(route('conversations.sendDiscordMessage', $assistant), ['channel_id' => 'chan-1', 'content' => '[OOC: how warm are you?]'])->assertOk();

    expect(promptOfRequest())->toContain('tell them the numbers plainly');
});
