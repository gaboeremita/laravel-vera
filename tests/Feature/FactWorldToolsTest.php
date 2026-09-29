<?php

use App\Enums\RevealSource;
use App\Models\AiModel;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\Fact;
use App\Models\FactAcknowledgement;
use App\Models\KnownFact;
use App\Models\RevealAttempt;
use App\Models\Settings;
use App\Models\WorldResident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const KEEPER_SECRET = 'The keeper rowed out to meet a smuggler and never came back.';

/**
 * A world session whose resident keeps the keeper's secret, with the review on the fake model.
 *
 * @return array{0: mixed, 1: Assistant, 2: Conversation, 3: mixed, 4: WorldResident, 5: mixed, 6: mixed, 7: mixed, 8: Fact}
 */
function keeperScenario(): array
{
    $scenario = inventoryScenario();
    [, , , $region, $resident] = $scenario;
    $region->world->update(['narrator_model_id' => AiModel::first()->id]);
    $fact = worldFact($resident, ['topic' => 'the keeper\'s last night', 'content' => KEEPER_SECRET, 'disclosure' => 'only in the chapel']);

    return [...$scenario, $fact];
}

function factChatPositions(array $scenario): array
{
    return ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -3]]];
}

function systemPromptOfRequest(int $index = 0): string
{
    return collect(Http::recorded()[$index][0]['messages'])->firstWhere('role', 'system')['content'] ?? '';
}

/**
 * @return array<int, string>
 */
function toolNamesOfRequest(int $index = 0): array
{
    return collect(Http::recorded()[$index][0]['tools'] ?? [])->pluck('function.name')->all();
}

function verdictResponse(bool $approved, string $verdict): array
{
    return toolCallResponse('verdict_1', 'verdict', ['approved' => $approved, 'verdict' => $verdict]);
}

it('tells a holder the topic and when they share it, never the secret', function () {
    $scenario = keeperScenario();
    fakeTurn(finalAnswerResponse('Ask me another time.'));

    sendWorldMessage($this, $scenario, factChatPositions($scenario))->assertOk();

    expect(systemPromptOfRequest())->toContain('the keeper\'s last night')->toContain('only in the chapel')->not->toContain(KEEPER_SECRET)
        ->and(toolNamesOfRequest())->toContain('reveal');
});

it('reveals the secret when the review approves, and the player learns what was told', function () {
    $scenario = keeperScenario();
    [, , , , $resident, $session, , , $fact] = $scenario;
    fakeTurn(
        toolCallResponse('call_1', 'reveal', ['fact' => 'the keeper\'s last night', 'reason' => 'we are in the chapel and they asked kindly']),
        verdictResponse(true, 'They are in the chapel.'),
        finalAnswerResponse('He rowed out to meet a smuggler, and never came home.'),
        finalAnswerResponse('You heard the keeper rowed out to meet a smuggler and never returned.'),
    );

    sendWorldMessage($this, $scenario, factChatPositions($scenario))
        ->assertOk()
        ->assertJsonPath('learnedFacts.0.topic', 'the keeper\'s last night')
        ->assertJsonPath('learnedFacts.0.summary', 'You heard the keeper rowed out to meet a smuggler and never returned.')
        ->assertJsonPath('learnedFacts.0.sourceName', $resident->assistant->name);

    expect(toolResultSentBack(2))->toContain(KEEPER_SECRET)
        ->and(KnownFact::where('world_session_id', $session->id)->where('fact_id', $fact->id)->first())->source->toBe(RevealSource::InCharacter)
        ->and(RevealAttempt::sole())->reviewed->toBeTrue()->approved->toBeTrue()->reason->toBe('we are in the chapel and they asked kindly')->verdict->toBe('They are in the chapel.');
});

