<?php

use App\Models\User;
use App\Models\World;
use App\Models\WorldSession;
use App\Models\WorldUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: \App\Models\Assistant, 2: \App\Models\Conversation, 3: World, 4: \App\Models\WorldResident, 5: WorldSession}
 */
function worldStateScenario(array $worldAttributes = []): array
{
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant');
    $world = World::factory()->forUser($user)->withLayout()->create($worldAttributes);
    $resident = $world->residents()->create([
        'assistant_id' => $assistant->id,
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
    ]);
    $worldUser = WorldUser::where('world_id', $world->id)->where('user_id', $user->id)->firstOrFail();
    $session = WorldSession::factory()->create(['world_user_id' => $worldUser->id]);

    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Right here.'))]);

    return [$user, $assistant, $conversation, $world, $resident, $session];
}

function sendWorldMessage($test, array $scenario, array $positions): Illuminate\Testing\TestResponse
{
    [$user, $assistant, $conversation, $world, , $session] = $scenario;

    return $test->actingAs($user)->postJson(route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]), [
        'messages' => [['role' => 'user', 'content' => 'Where are you, and where am I?']],
        'worldId' => $world->id,
        'worldSessionId' => $session->id,
        'positions' => $positions,
    ]);
}

function sentSystemPrompt(): string
{
    $prompt = '';
    Http::assertSent(function ($request) use (&$prompt) {
        $prompt = collect($request['messages'] ?? [])->firstWhere('role', 'system')['content'] ?? '';

        return true;
    });

    return $prompt;
}

it('tells the resident her zone, the user\'s zone and their distance', function () {
    $scenario = worldStateScenario();
    $residentId = $scenario[4]->id;

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -3],
        'residents' => [$residentId => ['x' => -2, 'y' => 0, 'z' => 9]],
    ])->assertSuccessful();

    $prompt = sentSystemPrompt();
    expect($prompt)
        ->toContain('World state:')
        ->toContain('You are in: Vocal booth, inside Music studio, on the Ground floor.')
        ->toContain('The user is in: Pool terrace, on the Ground floor, about 14 m away from you.');
});

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
        ->toContain('Other places: Music studio [studio] (Ground floor); Vocal booth [vocal-booth] (Ground floor); Gallery [gallery] (Upper floor)')
        ->not->toContain('A studio full of keyboards.')
        ->not->toContain('An upper gallery overlooking the city.');
});

it('says when the user is on another floor', function () {
    $scenario = worldStateScenario();
    $residentId = $scenario[4]->id;

    sendWorldMessage($this, $scenario, [
        'user' => ['x' => 0, 'y' => 5, 'z' => 5],
        'residents' => [$residentId => ['x' => -5, 'y' => 0, 'z' => 2]],
    ])->assertSuccessful();

    expect(sentSystemPrompt())->toContain('The user is upstairs, in: Gallery, on the Upper floor');
});

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
    $section = substr($prompt, strpos($prompt, 'World state:'));
    $section = explode("\n\n", $section)[0];
    expect($section)->toContain('Guest room number 40')
        ->and(strlen($section))->toBeLessThan(2000);
});

it('rejects a world session that does not belong to the user', function () {
    $scenario = worldStateScenario();
    $otherSession = WorldSession::factory()->create();
    $scenario[5] = $otherSession;

    sendWorldMessage($this, $scenario, ['user' => ['x' => 0, 'y' => 0, 'z' => 0]])->assertNotFound();
});
