<?php

use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function worldPayloadWithEnvironment(string $glb, string $slug = 'lua-penthouse'): array
{
    return [
        'name' => 'Lua Penthouse',
        'slug' => $slug,
        'description' => 'The Creator\'s penthouse.',
        'assistantContextPrompt' => 'You are in the penthouse.',
        'npcContextPrompt' => 'You work in the penthouse.',
        'settings' => ['theme' => 'terminal'],
        'environment' => UploadedFile::fake()->createWithContent('penthouse.glb', $glb),
    ];
}

function markerNode(string $name, array $vera, array $transform = [], array $children = []): array
{
    return array_filter([
        'name' => $name,
        'extras' => ['vera' => $vera],
        'children' => $children ?: null,
        ...$transform,
    ], fn ($value) => $value !== null);
}

/**
 * A penthouse-like file: one floor, a terrace zone with an entry, and a
 * lounger object inside a rotated group so world transforms are exercised.
 */
function penthouseMarkerNodes(): array
{
    $quarterTurn = [0, sin(M_PI / 4), 0, cos(M_PI / 4)];

    return [
        0 => markerNode('Floor.Ground', ['type' => 'floor', 'id' => 'ground', 'name' => 'Ground floor', 'minY' => -2, 'maxY' => 4]),
        1 => markerNode('Zone.Terrace', [
            'type' => 'zone', 'id' => 'pool-terrace', 'name' => 'Pool terrace', 'floor' => 'ground',
            'description' => 'An open terrace with an infinity pool.',
            'activities' => [['id' => 'swim', 'name' => 'Swim']],
        ], ['translation' => [12, 2, 0], 'scale' => [3, 2, 3]], [2]),
        2 => markerNode('Zone.Terrace.Entry', ['type' => 'entry'], ['translation' => [0, -1, 0.5]]),
        3 => ['name' => 'Furniture', 'translation' => [10, 0, 0], 'rotation' => $quarterTurn, 'children' => [4]],
        4 => markerNode('Lounger', [
            'type' => 'object', 'id' => 'pool-lounger-1', 'name' => 'Pool lounger',
            'description' => 'A white lounger on the sun ledge.',
        ], ['translation' => [0, 0, 2]], [5]),
        5 => markerNode('Lounger.Seat', [
            'type' => 'spot', 'id' => 'pool-lounger-1-seat',
            'activities' => [['id' => 'recline', 'name' => 'Recline', 'posture' => 'reclining']],
        ], ['translation' => [0, 0.5, 0]]),
    ];
}

it('imports floors, zones, objects and spots in world space from an uploaded environment', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('worlds.store'), worldPayloadWithEnvironment(buildTestGlb(penthouseMarkerNodes())));

    $response->assertCreated()->assertJsonPath('layoutWarnings', []);
    $layout = World::findOrFail($response->json('id'))->layout;

    expect($layout['floors'])->toBe([['id' => 'ground', 'name' => 'Ground floor', 'minY' => -2, 'maxY' => 4]]);

    $zone = $layout['zones'][0];
    expect($zone['id'])->toBe('pool-terrace')
        ->and($zone['floorId'])->toBe('ground')
        ->and($zone['parentId'])->toBeNull()
        ->and($zone['private'])->toBeFalse()
        ->and($zone['activities'])->toBe([['id' => 'swim', 'name' => 'Swim', 'posture' => null, 'pose' => null]])
        ->and($zone['minY'])->toEqualWithDelta(0, 0.0001)
        ->and($zone['maxY'])->toEqualWithDelta(4, 0.0001)
        ->and($zone['entry']['x'])->toEqualWithDelta(12, 0.0001)
        ->and($zone['entry']['y'])->toEqualWithDelta(0, 0.0001)
        ->and($zone['entry']['z'])->toEqualWithDelta(1.5, 0.0001);
    expect(collect($zone['outline'])->map(fn ($point) => [round($point[0], 4), round($point[1], 4)])->all())
        ->toBe([[9.0, -3.0], [15.0, -3.0], [15.0, 3.0], [9.0, 3.0]]);

    $object = $layout['objects'][0];
    expect($object['id'])->toBe('pool-lounger-1')
        ->and($object['zoneId'])->toBe('pool-terrace')
        ->and($object['position']['x'])->toEqualWithDelta(12, 0.0001)
        ->and($object['position']['z'])->toEqualWithDelta(0, 0.0001);

    $spot = $object['spots'][0];
    expect($spot['id'])->toBe('pool-lounger-1-seat')
        ->and($spot['position']['x'])->toEqualWithDelta(12, 0.0001)
        ->and($spot['position']['y'])->toEqualWithDelta(0.5, 0.0001)
        ->and($spot['facing'])->toEqualWithDelta(M_PI / 2, 0.0001)
        ->and($spot['approach']['x'])->toEqualWithDelta(12.6, 0.0001)
        ->and($spot['approach']['z'])->toEqualWithDelta(0, 0.0001)
        ->and($spot['activities'])->toBe([['id' => 'recline', 'name' => 'Recline', 'posture' => 'reclining', 'pose' => null]])
        ->and($spot['capacity'])->toBe(1);
});