it('keeps the secret when the review rejects it, and logs the attempt', function () {
    $scenario = keeperScenario();
    fakeTurn(
        toolCallResponse('call_1', 'reveal', ['fact' => 'the keeper\'s last night', 'reason' => 'they seem nice']),
        verdictResponse(false, 'They are on the pier, not in the chapel.'),
        finalAnswerResponse('Not here.'),
    );

    sendWorldMessage($this, $scenario, factChatPositions($scenario))->assertOk()->assertJsonPath('learnedFacts', []);

    expect(toolResultSentBack(2))->toContain('right moment')->not->toContain(KEEPER_SECRET)
        ->and(KnownFact::count())->toBe(0)
        ->and(RevealAttempt::sole())->approved->toBeFalse()->verdict->toBe('They are on the pier, not in the chapel.');
});

it('reviews a fact only once per turn, however often the holder tries', function () {
    $scenario = keeperScenario();
    fakeTurn(
        toolCallResponse('call_1', 'reveal', ['fact' => 'the keeper\'s last night', 'reason' => 'they asked']),
        verdictResponse(false, 'Not the chapel.'),
        toolCallResponse('call_2', 'reveal', ['fact' => 'the keeper\'s last night', 'reason' => 'they asked again']),
        finalAnswerResponse('Not here.'),
    );

    sendWorldMessage($this, $scenario, factChatPositions($scenario))->assertOk();

    expect(collect(Http::recorded())->filter(fn (array $pair) => collect($pair[0]['tools'] ?? [])->pluck('function.name')->contains('verdict')))->toHaveCount(1)
        ->and(RevealAttempt::count())->toBe(1);
});

it('approves without review when the world turns the review off', function () {
    $scenario = keeperScenario();
    $scenario[3]->world->update(['review_reveals' => false]);
    fakeTurn(
        toolCallResponse('call_1', 'reveal', ['fact' => 'the keeper\'s last night', 'reason' => 'they asked']),
        finalAnswerResponse('He rowed out.'),
        finalAnswerResponse('You heard the keeper rowed out.'),
    );

    sendWorldMessage($this, $scenario, factChatPositions($scenario))->assertOk();

    expect(RevealAttempt::sole())->reviewed->toBeFalse()->approved->toBeTrue()
        ->and(KnownFact::count())->toBe(1);
});

it('gives the holder the secret and how the player found out when they learned it elsewhere', function () {
    $scenario = keeperScenario();
    [, , , , , $session, , , $fact] = $scenario;
    KnownFact::factory()->fromItem('Torn letter')->create(['world_session_id' => $session->id, 'fact_id' => $fact->id]);
    fakeTurn(finalAnswerResponse('So you read it.'));

    sendWorldMessage($this, $scenario, factChatPositions($scenario))->assertOk();

    expect(systemPromptOfRequest())->toContain(KEEPER_SECRET)->toContain('found out through Torn letter')->toContain('once they bring it up');
});

it('refuses to reveal a fact the resident does not hold', function () {
    $scenario = keeperScenario();
    fakeTurn(
        toolCallResponse('call_1', 'reveal', ['fact' => 'the mayor\'s debts', 'reason' => 'why not']),
        finalAnswerResponse('I know nothing of that.'),
    );

    sendWorldMessage($this, $scenario, factChatPositions($scenario))->assertOk();

    expect(toolResultSentBack())->toContain('You hold no secret')
        ->and(KnownFact::count())->toBe(0)
        ->and(RevealAttempt::count())->toBe(0);
});

it('gives no facts to a resident whose model cannot call tools', function () {
    $scenario = keeperScenario();
    AiModel::query()->update(['supports_tools' => false]);
    $scenario[1]->update(['kind' => 'world_npc']);
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hello.'))]);

    sendWorldMessage($this, $scenario, factChatPositions($scenario))->assertOk();

    expect(systemPromptOfRequest())->not->toContain('the keeper\'s last night');
});

