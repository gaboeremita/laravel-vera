<?php

use App\Models\ResidentActivity;
use App\Models\WorldSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('tells the resident her zone, and the user\'s zone and distance when they share the room', function () {
    $scenario = worldStateScenario();
    $residentId = $scenario[4]->id;

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 1, 'y' => 0, 'z' => -1],
        'residents' => [$residentId => ['x' => 9, 'y' => 0, 'z' => -9]],
    ])->assertSuccessful();

    $prompt = sentSystemPrompt();
    expect($prompt)
        ->toContain('World state:')
        ->toContain('You are in: Pool terrace, on the Ground floor')
        ->toContain('The user is: in Pool terrace, on the Ground floor, about 11 m away from you');
});

it('only knows the user is out of sight when they are in another room', function (array $user, array $resident) {
    $scenario = worldStateScenario();

    sendWorldMessage($this, $scenario, ['user' => $user, 'residents' => [$scenario[4]->id => $resident]], ['userState' => ['posture' => 'swimming']])->assertSuccessful();

    expect(sentSystemPrompt())->toContain('The user is: somewhere out of sight')->not->toContain('away from you')->not->toContain(', swimming');
})->with([
    'another room' => [['x' => 5, 'y' => 0, 'z' => -3], ['x' => -2, 'y' => 0, 'z' => 9]],
    'a room inside hers' => [['x' => -2, 'y' => 0, 'z' => 8], ['x' => -8, 'y' => 0, 'z' => 2]],
    'upstairs' => [['x' => 0, 'y' => 5, 'z' => 5], ['x' => -5, 'y' => 0, 'z' => 2]],
]);

it('describes her own zone in detail and other zones only by name and floor', function () {
    $scenario = worldStateScenario();
    $residentId = $scenario[4]->id;

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => -5, 'y' => 0, 'z' => 2],
        'residents' => [$residentId => ['x' => 5, 'y' => 0, 'z' => -3]],
    ])->assertSuccessful();

    $prompt = sentSystemPrompt();
    expect($prompt)
        ->toContain('An open terrace with an infinity pool.')
        ->toContain('Things to do here: Swim [swim]')
        ->toContain('Pool lounger [pool-lounger-1]: A white lounger by the pool. Spots: pool-lounger-1-seat (Recline [recline], reclining)')
        ->toContain('Here: An open terrace with an infinity pool.')
        ->toContain('Available places: Music studio [studio] (Ground floor), Vocal booth [vocal-booth] (Ground floor), Pool terrace [pool-terrace] (Ground floor), Gallery [gallery] (Upper floor)')
        ->not->toContain('A studio full of keyboards.')
        ->not->toContain('An upper gallery overlooking the city.');
});

it('sees the user outside every room only on the same floor and within 10 m', function (array $user, bool $inSight) {
    $scenario = worldStateScenario();

    sendWorldMessage($this, $scenario, ['user' => $user, 'residents' => [$scenario[4]->id => ['x' => 20, 'y' => 0, 'z' => 20]]])->assertSuccessful();

    expect(str_contains(sentSystemPrompt(), 'The user is: somewhere out of sight'))->toBe(! $inSight);
})->with([
    'close by' => [['x' => 26, 'y' => 0, 'z' => 26], true],
    'far away' => [['x' => 40, 'y' => 0, 'z' => 20], false],
    'on the floor above' => [['x' => 20, 'y' => 5, 'z' => 20], false],
]);

it('keeps today\'s prompt for a world without markers', function () {
    $scenario = worldStateScenario(['layout' => null]);
    $residentId = $scenario[4]->id;

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 0, 'y' => 0, 'z' => 0],
        'residents' => [$residentId => ['x' => 1, 'y' => 0, 'z' => 1]],
    ])->assertSuccessful();

    expect(sentSystemPrompt())->not->toContain('World state:');
});