it('imports how many bodies a spot holds', function () {
    $user = User::factory()->create();
    $nodes = penthouseMarkerNodes();
    $nodes[5]['extras']['vera']['capacity'] = 2;

    $response = $this->actingAs($user)->postJson(route('worlds.store'), worldPayloadWithEnvironment(buildTestGlb($nodes)));

    $response->assertCreated()->assertJsonPath('layoutWarnings', []);
    expect(World::findOrFail($response->json('id'))->layout['objects'][0]['spots'][0]['capacity'])->toBe(2);
});

it('falls back to one body with a warning for an invalid spot capacity', function (mixed $capacity) {
    $user = User::factory()->create();
    $nodes = penthouseMarkerNodes();
    $nodes[5]['extras']['vera']['capacity'] = $capacity;

    $response = $this->actingAs($user)->postJson(route('worlds.store'), worldPayloadWithEnvironment(buildTestGlb($nodes)));

    $response->assertCreated()->assertJsonPath('layoutWarnings.0.reason', 'spot capacity must be a whole number of at least 1');
    expect(World::findOrFail($response->json('id'))->layout['objects'][0]['spots'][0]['capacity'])->toBe(1);
})->with([
    'zero' => [0],
    'fraction' => [1.5],
    'text' => ['two'],
]);

it('imports nested zones and custom outlines', function () {
    $user = User::factory()->create();
    $nodes = [
        0 => markerNode('Zone.Studio', [
            'type' => 'zone', 'id' => 'music-studio', 'name' => 'Music studio', 'description' => 'Keyboards everywhere.',
        ], ['translation' => [-5, 2, 5], 'scale' => [5, 2, 5]], [1]),
        1 => markerNode('Zone.Studio.Entry', ['type' => 'entry']),
        2 => markerNode('Zone.Booth', [
            'type' => 'zone', 'id' => 'vocal-booth', 'name' => 'Vocal booth', 'description' => 'A glass booth.',
            'parent' => 'music-studio', 'private' => true,
            'outline' => [[0, 0], [2, 0], [2, 2], [0, 2]],
        ], ['translation' => [-4, 2, 6]], [3]),
        3 => markerNode('Zone.Booth.Entry', ['type' => 'entry']),
    ];

    $response = $this->actingAs($user)->postJson(route('worlds.store'), worldPayloadWithEnvironment(buildTestGlb($nodes)));

    $response->assertCreated()->assertJsonPath('layoutWarnings', []);
    $booth = collect(World::findOrFail($response->json('id'))->layout['zones'])->firstWhere('id', 'vocal-booth');
    expect($booth['parentId'])->toBe('music-studio')
        ->and($booth['private'])->toBeTrue()
        ->and($booth['floorId'])->toBeNull()
        ->and($booth['outline'])->toEqual([[-4, 6], [-2, 6], [-2, 8], [-4, 8]]);
});

it('replaces the layout when the environment is replaced and keeps it otherwise', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->withLayout()->create(['slug' => 'lua-penthouse']);

    $payload = worldPayloadWithEnvironment(buildTestGlb(penthouseMarkerNodes()));
    unset($payload['environment']);
    $this->actingAs($user)->withHeader('Accept', 'application/json')->put(route('worlds.update', $world), $payload)->assertSuccessful();
    expect(collect($world->fresh()->layout['zones'])->pluck('id')->all())->toBe(['studio', 'vocal-booth', 'pool-terrace', 'gallery']);

    $this->actingAs($user)->withHeader('Accept', 'application/json')
        ->put(route('worlds.update', $world), worldPayloadWithEnvironment(buildTestGlb(penthouseMarkerNodes())))
        ->assertSuccessful()
        ->assertJsonPath('layoutWarnings', []);
    expect(collect($world->fresh()->layout['zones'])->pluck('id')->all())->toBe(['pool-terrace']);
});

it('stores an empty layout for an environment without markers', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('worlds.store'), worldPayloadWithEnvironment(buildTestGlb([['name' => 'Floor mesh']])));

    $response->assertCreated()->assertJsonPath('layoutWarnings', []);
    expect(World::findOrFail($response->json('id'))->layout)->toBe(['floors' => [], 'zones' => [], 'objects' => []]);
});

it('reports a file that is not a valid GLB and stores an empty layout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('worlds.store'), worldPayloadWithEnvironment('not a glb file'));

    $response->assertCreated()->assertJsonPath('layoutWarnings.0.reason', 'The environment file is not a valid GLB, so no markers were read.');
    expect(World::findOrFail($response->json('id'))->layout)->toBe(['floors' => [], 'zones' => [], 'objects' => []]);
});