it('tells a relay resident the topic they want, and the secret only once the player knows it', function () {
    $scenario = keeperScenario();
    [, , , $region, $resident, $session, , , $fact] = $scenario;
    $other = WorldResident::factory()->create(['region_id' => $region->id]);
    $fact->update(['world_resident_id' => $other->id]);
    $fact->relays()->attach($resident->id);
    fakeTurn(
        finalAnswerResponse('Have you heard of the keeper?'),
        toolCallResponse('call_1', 'acknowledge', ['fact' => 'the keeper\'s last night']),
        finalAnswerResponse('So that is what happened.'),
    );

    sendWorldMessage($this, $scenario, factChatPositions($scenario))->assertOk();
    expect(systemPromptOfRequest())->toContain('want to find out')->toContain('the keeper\'s last night')->not->toContain(KEEPER_SECRET)
        ->and(toolNamesOfRequest())->not->toContain('acknowledge');

    KnownFact::factory()->create(['world_session_id' => $session->id, 'fact_id' => $fact->id]);

    sendWorldMessage($this, $scenario, factChatPositions($scenario))->assertOk();
    expect(systemPromptOfRequest(1))->toContain(KEEPER_SECRET)->and(toolNamesOfRequest(1))->toContain('acknowledge')
        ->and(systemPromptOfRequest(1))->toContain('The user has learned')
        ->and(FactAcknowledgement::where('world_session_id', $session->id)->where('fact_id', $fact->id)->where('world_resident_id', $resident->id)->exists())->toBeTrue();
});

it('gives holders the secret on out-of-character turns, keeps the OOC text, and reveals without review', function () {
    $scenario = keeperScenario();
    fakeTurn(
        toolCallResponse('call_1', 'reveal', ['fact' => 'the keeper\'s last night', 'reason' => 'the player asked out of character']),
        finalAnswerResponse('[OOC: here is the secret] He rowed out.'),
        finalAnswerResponse('You heard he rowed out.'),
    );

    sendWorldMessage($this, $scenario, factChatPositions($scenario), ['messages' => [['role' => 'user', 'content' => '[OOC: what is your secret about the keeper?]']]])->assertOk();

    expect(systemPromptOfRequest())->toContain(KEEPER_SECRET)
        ->and(collect(Http::recorded()[0][0]['messages'])->last()['content'])->toContain('[OOC: what is your secret')
        ->and(RevealAttempt::sole())->source->toBe(RevealSource::OocTurn)->reviewed->toBeFalse()->approved->toBeTrue();
});

it('gives a holder only the topic in conversations between residents, with no way to reveal', function () {
    [$user, $yinlin, , $region, $first, $session] = worldStateScenario(fakeReply: false);
    $vera = Assistant::factory()->create(['name' => 'Vera', 'mode' => 'agent']);
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $vera->id]);
    Settings::create(['user_id' => $user->id, 'assistant_id' => $vera->id, 'data' => Settings::where('user_id', $user->id)->where('assistant_id', $yinlin->id)->first()->data]);
    $second = $region->residents()->create(['assistant_id' => $vera->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'autonomous']);
    worldFact($second, ['topic' => 'the keeper\'s last night', 'content' => KEEPER_SECRET]);
    $conversation = Conversation::factory()->betweenAssistants($yinlin, $vera)->forWorldSession($session)->create(['resumed_at' => now()->subMinute()]);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'What happened to the keeper?', 'speaker_type' => $yinlin->getMorphClass(), 'speaker_id' => $yinlin->id])->forceFill(['created_at' => now()->subMinute()])->save();
    fakeTurn(finalAnswerResponse('I would rather not say.'));

    $this->actingAs($user)->postJson(route('worlds.sessions.conversations.turns.store', [$region->world_id, $session->id, $conversation->id]), [
        'positions' => ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$first->id => ['x' => 5, 'y' => 0, 'z' => -3], $second->id => ['x' => 6, 'y' => 0, 'z' => -3]]],
    ])->assertSuccessful();

    expect(systemPromptOfRequest())->toContain('the keeper\'s last night')->not->toContain(KEEPER_SECRET)
        ->and(toolNamesOfRequest())->not->toContain('reveal')->not->toContain('acknowledge');
});
