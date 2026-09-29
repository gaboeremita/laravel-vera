<?php

use App\Actions\Narrate;
use App\Exceptions\NarratorUnavailable;
use App\Models\AiModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('returns the narrator\'s verdict and describes the attempt to it', function () {
    [, , , $region] = worldStateScenario(fakeReply: false);
    $region->world->update(['narrator_model_id' => AiModel::first()->id]);
    fakeTurn(toolCallResponse('call_1', 'narrate', ['succeeded' => false, 'narration' => 'The lock does not budge.', 'action' => '*rattles the gate.*']));

    $verdict = app(Narrate::class)->handle($region->world, $region, ['Doing' => 'opening the gate', 'Requirement' => 'the iron key']);

    expect($verdict)->toBe(['succeeded' => false, 'narration' => 'The lock does not budge.', 'action' => 'rattles the gate']);
    $request = Http::recorded()[0][0];
    expect(collect($request['tools'])->pluck('function.name')->all())->toBe(['narrate'])
        ->and(collect($request['messages'])->firstWhere('role', 'user')['content'])->toContain('Requirement: the iron key')
        ->and(sentSystemPrompt())->toContain("narrator of {$region->world->name}");
});

it('fails loudly when the narrator gives no verdict', function () {
    [, , , $region] = worldStateScenario(fakeReply: false);
    $region->world->update(['narrator_model_id' => AiModel::first()->id]);
    fakeTurn(finalAnswerResponse('It opens, probably.'));

    app(Narrate::class)->handle($region->world, $region, ['Doing' => 'opening the gate']);
})->throws(RuntimeException::class, 'The narrator did not give a verdict');

it('says when there is no narrator model at all', function () {
    [, , , $region] = worldStateScenario(fakeReply: false);
    config(['ai.default' => null]);

    app(Narrate::class)->handle($region->world, $region, ['Doing' => 'opening the gate']);
})->throws(NarratorUnavailable::class, 'No narrator model is set for this world');

it('lets the world choose one of the user\'s models as narrator', function () {
    [$user, , , $region] = worldStateScenario();
    $world = $region->world;
    $payload = ['name' => $world->name, 'slug' => $world->slug, 'description' => $world->description, 'assistantContextPrompt' => 'a', 'npcContextPrompt' => 'b'];

    $this->actingAs($user)->patchJson(route('worlds.update', $world), [...$payload, 'narratorModelId' => AiModel::first()->id])
        ->assertOk()->assertJsonPath('narratorModelId', AiModel::first()->id);

    $foreign = setUpAgentAssistant()[0];
    $foreignModel = AiModel::whereHas('provider', fn ($query) => $query->where('user_id', $foreign->id))->first();
    $this->patchJson(route('worlds.update', $world), [...$payload, 'narratorModelId' => $foreignModel->id])->assertJsonValidationErrors('narratorModelId');
});
