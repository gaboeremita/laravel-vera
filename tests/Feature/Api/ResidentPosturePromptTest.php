<?php

use App\Enums\Posture;
use App\Models\Pose;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: \App\Models\User, 1: \App\Models\Assistant, 2: \App\Models\Conversation, 3: \App\Models\World, 4: \App\Models\WorldResident, 5: \App\Models\WorldSession}
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
        ->toContain('Available poses: laugh')
        ->toContain('Poses that make you stand up: dance');
});

it('assumes she is standing when no posture is sent', function () {
    $scenario = postureScenario();

    sendWorldMessage($this, $scenario, [])->assertSuccessful();

    expect(sentSystemPrompt())
        ->toContain('Available poses: laugh, dance')
        ->not->toContain('Poses that make you stand up');
});

it('rejects an unknown posture', function () {
    $scenario = postureScenario();

    sendWorldMessage($this, $scenario, [], ['residentPosture' => 'floating'])->assertUnprocessable()->assertJsonValidationErrors(['residentPosture']);
});
