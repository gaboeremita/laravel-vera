<?php

use App\Actions\ApplyResidentZoneAccess;
use App\Enums\AssistantKind;
use App\Models\World;
use App\Models\WorldResident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('keeps a resident with an area to its zones and the zones inside them', function () {
    $world = World::factory()->withLayout()->make();
    $resident = WorldResident::factory()->make(['behavior_settings' => ['area' => ['studio']]]);

    $layout = (new ApplyResidentZoneAccess)->handle($world, $resident)->layout;

    expect(collect($layout['zones'])->pluck('id')->all())->toBe(['studio', 'vocal-booth'])
        ->and(collect($layout['objects'])->pluck('id')->all())->not->toContain('pool-lounger-1');
});

it('offers places outside her area to nobody who keeps to one', function () {
    $scenario = worldStateScenario();
    $scenario[4]->update(['behavior_settings' => ['area' => ['studio']]]);

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => -5, 'y' => 0, 'z' => 2],
        'residents' => [$scenario[4]->id => ['x' => -5, 'y' => 0, 'z' => 3]],
    ])->assertSuccessful();

    $tools = collect(Http::recorded()[0][0]['tools'])->keyBy('function.name');
    expect($tools['go_to']['function']['parameters']['properties']['target']['enum'])->toContain('studio')->not->toContain('pool-terrace')->not->toContain('gallery')
        ->and(sentSystemPrompt())->toContain('Available places: Music studio [studio] (Ground floor), Vocal booth [vocal-booth] (Ground floor)')->not->toContain('Pool terrace [pool-terrace]');
});

it('gives an NPC who stays put only the tools that tell her about the place, and tells her she keeps to her post', function (string $behavior) {
    $scenario = worldStateScenario();
    $scenario[1]->update(['kind' => AssistantKind::WorldNpc]);
    $scenario[4]->update(['behavior' => $behavior, 'behavior_settings' => $behavior === 'route' ? ['route' => [['target' => 'studio'], ['target' => 'pool-terrace']]] : null]);

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -3],
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -4]],
    ])->assertSuccessful();

    expect(collect(Http::recorded()[0][0]['tools'])->pluck('function.name')->sort()->values()->all())->toBe(['describe', 'what_is_in', 'where_can_i'])
        ->and(sentSystemPrompt())->toContain('You keep to your post here, and people come to you.')->not->toContain('Your body in this world moves only through your tools');
})->with(['stationary', 'route']);

it('keeps the movement tools of an assistant who stays put and of a roaming NPC', function (AssistantKind $kind, string $behavior) {
    $scenario = worldStateScenario();
    $scenario[1]->update(['kind' => $kind]);
    $scenario[4]->update(['behavior' => $behavior]);

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -3],
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -4]],
    ])->assertSuccessful();

    expect(collect(Http::recorded()[0][0]['tools'])->pluck('function.name')->all())->toContain('go_to')->toContain('follow');
})->with([
    'assistant who stays put' => [AssistantKind::Assistant, 'stationary'],
    'roaming NPC' => [AssistantKind::WorldNpc, 'roam'],
]);

it('puts the unchanging world sections before the world state, and leaves places, activities and things out for an NPC at her post', function () {
    $scenario = worldStateScenario();
    $positions = ['user' => ['x' => 5, 'y' => 0, 'z' => -3], 'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -4]]];

    sendWorldMessage($this, $scenario, $positions)->assertSuccessful();
    $prompt = sentSystemPrompt();
    expect(strpos($prompt, 'World awareness:'))->toBeLessThan(strpos($prompt, 'Places in this world:'))
        ->and(strpos($prompt, 'Places in this world:'))->toBeLessThan(strpos($prompt, 'World state:'))
        ->and($prompt)->toContain('Things here:');

    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Right here.'))]);
    $scenario[1]->update(['kind' => AssistantKind::WorldNpc]);
    sendWorldMessage($this, $scenario, $positions)->assertSuccessful();

    expect(sentSystemPrompt())->toContain('World state:')->toContain('You are in: Pool terrace')
        ->not->toContain('Places in this world')->not->toContain('Things here:')->not->toContain('Things to do here:');
});
