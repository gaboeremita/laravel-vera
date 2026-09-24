<?php

use App\Enums\AssistantKind;
use App\Enums\Posture;
use App\Models\AiModel;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\Pose;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function sendToolWorldMessage($test, array $scenario): TestResponse
{
    return sendWorldMessage($test, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -3],
        'residents' => [$scenario[4]->id => ['x' => -5, 'y' => 0, 'z' => 2]],
    ]);
}

it('offers the world tools, with go_to limited to the world\'s real ids', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(finalAnswerResponse('Hello.'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful();

    /** @var Request $request */
    $request = Http::recorded()[0][0];
    $tools = collect($request['tools'])->keyBy('function.name');
    expect($tools->keys()->sort()->values()->all())->toBe(['describe', 'follow', 'go_to', 'plan', 'stop', 'swim_to_edge', 'use', 'wander', 'what_is_in', 'where_can_i', 'zone']);
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
        ->assertJsonPath('action', ['verb' => 'use', 'target' => 'pool-lounger-1-seat', 'activity' => 'recline', 'pose' => null]);
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
    ])->assertSuccessful()->assertJsonPath('action', ['verb' => 'zone', 'target' => null, 'activity' => 'swim', 'pose' => null]);
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

it('returns a plan of several steps, checked before anything starts', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', 'plan', [
        'goal' => 'relax by the pool',
        'steps' => [
            ['action' => 'go_to', 'target' => 'pool-terrace'],
            ['action' => 'zone', 'activity' => 'swim'],
            ['action' => 'use', 'target' => 'pool-lounger-1-seat', 'activity' => 'recline'],
            ['action' => 'do', 'description' => 'hums a song with her eyes closed'],
        ],
    ]), finalAnswerResponse('(I need the sun) *heads out for a swim, then the lounger*'));

    sendToolWorldMessage($this, $scenario)
        ->assertSuccessful()
        ->assertJsonPath('action.verb', 'plan')
        ->assertJsonPath('action.target', 'relax by the pool')
        ->assertJsonPath('action.steps.0', ['verb' => 'go_to', 'target' => 'pool-terrace', 'activity' => null, 'pose' => null, 'description' => null])
        ->assertJsonPath('action.steps.1.activity', 'swim')
        ->assertJsonPath('action.steps.2', ['verb' => 'use', 'target' => 'pool-lounger-1-seat', 'activity' => 'recline', 'pose' => null, 'description' => null])
        ->assertJsonPath('action.steps.3.description', 'hums a song with her eyes closed');
});

it('gives a plan with a bad step back to her with the step number', function (array $step, string $error) {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', 'plan', [
        'goal' => 'do something',
        'steps' => [['action' => 'go_to', 'target' => 'studio'], $step],
    ]), finalAnswerResponse('Hm.'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful()->assertJsonPath('action', null);
    expect(toolResultSentBack())->toContain('Step 2:')->toContain($error);
})->with([
    'unknown place' => [['action' => 'go_to', 'target' => 'moon'], 'no place or thing called \\"moon\\"'],
    'zone activity of another place' => [['action' => 'zone', 'activity' => 'swim'], 'not something you can do in the place you will be in'],
    'do with no description' => [['action' => 'do'], 'needs a description'],
    'unknown pose' => [['action' => 'pose', 'pose' => 'backflip'], 'no pose called \\"backflip\\"'],
]);

it('plays the pose she picks from her own library for an activity', function (array $arguments, ?string $pose) {
    $scenario = worldStateScenario(fakeReply: false);
    Pose::factory()->posture(Posture::Reclining)->create(['assistant_id' => $scenario[1]->id, 'name' => 'lounge_back']);
    fakeTurn(toolCallResponse('call_1', 'use', ['spot' => 'pool-lounger-1-seat', 'activity' => 'recline', ...$arguments]), finalAnswerResponse('Ah.'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful()->assertJsonPath('action.pose', $pose);
})->with([
    'her chosen pose' => [['pose' => 'lounge_back'], 'lounge_back'],
    'no choice and the activity names no pose' => [[], null],
]);

it('rejects a pose she does not have', function () {
    $scenario = worldStateScenario(fakeReply: false);
    Pose::factory()->create(['assistant_id' => $scenario[1]->id, 'name' => 'prepare_drink']);
    fakeTurn(toolCallResponse('call_1', 'use', ['spot' => 'pool-lounger-1-seat', 'activity' => 'recline', 'pose' => 'backflip']), finalAnswerResponse('Hm.'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful()->assertJsonPath('action', null);
    expect(toolResultSentBack())->toContain('You have no pose called \\"backflip\\"')->toContain('prepare_drink');
});

it('goes to the user', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', 'go_to', ['target' => 'user']), finalAnswerResponse('Coming.'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful()->assertJsonPath('action', ['verb' => 'go_to', 'target' => 'user', 'activity' => null]);
});

it('swims to the edge on her own', function () {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', 'swim_to_edge', []), finalAnswerResponse('(My arms are tired) *swims to the side and rests*'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful()->assertJsonPath('action', ['verb' => 'swim_to_edge', 'target' => null, 'activity' => null]);
});

it('wanders around a place or around where she is', function (array $arguments, ?string $target) {
    $scenario = worldStateScenario(fakeReply: false);
    fakeTurn(toolCallResponse('call_1', 'wander', $arguments), finalAnswerResponse('(Let us see) *drifts off to explore*'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful()->assertJsonPath('action', ['verb' => 'wander', 'target' => $target, 'activity' => null]);
})->with([
    'a place' => [['place' => 'gallery'], 'gallery'],
    'around her' => [[], null],
]);

it('rejects a pose with no version for the posture the activity puts her in', function () {
    $scenario = worldStateScenario(fakeReply: false);
    Pose::factory()->create(['assistant_id' => $scenario[1]->id, 'name' => 'flirty']);
    Pose::factory()->posture(Posture::Reclining)->create(['assistant_id' => $scenario[1]->id, 'name' => 'sunbathe']);
    fakeTurn(toolCallResponse('call_1', 'use', ['spot' => 'pool-lounger-1-seat', 'activity' => 'recline', 'pose' => 'flirty']), finalAnswerResponse('Hm.'));

    sendToolWorldMessage($this, $scenario)->assertSuccessful()->assertJsonPath('action', null);
    expect(toolResultSentBack())->toContain('has no reclining version')->toContain('Your reclining poses: sunbathe');
});

it('runs an NPC on the model chosen for it instead of the default', function () {
    config(['ai.default.url' => 'https://default-llm.test/chat/completions', 'ai.default.model' => 'default', 'ai.default.format' => 'generic']);
    [$user, , , $world] = worldStateScenario(fakeReply: false);
    $npc = Assistant::factory()->create(['kind' => AssistantKind::WorldNpc, 'mode' => 'assistant']);
    $assistantUser = AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $npc->id]);
    $conversation = Conversation::factory()->create(['assistant_user_id' => $assistantUser->id]);
    $world->residents()->create(['assistant_id' => $npc->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);
    $this->actingAs($user)->putJson(route('settings.selectModel', ['assistant' => $npc->id]), ['ai_model_id' => AiModel::query()->value('id')])->assertSuccessful();
    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Hi.')),
        'default-llm.test/*' => Http::response(finalAnswerResponse('Wrong model.')),
    ]);

    $this->actingAs($user)->postJson(route('conversations.sendMessage', ['assistant' => $npc->id, 'id' => $conversation->id]), [
        'messages' => [['role' => 'user', 'content' => 'Hello.']],
        'worldId' => $world->id,
    ])->assertSuccessful()->assertJsonPath('content', 'Hi.');
});
