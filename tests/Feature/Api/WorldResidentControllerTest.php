<?php

use App\Enums\AssistantPortraitType;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\User;
use App\Models\VrmFile;
use App\Models\World;
use App\Models\WorldResident;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function residentAssistantFor(User $user): Assistant
{
    $assistant = Assistant::factory()->create(['portrait_type' => AssistantPortraitType::Avatar3D]);
    AssistantUser::factory()->create(['assistant_id' => $assistant->id, 'user_id' => $user->id]);
    VrmFile::factory()->create(['vrmable_id' => $assistant->id, 'vrmable_type' => Assistant::class]);

    return $assistant;
}

it('adds and removes a resident placement without deleting the character', function () {
    $user = User::factory()->create();
    $world = World::factory()->for($user)->create();
    $assistant = residentAssistantFor($user);
    $payload = ['position' => ['x' => 1, 'y' => 0, 'z' => 2], 'behavior' => 'roam', 'behavior_settings' => ['radius' => 1]];

    $this->actingAs($user)->putJson(route('worlds.residents.upsert', [$world, $assistant]), $payload)
        ->assertSuccessful()
        ->assertJsonPath('assistant.id', $assistant->id)
        ->assertJsonPath('behavior', 'roam');

    $this->actingAs($user)->deleteJson(route('worlds.residents.destroy', [$world, $assistant]))->assertNoContent();

    expect(WorldResident::where('world_id', $world->id)->exists())->toBeFalse();
    expect(Assistant::find($assistant->id))->not->toBeNull();
});

it('rejects a resident without a VRM asset', function () {
    $user = User::factory()->create();
    $world = World::factory()->for($user)->create();
    $assistant = Assistant::factory()->create(['portrait_type' => AssistantPortraitType::Avatar3D]);
    AssistantUser::factory()->create(['assistant_id' => $assistant->id, 'user_id' => $user->id]);

    $this->actingAs($user)->putJson(route('worlds.residents.upsert', [$world, $assistant]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
    ])->assertUnprocessable();
});
