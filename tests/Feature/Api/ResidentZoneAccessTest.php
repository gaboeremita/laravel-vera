<?php

use App\Actions\ApplyResidentZoneAccess;
use App\Models\World;
use App\Models\WorldResident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * The factory layout plus a private house and a secret hideout with a room
 * and a table inside it.
 */
function layoutWithPrivateZones(): array
{
    $layout = World::factory()->withLayout()->make()->layout;
    $zone = fn (string $id, string $name, array $extra) => [
        'id' => $id, 'name' => $name, 'description' => "The {$name}.",
        'floorId' => 'ground', 'parentId' => null, 'private' => false, 'secret' => false, 'accessTags' => [],
        'outline' => [[20, 20], [30, 20], [30, 30], [20, 30]], 'minY' => -2, 'maxY' => 4,
        'entry' => ['x' => 25, 'y' => 0, 'z' => 25], 'activities' => [],
        ...$extra,
    ];
    $layout['zones'][] = $zone('mona-house', 'Row house', ['private' => true]);
    $layout['zones'][] = $zone('the-orphanage', 'Orphanage', ['private' => true, 'secret' => true, 'accessTags' => ['Deprecated']]);
    $layout['zones'][] = $zone('war-room', 'War room', ['parentId' => 'the-orphanage']);
    $layout['objects'][] = [
        'id' => 'war-table', 'name' => 'War table', 'description' => 'A holographic map of the Lua Building.',
        'position' => ['x' => 25, 'y' => 0, 'z' => 25], 'zoneId' => 'war-room',
        'spots' => [['id' => 'war-table-north', 'position' => ['x' => 25, 'y' => 0, 'z' => 24], 'facing' => 0.0, 'approach' => ['x' => 25, 'y' => 0, 'z' => 24], 'activities' => [['id' => 'study-map', 'name' => 'Study the map', 'posture' => null, 'pose' => null]]]],
    ];

    return $layout;
}

function residentWith(?array $zoneAccess): WorldResident
{
    return WorldResident::factory()->make(['zone_access' => $zoneAccess]);
}

function worldWithPrivateZones(): World
{
    return World::factory()->make(['layout' => layoutWithPrivateZones()]);
}

it('hides a secret zone, the zones inside it and their things from a resident outside its group', function () {
    $visible = (new ApplyResidentZoneAccess)->handle(worldWithPrivateZones(), residentWith(null))->layout;

    expect(collect($visible['zones'])->pluck('id')->all())->not->toContain('the-orphanage')->not->toContain('war-room')
        ->and(collect($visible['objects'])->pluck('id')->all())->not->toContain('war-table');
});

it('opens a secret zone and everything inside it to a resident in its group, whatever the case of the tag', function () {
    $visible = (new ApplyResidentZoneAccess)->handle(worldWithPrivateZones(), residentWith(['tags' => ['deprecated'], 'zones' => []]))->layout;
    $zones = collect($visible['zones'])->keyBy('id');

    expect($zones['the-orphanage']['residentAccess'])->toBe(ApplyResidentZoneAccess::ALLOWED)
        ->and($zones['war-room']['residentAccess'])->toBe(ApplyResidentZoneAccess::ALLOWED)
        ->and($zones['mona-house']['residentAccess'])->toBe(ApplyResidentZoneAccess::PRIVATE)
        ->and($zones['studio']['residentAccess'])->toBe(ApplyResidentZoneAccess::OPEN)
        ->and(collect($visible['objects'])->pluck('id')->all())->toContain('war-table');
});

it('opens a private zone to a resident given it by id', function () {
    $visible = (new ApplyResidentZoneAccess)->handle(worldWithPrivateZones(), residentWith(['tags' => [], 'zones' => ['mona-house']]))->layout;

    expect(collect($visible['zones'])->firstWhere('id', 'mona-house')['residentAccess'])->toBe(ApplyResidentZoneAccess::ALLOWED);
});

it('leaves the stored world untouched', function () {
    $world = worldWithPrivateZones();

    (new ApplyResidentZoneAccess)->handle($world, residentWith(null));

    expect(collect($world->layout['zones'])->pluck('id')->all())->toContain('the-orphanage');
});

it('keeps secret places out of her tools and tells her which places are private', function () {
    $scenario = worldStateScenario(['layout' => layoutWithPrivateZones()]);

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -3],
        'residents' => [$scenario[4]->id => ['x' => -5, 'y' => 0, 'z' => 2]],
    ])->assertSuccessful();

    /** @var Request $request */
    $request = Http::recorded()[0][0];
    $tools = collect($request['tools'])->keyBy('function.name');
    expect($tools['go_to']['function']['parameters']['properties']['target']['enum'])
        ->toContain('mona-house')
        ->not->toContain('the-orphanage')
        ->not->toContain('war-room')
        ->not->toContain('war-table');
    expect(sentSystemPrompt())
        ->toContain('Row house [mona-house] (Ground floor), private: you go in only when the user asks you to')
        ->not->toContain('Orphanage');
});

it('tells a resident in the group that the secret place is hers to enter', function () {
    $scenario = worldStateScenario(['layout' => layoutWithPrivateZones()], fakeReply: false);
    $scenario[4]->update(['zone_access' => ['tags' => ['deprecated'], 'zones' => []]]);
    fakeTurn(toolCallResponse('call_1', 'what_is_in', ['place' => 'the-orphanage']), finalAnswerResponse('Home.'));

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -3],
        'residents' => [$scenario[4]->id => ['x' => -5, 'y' => 0, 'z' => 2]],
    ])->assertSuccessful();

    expect(toolResultSentBack())->toContain('private, and you may go in')->toContain('War room');
});
