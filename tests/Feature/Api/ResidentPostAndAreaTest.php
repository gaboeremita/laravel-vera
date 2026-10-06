<?php

use App\Actions\ApplyResidentZoneAccess;
use App\Enums\AssistantKind;
use App\Models\Region;
use App\Models\WorldResident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('shows a resident with an area every zone of their home region, marking the ones outside it', function () {
    $region = Region::factory()->withLayout()->create();
    $resident = WorldResident::factory()->make(['region_id' => $region->id, 'behavior_settings' => ['area' => ['studio']]]);

    $layout = (new ApplyResidentZoneAccess)->handle($region, $resident)->layout;
    $access = collect($layout['zones'])->pluck('residentAccess', 'id');

    expect($access['studio'])->toBe(ApplyResidentZoneAccess::OPEN)
        ->and($access['vocal-booth'])->toBe(ApplyResidentZoneAccess::OPEN)
        ->and($access['pool-terrace'])->toBe(ApplyResidentZoneAccess::OUTSIDE_AREA)
        ->and(collect($layout['objects'])->pluck('id')->all())->toContain('pool-lounger-1');
});

it('leaves a resident\'s area out of every region but their home one', function () {
    $home = Region::factory()->withLayout()->create();
    $elsewhere = Region::factory()->withLayout()->create(['world_id' => $home->world_id]);
    $resident = WorldResident::factory()->make(['region_id' => $home->id, 'behavior_settings' => ['area' => ['studio']]]);

    $layout = (new ApplyResidentZoneAccess)->handle($elsewhere, $resident)->layout;

    expect(collect($layout['zones'])->pluck('residentAccess')->unique()->all())->toBe([ApplyResidentZoneAccess::OPEN])
        ->and(collect($layout['objects'])->pluck('id')->all())->toContain('pool-lounger-1');
});

it('offers a resident who keeps to an area every place, telling them which lie outside it', function () {
    $scenario = worldStateScenario();
    $scenario[4]->update(['behavior_settings' => ['area' => ['studio']]]);

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => -5, 'y' => 0, 'z' => 2],
        'residents' => [$scenario[4]->id => ['x' => -5, 'y' => 0, 'z' => 3]],
    ])->assertSuccessful();

    $tools = collect(Http::recorded()[0][0]['tools'])->keyBy('function.name');
    expect($tools['go_to']['function']['parameters']['properties']['target']['enum'])->toContain('studio')->toContain('pool-terrace')
        ->and(sentPrompt())
        ->toContain("Available places:\nGround floor: Music studio [studio], Vocal booth [vocal-booth]")
        ->toContain("Outside the area you keep to, so you go there only when the user asks you to:\nGround floor: Pool terrace [pool-terrace]\nUpper floor: Gallery [gallery]");
});

it('gives an NPC who stays put only the tools that tell her about the place and the trading tools, and tells her she keeps to her post', function (string $behavior) {
    $scenario = worldStateScenario();
    $scenario[1]->update(['kind' => AssistantKind::WorldNpc]);
    $scenario[4]->update(['behavior' => $behavior, 'behavior_settings' => $behavior === 'route' ? ['route' => [['target' => 'studio'], ['target' => 'pool-terrace']]] : null]);

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -3],
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -4]],
    ])->assertSuccessful();

    expect(collect(Http::recorded()[0][0]['tools'])->pluck('function.name')->sort()->values()->all())->toBe(['ask_for', 'check_holds', 'describe', 'give', 'what_is_in', 'where_can_i'])
        ->and(sentPrompt())->toContain('You keep to your post here, and people come to you.')->not->toContain('Your body in this world moves only through your tools');
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
    $prompt = sentPrompt();
    expect(strpos($prompt, '# WORLD AWARENESS'))->toBeLessThan(strpos($prompt, '# PLACES IN THIS WORLD'))
        ->and(strpos($prompt, '# PLACES IN THIS WORLD'))->toBeLessThan(strpos($prompt, 'World state:'))
        ->and($prompt)->toContain('Things here:');

    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Right here.'))]);
    $scenario[1]->update(['kind' => AssistantKind::WorldNpc]);
    sendWorldMessage($this, $scenario, $positions)->assertSuccessful();

    expect(sentPrompt())->toContain('World state:')->toContain('You are in: Pool terrace')
        ->not->toContain('# PLACES IN THIS WORLD')->not->toContain('Things here:')->not->toContain('Things to do here:');
});
