<?php

use App\Enums\AssistantKind;
use App\Enums\AssistantPortraitType;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\User;
use App\Models\VrmFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('creates NPCs through the assistant persistence flow', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('npcs.store'), [
        'name' => 'Station Attendant',
        'description' => 'Welcomes visitors.',
        'slug' => 'must-be-ignored',
        'mode' => 'agent',
        'portrait_type' => 'image',
        'prompt' => ['identity' => ['Helpful resident']],
        'vrm' => UploadedFile::fake()->create('attendant.vrm', 100, 'application/octet-stream'),
    ]);

    $response->assertCreated()->assertJsonPath('name', 'Station Attendant');
    $npc = Assistant::where('name', 'Station Attendant')->firstOrFail();

    expect($npc->kind)->toBe(AssistantKind::WorldNpc);
    expect($npc->mode->value)->toBe('assistant');
    expect($npc->portrait_type)->toBe(AssistantPortraitType::Avatar3D);
    expect($npc->slug)->toStartWith('station-attendant-');
    expect($npc->slug)->not->toBe('must-be-ignored');
    expect($npc->vrm)->not->toBeNull();
    expect(AssistantUser::where('user_id', $user->id)->where('assistant_id', $npc->id)->exists())->toBeTrue();
});

it('requires a VRM avatar for NPC creation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('npcs.store'), ['name' => 'Station Attendant'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('vrm');
});

it('index exposes vrm_url so NPCs are selectable as world residents', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $npc = Assistant::factory()->create(['kind' => AssistantKind::WorldNpc, 'portrait_type' => AssistantPortraitType::Avatar3D]);
    AssistantUser::factory()->create(['assistant_id' => $npc->id, 'user_id' => $user->id]);
    VrmFile::factory()->create(['vrmable_id' => $npc->id, 'vrmable_type' => Assistant::class, 'disk' => 'public', 'path' => 'vrm/npc.vrm']);

    $response = $this->actingAs($user)->getJson(route('npcs.index'));

    $response->assertOk();
    $payload = collect($response->json())->firstWhere('id', $npc->id);
    expect($payload['vrm_url'])->not->toBeNull();
});

it('does not expose NPCs owned by another user', function () {
    $npc = Assistant::factory()->create(['kind' => AssistantKind::WorldNpc]);
    AssistantUser::factory()->create(['assistant_id' => $npc->id]);
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('npcs.show', $npc))->assertNotFound();
});

it('permanently deletes NPC character assets with the NPC', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $npc = Assistant::factory()->create(['kind' => AssistantKind::WorldNpc]);
    AssistantUser::factory()->create(['assistant_id' => $npc->id, 'user_id' => $user->id]);
    $vrm = VrmFile::factory()->create([
        'vrmable_id' => $npc->id,
        'vrmable_type' => Assistant::class,
        'disk' => 'public',
        'path' => 'vrm/npc.vrm',
    ]);
    Storage::disk('public')->put($vrm->path, 'vrm');

    $this->actingAs($user)->deleteJson(route('npcs.destroy', $npc))->assertNoContent();

    expect(Assistant::find($npc->id))->toBeNull();
    Storage::disk('public')->assertMissing('vrm/npc.vrm');
});