it('keeps the world state under 2,000 characters for a world with 40 zones', function () {
    $zones = collect(range(1, 40))->map(fn (int $n) => [
        'id' => "room-{$n}", 'name' => "Guest room number {$n}", 'description' => str_repeat('A long description of this room. ', 10),
        'floorId' => null, 'parentId' => null, 'private' => false,
        'outline' => [[$n * 10, 0], [$n * 10 + 9, 0], [$n * 10 + 9, 9], [$n * 10, 9]], 'minY' => -1, 'maxY' => 4,
        'entry' => ['x' => $n * 10 + 1, 'y' => 0, 'z' => 1], 'activities' => [],
    ])->all();
    $scenario = worldStateScenario(['layout' => ['floors' => [], 'zones' => $zones, 'objects' => []]]);
    $residentId = $scenario[4]->id;

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 15, 'y' => 0, 'z' => 5],
        'residents' => [$residentId => ['x' => 25, 'y' => 0, 'z' => 5]],
    ])->assertSuccessful();

    $prompt = sentSystemPrompt();
    $places = substr($prompt, strpos($prompt, 'Places in this world:'));
    $places = explode("\n\n", $places)[0];
    $state = substr($prompt, strpos($prompt, 'World state:'));
    $state = explode("\n\n", $state)[0];
    expect($places)->toContain('Guest room number 40')
        ->and(strlen($places))->toBeLessThan(2000)
        ->and($state)->not->toContain('Guest room number 40');
});

it('rejects a world session that does not belong to the user', function () {
    $scenario = worldStateScenario();
    $otherSession = WorldSession::factory()->create();
    $scenario[5] = $otherSession;

    sendWorldMessage($this, $scenario, ['user' => ['x' => 0, 'y' => 0, 'z' => 0]])->assertNotFound();
});

it('includes her recent activity, newest first, limited to the last eight', function () {
    $scenario = worldStateScenario();
    [, , , , $resident, $session] = $scenario;
    foreach (range(1, 9) as $minutesAgo) {
        ResidentActivity::factory()->finished()->create([
            'world_session_id' => $session->id,
            'world_resident_id' => $resident->id,
            'target' => "place-{$minutesAgo}",
            'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }
    ResidentActivity::factory()->failed('there is no way to get there')->create([
        'world_session_id' => $session->id,
        'world_resident_id' => $resident->id,
        'target' => 'pool-terrace',
        'zone_id' => 'studio',
        'created_at' => now(),
    ]);

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -3],
        'residents' => [$resident->id => ['x' => -5, 'y' => 0, 'z' => 2]],
    ])->assertSuccessful();

    $prompt = sentSystemPrompt();
    expect($prompt)
        ->toContain('Your recent activity, newest first:')
        ->toContain('- walked toward pool-terrace, from Music studio: failed (there is no way to get there), just now')
        ->toContain('- walked toward place-1: completed, 1 min ago')
        ->toContain('place-7')
        ->not->toContain('place-8')
        ->not->toContain('place-9');
    expect(strpos($prompt, 'pool-terrace, from'))->toBeLessThan(strpos($prompt, 'place-1:'));
});

it('tells the resident her body moves only through her world tools', function () {
    $scenario = worldStateScenario();

    sendWorldMessage($this, $scenario, [])->assertSuccessful();

    expect(sentSystemPrompt())
        ->toContain('World awareness:')
        ->toContain('Your body in this world moves only through your tools. Whenever your reply has you go somewhere')
        ->toContain('A pose tag sets your gesture or expression where you are right now; moving and changing posture come from your tools.')
        ->toContain('People name things loosely; a couch can mean a sofa or the armchairs. Match what they mean to the closest fitting thing, and prefer what is near you.');
});

it('tells the resident what the user is doing', function (array $userState, string $phrase) {
    $scenario = worldStateScenario();

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -4.4],
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -8]],
    ], ['userState' => $userState])->assertSuccessful();

    expect(sentSystemPrompt())->toContain("away from you{$phrase}");
})->with([
    'reclining on a spot' => [['posture' => 'reclining', 'spotId' => 'pool-lounger-1-seat', 'activityId' => 'recline'], ', reclining on the Pool lounger, doing "Recline"'],
    'doing a zone activity' => [['posture' => 'standing', 'activityId' => 'swim'], ', doing "Swim"'],
    'swimming' => [['posture' => 'swimming'], ', swimming'],
    'crouching' => [['posture' => 'crouching'], ', crouching'],
]);

it('tells the resident her own posture and what she is on', function () {
    $scenario = worldStateScenario();

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -4.4],
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -8]],
    ], ['residentState' => ['posture' => 'reclining', 'spotId' => 'pool-lounger-1-seat', 'activityId' => 'recline']])->assertSuccessful();

    expect(sentSystemPrompt())->toContain('Pool terrace, on the Ground floor, reclining on the Pool lounger');
});