it('skips invalid markers with a warning while importing the valid ones', function (array $invalidNodes, string $expectedReason) {
    $user = User::factory()->create();
    $nodes = [...penthouseMarkerNodes(), ...$invalidNodes];

    $response = $this->actingAs($user)->postJson(route('worlds.store'), worldPayloadWithEnvironment(buildTestGlb($nodes)));

    $response->assertCreated();
    expect(collect($response->json('layoutWarnings'))->pluck('reason')->implode(' | '))->toContain($expectedReason);
    $layout = World::findOrFail($response->json('id'))->layout;
    expect(collect($layout['zones'])->pluck('id'))->toContain('pool-terrace');
    expect(collect($layout['objects'])->pluck('id'))->toContain('pool-lounger-1');
})->with([
    'missing id' => [[6 => markerNode('Zone.NoId', ['type' => 'zone', 'name' => 'Nameless', 'description' => 'x', 'floor' => 'ground'], [], [7]), 7 => markerNode('E', ['type' => 'entry'])], 'missing required field "id"'],
    'malformed id' => [[6 => markerNode('Zone.Bad', ['type' => 'zone', 'id' => 'Bad Id', 'name' => 'Bad', 'description' => 'x', 'floor' => 'ground'], [], [7]), 7 => markerNode('E', ['type' => 'entry'])], 'must be a lowercase slug'],
    'duplicate id' => [[6 => markerNode('Zone.Dup', ['type' => 'zone', 'id' => 'pool-terrace', 'name' => 'Dup', 'description' => 'x', 'floor' => 'ground'], [], [7]), 7 => markerNode('E', ['type' => 'entry'])], 'duplicate zone id "pool-terrace"'],
    'zone without entry' => [[6 => markerNode('Zone.NoEntry', ['type' => 'zone', 'id' => 'kitchen', 'name' => 'Kitchen', 'description' => 'x', 'floor' => 'ground'])], 'zone has no entry child'],
    'unknown floor' => [[6 => markerNode('Zone.Floorless', ['type' => 'zone', 'id' => 'attic', 'name' => 'Attic', 'description' => 'x', 'floor' => 'roof'], [], [7]), 7 => markerNode('E', ['type' => 'entry'])], 'unknown floor "roof"'],
    'missing floor when floors exist' => [[6 => markerNode('Zone.NoFloor', ['type' => 'zone', 'id' => 'attic', 'name' => 'Attic', 'description' => 'x'], [], [7]), 7 => markerNode('E', ['type' => 'entry'])], 'missing required field "floor"'],
    'unknown parent' => [[6 => markerNode('Zone.Orphan', ['type' => 'zone', 'id' => 'closet', 'name' => 'Closet', 'description' => 'x', 'floor' => 'ground', 'parent' => 'nowhere'], [], [7]), 7 => markerNode('E', ['type' => 'entry'])], 'unknown parent zone "nowhere"'],
    'overlapping floors' => [[6 => markerNode('Floor.Overlap', ['type' => 'floor', 'id' => 'mezzanine', 'name' => 'Mezzanine', 'minY' => 2, 'maxY' => 6])], 'overlaps floor "ground"'],
    'spot outside an object' => [[6 => markerNode('Loose.Spot', ['type' => 'spot', 'id' => 'loose-spot', 'activities' => [['id' => 'sit', 'name' => 'Sit', 'posture' => 'sitting']]])], 'spot is not inside an object'],
    'spot without activities' => [[6 => markerNode('Chair', ['type' => 'object', 'id' => 'chair', 'name' => 'Chair', 'description' => 'x'], [], [7]), 7 => markerNode('Chair.Seat', ['type' => 'spot', 'id' => 'chair-seat', 'activities' => []])], 'spot has no activities'],
    'short outline' => [[6 => markerNode('Zone.Line', ['type' => 'zone', 'id' => 'hall', 'name' => 'Hall', 'description' => 'x', 'floor' => 'ground', 'outline' => [[0, 0], [1, 0]]], [], [7]), 7 => markerNode('E', ['type' => 'entry'])], 'outline needs at least three points'],
    'unknown posture' => [[6 => markerNode('Bed', ['type' => 'object', 'id' => 'bed', 'name' => 'Bed', 'description' => 'x'], [], [7]), 7 => markerNode('Bed.Side', ['type' => 'spot', 'id' => 'bed-side', 'activities' => [['id' => 'float', 'name' => 'Float', 'posture' => 'floating']]])], 'unknown posture "floating"'],
    'unknown type' => [[6 => markerNode('Mystery', ['type' => 'portal', 'id' => 'portal'])], 'unknown marker type "portal"'],
]);
