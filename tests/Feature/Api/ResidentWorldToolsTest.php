<?php

use App\Enums\AssistantKind;
use App\Models\AiModel;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeTurn(array ...$responses): void
{
    $sequence = Http::sequence();
    foreach ($responses as $response) {
        $sequence->push($response);
    }
    Http::fake(['fake-llm.test/*' => $sequence]);
}

function sendToolWorldMessage($test, array $scenario): Illuminate\Testing\TestResponse
{
    return sendWorldMessage($test, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -3],
        'residents' => [$scenario[4]->id => ['x' => -5, 'y' => 0, 'z' => 2]],
    ]);
}

/**
 * The content of the tool result she was given back, from the request that followed her tool call.
 */
function toolResultSentBack(int $requestIndex = 1): string
{
    /** @var Request $request */
    $request = Http::recorded()[$requestIndex][0];

    return collect($request['messages'])->where('role', 'tool')->last()['content'] ?? '';
}

it('offers the world tools, with go_to limited to the world\'s real ids', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(finalAnswerResponse('Hello.'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful();

    /** @var Request $request */
    $request = Http::recorded()[0][0];
    $tools = collect($request['tools'])->keyBy('function.name');
    expect($tools->keys()->sort()->values()->all())->toBe(['describe', 'follow', 'go_to', 'stop', 'use', 'what_is_in', 'where_can_i', 'zone']);
    expect($tools['go_to']['function']['parameters']['properties']['target']['enum'])
        ->toContain('pool-terrace')
        ->toContain('studio')
        ->toContain('pool-lounger-1');
    expect($tools['what_is_in']['function']['parameters']['properties']['place']['enum'])->toContain('gallery');
});

it('returns a use action for a spot activity', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', 'use', ['spot' => 'pool-lounger-1-seat', 'activity' => 'recline']), finalAnswerResponse('Time to relax.'));

    sendToolWorldMessage($this, $scenario)
        ->assertSuccessful()
        ->assertJsonPath('action', ['verb' => 'use', 'target' => 'pool-lounger-1-seat', 'activity' => 'recline']);
});

it('rejects an activity the spot does not offer', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', 'use', ['spot' => 'pool-lounger-1-seat', 'activity' => 'swim']), finalAnswerResponse('Hm.'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful()->assertJsonPath('action', null);
    expect(toolResultSentBack())->toContain('\"swim\" cannot be done at pool-lounger-1-seat')->toContain('recline');
});

it('returns a zone action for an activity of the place she is in', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', 'zone', ['activity' => 'swim']), finalAnswerResponse('Into the water.'));

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -3],
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -3]],
    ])->assertSuccessful()->assertJsonPath('action', ['verb' => 'zone', 'target' => null, 'activity' => 'swim']);
});

it('rejects a zone activity offered somewhere else', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', 'zone', ['activity' => 'swim']), finalAnswerResponse('Not here.'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful()->assertJsonPath('action', null);
    expect(toolResultSentBack())->toContain('\"swim\" is not something you can do where you are');
});

it('returns the action she chose with a tool', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', 'go_to', ['target' => 'pool-terrace']), finalAnswerResponse('On my way.'));

    sendToolWorldMessage($this, $scenario)
        ->assertSuccessful()
        ->assertJsonPath('content', 'On my way.')
        ->assertJsonPath('action', ['verb' => 'go_to', 'target' => 'pool-terrace', 'activity' => null]);
    expect(toolResultSentBack())->toContain('started');
});

it('returns follow and stop actions', function (string $tool, string $verb) {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', $tool, []), finalAnswerResponse('Okay.'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful()->assertJsonPath('action', ['verb' => $verb, 'target' => null, 'activity' => null]);
})->with([
    'follow' => ['follow', 'follow'],
    'stop' => ['stop', 'stop'],
]);

it('gives an unknown place back to her as an error within the same turn', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', 'go_to', ['target' => 'moon']), finalAnswerResponse('I cannot find that.'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful()->assertJsonPath('action', null);
    expect(toolResultSentBack())->toContain('There is no place or thing called \"moon\"');
});

it('keeps the first action when she tries a second one in the same turn', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(
        toolCallResponse('call_1', 'go_to', ['target' => 'pool-terrace']),
        toolCallResponse('call_2', 'follow', []),
        finalAnswerResponse('On my way.'),
    );

    sendToolWorldMessage($this, $scenario)->assertSuccessful()->assertJsonPath('action.verb', 'go_to');
    expect(toolResultSentBack(2))->toContain('You already chose an action this turn');
});

it('answers where an activity is available', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', 'where_can_i', ['activity' => 'recline']), finalAnswerResponse('By the pool.'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful();
    expect(toolResultSentBack())->toContain('Pool terrace')->toContain('pool-lounger-1-seat')->toContain('recline');
});

it('describes what a place contains', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', 'what_is_in', ['place' => 'pool-terrace']), finalAnswerResponse('A lounger.'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful();
    expect(toolResultSentBack())->toContain('An open terrace with an infinity pool.')->toContain('Pool lounger')->toContain('Swim');
});

it('describes a place or thing', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', 'describe', ['id' => 'pool-lounger-1']), finalAnswerResponse('A lounger.'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful();
    expect(toolResultSentBack())->toContain('A white lounger by the pool.')->toContain('Pool terrace');
});

it('refuses a world conversation with an assistant whose model cannot call tools', function () {
    $scenario = worldStateScenario();
    AiModel::query()->update(['supports_tools' => false]);

    sendToolWorldMessage($this, $scenario)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Assistants living in a world need a model that supports tool calling. Choose one in this assistant\'s settings.');
});

it('gives NPCs the world tools on the default model', function () {
    config([
        'ai.default.url' => 'https://fake-llm.test/chat/completions',
        'ai.default.model' => 'fake-default',
        'ai.default.format' => 'generic',
    ]);
    $user = User::factory()->create();
    $npc = Assistant::factory()->create(['kind' => AssistantKind::WorldNpc, 'mode' => 'assistant']);
    $assistantUser = AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $npc->id]);
    $conversation = Conversation::factory()->create(['assistant_user_id' => $assistantUser->id]);
    $world = World::factory()->forUser($user)->withLayout()->create();
    $resident = $world->residents()->create(['assistant_id' => $npc->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);
    fakeTurn(toolCallResponse('call_1', 'go_to', ['target' => 'studio']), finalAnswerResponse('This way.'));

    $this->actingAs($user)->postJson(route('conversations.sendMessage', ['assistant' => $npc->id, 'id' => $conversation->id]), [
        'messages' => [['role' => 'user', 'content' => 'Show me the studio.']],
        'worldId' => $world->id,
    ])->assertSuccessful()->assertJsonPath('action.target', 'studio');

    expect($resident->exists)->toBeTrue();
});
