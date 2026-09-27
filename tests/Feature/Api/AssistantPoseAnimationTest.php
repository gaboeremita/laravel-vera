<?php

use App\Actions\DeleteAssistantAssets;
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

/**
 * A second assistant whose pose plays the same stored animation file as the given pose, as copied poses do.
 */
function shareAnimationWith(Pose $pose): Pose
{
    $other = Assistant::factory()->create(['portrait_type' => 'avatar3d']);
    $copy = Pose::factory()->create(['assistant_id' => $other->id, 'name' => $pose->name]);
    $file = $pose->fresh()->animationFile;
    $copy->animationFile()->create(['path' => $file->path, 'disk' => $file->disk, 'mime_type' => $file->mime_type, 'size' => $file->size, 'original_name' => $file->original_name]);

    return $copy;
}

it('keeps a stored animation another pose still plays when a pose, its animation or its assistant goes', function (string $removal) {
    [$user, $assistant, $pose] = setUpPoseForAnimation();
    $this->actingAs($user)
        ->postJson(route('assistants.poses.animation.store', ['assistant' => $assistant->id, 'pose' => $pose->id]), ['animation' => UploadedFile::fake()->create('spin.fbx', 512)])
        ->assertStatus(201);
    $path = $pose->fresh()->animationFile->path;
    $copy = shareAnimationWith($pose);

    match ($removal) {
        'pose' => $this->actingAs($user)->deleteJson(route('assistants.poses.destroy', ['assistant' => $assistant->id, 'pose' => $pose->id]))->assertOk(),
        'animation' => $this->actingAs($user)->deleteJson(route('assistants.poses.animation.destroy', ['assistant' => $assistant->id, 'pose' => $pose->id]))->assertOk(),
        'replacement' => $this->actingAs($user)->postJson(route('assistants.poses.animation.store', ['assistant' => $assistant->id, 'pose' => $pose->id]), ['animation' => UploadedFile::fake()->createWithContent('spin2.fbx', 'another animation')])->assertStatus(201),
        'assistant' => app(DeleteAssistantAssets::class)->handle($assistant),
    };

    Storage::disk('public')->assertExists($path);
    expect($copy->fresh()->animationFile->path)->toBe($path);

    $copy->animationFile->delete();
    PoseAnimationFile::releaseStorage('public', $path);
    Storage::disk('public')->assertMissing($path);
})->with(['pose', 'animation', 'replacement', 'assistant']);

/**
 * @return array{User, Assistant, Pose}
 */
function secondAssistantPoseFor(User $user): array
{
    $assistant = Assistant::factory()->create(['portrait_type' => 'avatar3d']);
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $assistant->id]);

    return [$user, $assistant, Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'wave'])];
}

it('shares one stored file between poses given identical animations', function () {
    [$user, $assistant, $pose] = setUpPoseForAnimation();
    [, $otherAssistant, $otherPose] = secondAssistantPoseFor($user);

    $first = $this->actingAs($user)
        ->postJson(route('assistants.poses.animation.store', ['assistant' => $assistant->id, 'pose' => $pose->id]), ['animation' => UploadedFile::fake()->createWithContent('wave.fbx', 'wave animation')])
        ->assertStatus(201);
    $second = $this->actingAs($user)
        ->postJson(route('assistants.poses.animation.store', ['assistant' => $otherAssistant->id, 'pose' => $otherPose->id]), ['animation' => UploadedFile::fake()->createWithContent('wave-copy.fbx', 'wave animation')])
        ->assertStatus(201);

    expect($second->json('animation_url'))->toBe($first->json('animation_url'));
    expect(Storage::disk('public')->allFiles())->toBe([$pose->fresh()->animationFile->path]);
    expect($otherPose->fresh()->animationFile->original_name)->toBe('wave-copy.fbx');
});

it('stores different animations as different files', function () {
    [$user, $assistant, $pose] = setUpPoseForAnimation();
    [, $otherAssistant, $otherPose] = secondAssistantPoseFor($user);

    $first = $this->actingAs($user)
        ->postJson(route('assistants.poses.animation.store', ['assistant' => $assistant->id, 'pose' => $pose->id]), ['animation' => UploadedFile::fake()->createWithContent('wave.fbx', 'wave animation')])
        ->assertStatus(201);
    $second = $this->actingAs($user)
        ->postJson(route('assistants.poses.animation.store', ['assistant' => $otherAssistant->id, 'pose' => $otherPose->id]), ['animation' => UploadedFile::fake()->createWithContent('wave.fbx', 'another wave')])
        ->assertStatus(201);

    expect($second->json('animation_url'))->not->toBe($first->json('animation_url'));
    expect(Storage::disk('public')->allFiles())->toHaveCount(2);
});

it('keeps a shared upload until the last pose using it lets it go', function () {
    [$user, $assistant, $pose] = setUpPoseForAnimation();
    [, $otherAssistant, $otherPose] = secondAssistantPoseFor($user);
    foreach ([[$assistant, $pose], [$otherAssistant, $otherPose]] as [$owner, $target]) {
        $this->actingAs($user)
            ->postJson(route('assistants.poses.animation.store', ['assistant' => $owner->id, 'pose' => $target->id]), ['animation' => UploadedFile::fake()->createWithContent('wave.vrma', 'wave animation')])
            ->assertStatus(201);
    }
    $path = $pose->fresh()->animationFile->path;

    $this->actingAs($user)->deleteJson(route('assistants.poses.animation.destroy', ['assistant' => $assistant->id, 'pose' => $pose->id]))->assertOk();
    Storage::disk('public')->assertExists($path);

    $this->actingAs($user)->deleteJson(route('assistants.poses.animation.destroy', ['assistant' => $otherAssistant->id, 'pose' => $otherPose->id]))->assertOk();
    Storage::disk('public')->assertMissing($path);
});

it('keeps the file when a pose is given the same animation again', function () {
    [$user, $assistant, $pose] = setUpPoseForAnimation();
    foreach (['wave.fbx', 'wave-again.fbx'] as $name) {
        $this->actingAs($user)
            ->postJson(route('assistants.poses.animation.store', ['assistant' => $assistant->id, 'pose' => $pose->id]), ['animation' => UploadedFile::fake()->createWithContent($name, 'wave animation')])
            ->assertStatus(201);
    }

    Storage::disk('public')->assertExists($pose->fresh()->animationFile->path);
});
