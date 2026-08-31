<?php

use App\Enums\AssistantKind;
use App\Enums\AssistantPortraitType;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\User;
use App\Models\VrmFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('creates NPCs through the assistant persistence flow', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('npcs.store'), [
        'name' => 'Station Attendant',
        'slug' => 'station-attendant',
        'description' => 'Welcomes visitors.',
        'portrait_type' => AssistantPortraitType::Avatar3D->value,
        'prompt' => ['identity' => ['Helpful resident']],
    ]);

    $response->assertCreated()->assertJsonPath('name', 'Station Attendant');
    $npc = Assistant::where('slug', 'station-attendant')->firstOrFail();

    expect($npc->kind)->toBe(AssistantKind::WorldNpc);
    expect(AssistantUser::where('user_id', $user->id)->where('assistant_id', $npc->id)->exists())->toBeTrue();
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
