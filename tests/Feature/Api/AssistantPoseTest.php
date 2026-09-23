<?php

use App\Enums\Posture;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Pose;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{User, Assistant}
 */
function setUpAssistantForPoses(): array
{
    $user = User::factory()->create();
    $assistant = Assistant::factory()->create(['portrait_type' => 'avatar3d']);
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

it('rejects pose creation for an image-portrait assistant', function () {
    $user = User::factory()->create();
    $assistant = Assistant::factory()->create(['portrait_type' => 'image']);
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $assistant->id]);

    $response = $this->actingAs($user)
        ->postJson(route('assistants.poses.store', ['assistant' => $assistant->id]), [
            'name' => 'spin',
        ]);

    $response->assertStatus(422);
    expect($assistant->poses()->count())->toBe(0);
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

it('creates poses as standing unless a posture is given', function () {
    [$user, $assistant] = setUpAssistantForPoses();

    $this->actingAs($user)
        ->postJson(route('assistants.poses.store', ['assistant' => $assistant->id]), ['name' => 'laugh'])
        ->assertCreated()
        ->assertJsonPath('posture', 'standing');

    $this->actingAs($user)
        ->postJson(route('assistants.poses.store', ['assistant' => $assistant->id]), ['name' => 'laugh', 'posture' => 'sitting'])
        ->assertCreated()
        ->assertJsonPath('posture', 'sitting');

    expect(Pose::where('assistant_id', $assistant->id)->where('name', 'laugh')->count())->toBe(2);
});

it('rejects the same pose name twice in one posture', function () {
    [$user, $assistant] = setUpAssistantForPoses();
    Pose::factory()->posture(Posture::Lying)->create(['assistant_id' => $assistant->id, 'name' => 'stretch']);

    $this->actingAs($user)
        ->postJson(route('assistants.poses.store', ['assistant' => $assistant->id]), ['name' => 'stretch', 'posture' => 'lying'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects an unknown posture', function () {
    [$user, $assistant] = setUpAssistantForPoses();

    $this->actingAs($user)
        ->postJson(route('assistants.poses.store', ['assistant' => $assistant->id]), ['name' => 'float', 'posture' => 'floating'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['posture']);
});

it('moves a pose to another posture unless that posture already has the name', function () {
    [$user, $assistant] = setUpAssistantForPoses();
    $pose = Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'wave']);
    Pose::factory()->posture(Posture::Reclining)->create(['assistant_id' => $assistant->id, 'name' => 'nap']);

    $this->actingAs($user)
        ->postJson(route('assistants.poses.update', ['assistant' => $assistant->id, 'pose' => $pose->id]), ['posture' => 'sitting'])
        ->assertSuccessful()
        ->assertJsonPath('posture', 'sitting');

    $this->actingAs($user)
        ->postJson(route('assistants.poses.update', ['assistant' => $assistant->id, 'pose' => $pose->id]), ['name' => 'nap', 'posture' => 'reclining'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('keeps a separate default pose for each posture', function () {
    [$user, $assistant] = setUpAssistantForPoses();

    $this->actingAs($user)
        ->postJson(route('assistants.poses.default.update', ['assistant' => $assistant->id]), ['vrm_blendshapes' => [['expression' => 'relaxed', 'weight' => 40]]])
        ->assertSuccessful()
        ->assertJsonPath('posture', 'standing');

    $this->actingAs($user)
        ->postJson(route('assistants.poses.default.update', ['assistant' => $assistant->id]), ['posture' => 'sitting', 'vrm_blendshapes' => [['expression' => 'happy', 'weight' => 60]]])
        ->assertSuccessful()
        ->assertJsonPath('posture', 'sitting');

    expect(Pose::where('assistant_id', $assistant->id)->where('name', 'default')->pluck('posture')->map->value->sort()->values()->all())
        ->toBe(['sitting', 'standing']);
});

it('uploads and deletes the default animation of one posture', function () {
    Storage::fake('public');
    [$user, $assistant] = setUpAssistantForPoses();

    $this->actingAs($user)
        ->postJson(route('assistants.poses.default.animation.store', ['assistant' => $assistant->id]), [
            'posture' => 'lying',
            'animation' => UploadedFile::fake()->create('lie.vrma', 10),
        ])
        ->assertCreated()
        ->assertJsonPath('posture', 'lying');

    expect(Pose::where('assistant_id', $assistant->id)->where('name', 'default')->where('posture', 'lying')->first()->animationFile)->not->toBeNull();

    $this->actingAs($user)
        ->deleteJson(route('assistants.poses.default.animation.destroy', ['assistant' => $assistant->id, 'posture' => 'standing']))
        ->assertNotFound();

    $this->actingAs($user)
        ->deleteJson(route('assistants.poses.default.animation.destroy', ['assistant' => $assistant->id, 'posture' => 'lying']))
        ->assertSuccessful();
});

it('returns only standing poses to the chat portrait', function () {
    [$user, $assistant] = setUpAssistantForPoses();
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'laugh']);
    Pose::factory()->posture(Posture::Sitting)->create(['assistant_id' => $assistant->id, 'name' => 'laugh']);

    $this->actingAs($user)
        ->getJson(route('emotions.index', ['assistant' => $assistant->id]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'poses');
});
