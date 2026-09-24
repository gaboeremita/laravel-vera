<?php

use App\Enums\Posture;
use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\Pose;
use App\Models\User;
use App\Models\World;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Assistant, 2: Conversation, 3: World, 4: WorldResident, 5: WorldSession}
 */
function postureScenario(): array
{
    $scenario = worldStateScenario();
    $scenario[1]->update(['portrait_type' => 'avatar3d']);
    Pose::factory()->create(['assistant_id' => $scenario[1]->id, 'name' => 'laugh']);
    Pose::factory()->create(['assistant_id' => $scenario[1]->id, 'name' => 'dance']);
    Pose::factory()->posture(Posture::Sitting)->create(['assistant_id' => $scenario[1]->id, 'name' => 'laugh']);

    return $scenario;
}

it('lists the poses that fit her posture and the ones that make her stand up', function () {
    $scenario = postureScenario();

    sendWorldMessage($this, $scenario, [], ['residentPosture' => 'sitting'])->assertSuccessful();

    expect(sentSystemPrompt())
        ->toContain('You are sitting.')
        ->toContain("Available poses:\nRegular: laugh\nRestricted: \n")
        ->toContain("Poses that make you stand up:\nRegular: dance");
});

it('assumes she is standing when no posture is sent', function () {
    $scenario = postureScenario();

    sendWorldMessage($this, $scenario, [])->assertSuccessful();

    expect(sentSystemPrompt())
        ->toContain("Available poses:\nRegular: laugh, dance")
        ->not->toContain('Poses that make you stand up');
});

it('rejects an unknown posture', function () {
    $scenario = postureScenario();

    sendWorldMessage($this, $scenario, [], ['residentPosture' => 'floating'])->assertUnprocessable()->assertJsonValidationErrors(['residentPosture']);
});

it('lists only the poses that fit while she swims', function () {
    $scenario = postureScenario();
    Pose::factory()->posture(Posture::Swimming)->create(['assistant_id' => $scenario[1]->id, 'name' => 'splash']);

    sendWorldMessage($this, $scenario, [], ['residentPosture' => 'swimming'])->assertSuccessful();

    expect(sentSystemPrompt())
        ->toContain('You are swimming.')
        ->toContain("Available poses:\nRegular: splash")
        ->not->toContain('Poses that make you stand up');
});

it('lists restricted poses apart from the regular ones', function () {
    $scenario = postureScenario();
    Pose::factory()->restricted()->create(['assistant_id' => $scenario[1]->id, 'name' => 'tease']);
    Pose::factory()->posture(Posture::Sitting)->restricted()->create(['assistant_id' => $scenario[1]->id, 'name' => 'lean-in']);

    sendWorldMessage($this, $scenario, [], ['residentPosture' => 'sitting'])->assertSuccessful();

    expect(sentSystemPrompt())
        ->toContain("Available poses:\nRegular: laugh\nRestricted: lean-in")
        ->toContain("Poses that make you stand up:\nRegular: dance\nRestricted: tease");
});
