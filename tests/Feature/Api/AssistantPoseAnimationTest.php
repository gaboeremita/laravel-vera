<?php

use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Pose;
use App\Models\PoseAnimationFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{User, Assistant, Pose}
 */
function setUpPoseForAnimation(): array
{
    Storage::fake('public');

    $user = User::factory()->create();
    $assistant = Assistant::factory()->create(['portrait_type' => 'avatar3d']);
    AssistantUser::factory()->create([
        'user_id' => $user->id,
        'assistant_id' => $assistant->id,
    ]);
    $pose = Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'spin']);

    return [$user, $assistant, $pose];
}

it('uploads a .vrma animation file and returns 201 with animation_url', function () {
    [$user, $assistant, $pose] = setUpPoseForAnimation();

    $file = UploadedFile::fake()->create('spin.vrma', 1024);

    $response = $this->actingAs($user)
        ->postJson(route('assistants.poses.animation.store', ['assistant' => $assistant->id, 'pose' => $pose->id]), [
            'animation' => $file,
        ]);

    $response->assertStatus(201)->assertJsonStructure(['animation_url']);
    expect($pose->fresh()->animationFile)->not->toBeNull();
});

it('uploads a Mixamo .fbx animation file and returns 201 with animation_url', function () {
    [$user, $assistant, $pose] = setUpPoseForAnimation();

    $file = UploadedFile::fake()->create('jump.fbx', 1024);

    $response = $this->actingAs($user)
        ->postJson(route('assistants.poses.animation.store', ['assistant' => $assistant->id, 'pose' => $pose->id]), [
            'animation' => $file,
        ]);

    $response->assertStatus(201)->assertJsonStructure(['animation_url']);
    expect($pose->fresh()->animationFile)->not->toBeNull();
});

it('rejects an animation file larger than 10 MB with 422', function () {
    [$user, $assistant, $pose] = setUpPoseForAnimation();

    $file = UploadedFile::fake()->create('big.vrma', 10241);

    $response = $this->actingAs($user)
        ->postJson(route('assistants.poses.animation.store', ['assistant' => $assistant->id, 'pose' => $pose->id]), [
            'animation' => $file,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['animation']);
});

it('rejects a file that is neither .vrma nor .fbx with 422', function () {
    [$user, $assistant, $pose] = setUpPoseForAnimation();

    $file = UploadedFile::fake()->create('image.png', 512);

    $response = $this->actingAs($user)
        ->postJson(route('assistants.poses.animation.store', ['assistant' => $assistant->id, 'pose' => $pose->id]), [
            'animation' => $file,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['animation']);
});

it('scopes animation upload to owner — another user gets 404', function () {
    [, $assistant, $pose] = setUpPoseForAnimation();
    $otherUser = User::factory()->create();

    $file = UploadedFile::fake()->create('spin.vrma', 512);

    $response = $this->actingAs($otherUser)
        ->postJson(route('assistants.poses.animation.store', ['assistant' => $assistant->id, 'pose' => $pose->id]), [
            'animation' => $file,
        ]);

    $response->assertStatus(404);
});

it('replaces an existing animation file on re-upload', function () {
    [$user, $assistant, $pose] = setUpPoseForAnimation();

    $first = UploadedFile::fake()->create('first.vrma', 512);
    $this->actingAs($user)
        ->postJson(route('assistants.poses.animation.store', ['assistant' => $assistant->id, 'pose' => $pose->id]), ['animation' => $first])
        ->assertStatus(201);

    $second = UploadedFile::fake()->create('second.fbx', 512);
    $this->actingAs($user)
        ->postJson(route('assistants.poses.animation.store', ['assistant' => $assistant->id, 'pose' => $pose->id]), ['animation' => $second])
        ->assertStatus(201);

    expect(PoseAnimationFile::where('pose_id', $pose->id)->count())->toBe(1);
    expect($pose->fresh()->animationFile->original_name)->toBe('second.fbx');
});

it('deletes the animation file and returns 200', function () {
    [$user, $assistant, $pose] = setUpPoseForAnimation();

    $file = UploadedFile::fake()->create('spin.vrma', 512);
    $this->actingAs($user)
        ->postJson(route('assistants.poses.animation.store', ['assistant' => $assistant->id, 'pose' => $pose->id]), ['animation' => $file])
        ->assertStatus(201);

    $response = $this->actingAs($user)
        ->deleteJson(route('assistants.poses.animation.destroy', ['assistant' => $assistant->id, 'pose' => $pose->id]));

    $response->assertStatus(200);
    expect($pose->fresh()->animationFile)->toBeNull();
});

it('returns 404 when deleting a non-existent animation file', function () {
    [$user, $assistant, $pose] = setUpPoseForAnimation();

    $response = $this->actingAs($user)
        ->deleteJson(route('assistants.poses.animation.destroy', ['assistant' => $assistant->id, 'pose' => $pose->id]));

    $response->assertStatus(404);
});

it('deleting a pose also deletes its animation file', function () {
    [$user, $assistant, $pose] = setUpPoseForAnimation();

    $file = UploadedFile::fake()->create('spin.vrma', 512);
    $this->actingAs($user)
        ->postJson(route('assistants.poses.animation.store', ['assistant' => $assistant->id, 'pose' => $pose->id]), ['animation' => $file])
        ->assertStatus(201);

    $animationPath = $pose->fresh()->animationFile->path;

    $this->actingAs($user)
        ->deleteJson(route('assistants.poses.destroy', ['assistant' => $assistant->id, 'pose' => $pose->id]))
        ->assertStatus(200);

    expect(PoseAnimationFile::where('pose_id', $pose->id)->count())->toBe(0);
    Storage::disk('public')->assertMissing($animationPath);
});
