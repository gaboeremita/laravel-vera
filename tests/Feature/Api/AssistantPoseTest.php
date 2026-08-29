<?php

use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Pose;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function setUpAssistantForPoses(): array
{
    $user = User::factory()->create();
    $assistant = Assistant::factory()->create();
    AssistantUser::factory()->create([
        'user_id' => $user->id,
        'assistant_id' => $assistant->id,
    ]);

    return [$user, $assistant];
}

it('creates a pose with blendshape weights only', function () {
    [$user, $assistant] = setUpAssistantForPoses();

    $response = $this->actingAs($user)
        ->postJson(route('assistants.poses.store', ['assistant' => $assistant->id]), [
            'name' => 'happy-hands',
            'vrm_blendshapes' => [['expression' => 'happy', 'weight' => 80]],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('name', 'happy-hands')
        ->assertJsonPath('vrm_blendshapes.0.expression', 'happy')
        ->assertJsonPath('vrm_blendshapes.0.weight', 0.8)
        ->assertJsonPath('animation_url', null);
});

it('creates a pose with no blendshapes', function () {
    [$user, $assistant] = setUpAssistantForPoses();

    $response = $this->actingAs($user)
        ->postJson(route('assistants.poses.store', ['assistant' => $assistant->id]), [
            'name' => 'spin',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('name', 'spin')
        ->assertJsonPath('vrm_blendshapes', null);
});

it('rejects a duplicate pose name on the same assistant', function () {
    [$user, $assistant] = setUpAssistantForPoses();
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'spin']);

    $response = $this->actingAs($user)
        ->postJson(route('assistants.poses.store', ['assistant' => $assistant->id]), [
            'name' => 'spin',
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('scopes pose creation to owner — another user gets 404', function () {
    [, $assistant] = setUpAssistantForPoses();
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser)
        ->postJson(route('assistants.poses.store', ['assistant' => $assistant->id]), [
            'name' => 'spin',
        ]);

    $response->assertStatus(404);
});

it('updates a pose name and blendshapes independently', function () {
    [$user, $assistant] = setUpAssistantForPoses();
    $pose = Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'spin']);

    $response = $this->actingAs($user)
        ->postJson(route('assistants.poses.update', ['assistant' => $assistant->id, 'pose' => $pose->id]), [
            'name' => 'twirl',
            'vrm_blendshapes' => [['expression' => 'happy', 'weight' => 50]],
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('name', 'twirl')
        ->assertJsonPath('vrm_blendshapes.0.weight', 0.5);
});

it('rejects renaming a pose to a name that already exists on the assistant', function () {
    [$user, $assistant] = setUpAssistantForPoses();
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'spin']);
    $pose = Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'dance']);

    $response = $this->actingAs($user)
        ->postJson(route('assistants.poses.update', ['assistant' => $assistant->id, 'pose' => $pose->id]), [
            'name' => 'spin',
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('deletes a pose', function () {
    [$user, $assistant] = setUpAssistantForPoses();
    $pose = Pose::factory()->create(['assistant_id' => $assistant->id]);

    $response = $this->actingAs($user)
        ->deleteJson(route('assistants.poses.destroy', ['assistant' => $assistant->id, 'pose' => $pose->id]));

    $response->assertStatus(200);
    expect(Pose::find($pose->id))->toBeNull();
});

it('scopes pose deletion to owner — another user gets 404', function () {
    [, $assistant] = setUpAssistantForPoses();
    $pose = Pose::factory()->create(['assistant_id' => $assistant->id]);
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser)
        ->deleteJson(route('assistants.poses.destroy', ['assistant' => $assistant->id, 'pose' => $pose->id]));

    $response->assertStatus(404);
});

it('creates poses (with and without an animation file) at assistant creation time', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('assistants.store'), [
            'name' => 'Avatar Assistant',
            'slug' => 'avatar-assistant-poses',
            'portrait_type' => 'avatar3d',
            'poses' => [
                ['name' => 'happy-hands', 'vrm_blendshapes' => [['expression' => 'happy', 'weight' => 80]]],
                ['name' => 'spin', 'animation' => UploadedFile::fake()->create('spin.vrma', 512)],
            ],
        ]);

    $response->assertStatus(201);
    $assistant = Assistant::find($response->json('id'));
    expect($assistant->poses()->count())->toBe(2);

    $spin = $assistant->poses()->where('name', 'spin')->first();
    expect($spin->animationFile)->not->toBeNull();

    $happyHands = $assistant->poses()->where('name', 'happy-hands')->first();
    expect($happyHands->vrm_blendshapes[0]['weight'])->toBe(0.8);
});

it('assistants.show includes poses', function () {
    [$user, $assistant] = setUpAssistantForPoses();
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'spin']);

    $response = $this->actingAs($user)
        ->getJson(route('assistants.show', ['id' => $assistant->id]));

    $response->assertStatus(200)
        ->assertJsonStructure(['poses'])
        ->assertJsonPath('poses.0.name', 'spin');
});

it('emotions index returns poses alongside emotions', function () {
    [$user, $assistant] = setUpAssistantForPoses();
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'spin']);

    $response = $this->actingAs($user)
        ->getJson(route('emotions.index', ['assistant' => $assistant->id]));

    $response->assertStatus(200)
        ->assertJsonStructure(['portrait_type', 'vrm_url', 'emotions', 'poses'])
        ->assertJsonPath('poses.0.name', 'spin');
});