it('tells the resident the pose she holds, why she is doing what she is doing and how she put it', function () {
    $scenario = worldStateScenario();
    ResidentActivity::factory()->create([
        'world_session_id' => $scenario[5]->id,
        'world_resident_id' => $scenario[4]->id,
        'source' => 'idle',
        'verb' => 'plan',
        'target' => 'get a drink',
        'reason' => 'I want to forget about today',
        'narration' => '*mixes a gin tonic, then takes it out to the lounger*',
    ]);
    ResidentActivity::factory()->finished()->create([
        'world_session_id' => $scenario[5]->id,
        'world_resident_id' => $scenario[4]->id,
        'source' => 'idle',
        'verb' => 'use',
        'target' => 'pool-lounger-1-seat',
        'activity' => 'recline',
        'reason' => 'step 2 of 2 of get a drink',
    ]);

    sendWorldMessage($this, $scenario, [
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -8]],
    ], ['residentState' => ['posture' => 'reclining', 'spotId' => 'pool-lounger-1-seat', 'activityId' => 'recline', 'pose' => 'content']])->assertSuccessful();

    expect(sentSystemPrompt())
        ->toContain('reclining on the Pool lounger, doing "Recline", holding the pose "content"')
        ->toContain("What you are doing now:\nYou have been in Pool terrace for less than a minute.\nYou have been at it for less than a minute.\nWhy: step 2 of 2 of get a drink\nIn your words when you started: *mixes a gin tonic, then takes it out to the lounger*");
});

it('rejects a resident state naming a spot the world does not have', function () {
    $scenario = worldStateScenario();

    sendWorldMessage($this, $scenario, [
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -8]],
    ], ['residentState' => ['posture' => 'sitting', 'spotId' => 'moon-chair']])->assertUnprocessable()->assertJsonValidationErrors('residentState.spotId');
});

it('adds nothing for a user who is simply standing', function () {
    $scenario = worldStateScenario();

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -4.4],
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -8]],
    ], ['userState' => ['posture' => 'standing']])->assertSuccessful();

    expect(sentSystemPrompt())->toContain('away from you')->not->toContain('away from you, ');
});

it('rejects a user state naming a spot or activity the world does not have', function (array $userState, string $field) {
    $scenario = worldStateScenario();

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -4.4],
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -8]],
    ], ['userState' => $userState])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'unknown spot' => [['posture' => 'sitting', 'spotId' => 'moon-chair'], 'userState.spotId'],
    'activity the spot does not offer' => [['posture' => 'sitting', 'spotId' => 'pool-lounger-1-seat', 'activityId' => 'swim'], 'userState.activityId'],
    'activity no place offers' => [['posture' => 'standing', 'activityId' => 'fly'], 'userState.activityId'],
]);

it('ignores the user state in a world without markers', function () {
    $scenario = worldStateScenario(['layout' => null]);

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -4.4],
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -8]],
    ], ['userState' => ['posture' => 'sitting', 'spotId' => 'moon-chair']])->assertSuccessful();
});

it('tells the resident who is lying on top of her and whom she lies on top of', function (array $holders, string $phrase) {
    $scenario = worldStateScenario();
    $holders = array_map(fn (string $holder) => $holder === 'resident' ? (string) $scenario[4]->id : $holder, $holders);

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -4.4],
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -8]],
    ], ['stackedSpots' => [['spotId' => 'pool-lounger-1-seat', 'holders' => $holders]]])->assertSuccessful();

    expect(sentSystemPrompt())->toContain("Sharing your spot: {$phrase}");
})->with([
    'the user on top of her' => [['resident', 'user'], 'the user is lying on top of you on the Pool lounger'],
    'her on top of the user' => [['user', 'resident'], 'you are lying on top of the user on the Pool lounger'],
]);

it('says nothing about a shared spot the resident is not in', function () {
    $scenario = worldStateScenario();

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -4.4],
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -8]],
    ], ['stackedSpots' => [['spotId' => 'pool-lounger-1-seat', 'holders' => ['user', '999']]]])->assertSuccessful();

    expect(sentSystemPrompt())->not->toContain('Sharing your spot');
});

it('rejects a shared spot the world does not have', function () {
    $scenario = worldStateScenario();

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -4.4],
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -8]],
    ], ['stackedSpots' => [['spotId' => 'moon-bed', 'holders' => [(string) $scenario[4]->id, 'user']]]])
        ->assertUnprocessable()->assertJsonValidationErrors('stackedSpots.0.spotId');
});
