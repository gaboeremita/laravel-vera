<?php

use App\Enums\AssistantPortraitType;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Emotion;
use App\Models\User;
use App\Models\VrmFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function setUpAssistantForVrm(): array
{
    Storage::fake('public');

    $user = User::factory()->create();
    $assistant = Assistant::factory()->create();
    AssistantUser::factory()->create([
        'user_id' => $user->id,
        'assistant_id' => $assistant->id,
    ]);

    return [$user, $assistant];
}

it('uploads a VRM file and returns 201 with vrm_url', function () {
    [$user, $assistant] = setUpAssistantForVrm();

    $file = UploadedFile::fake()->create('avatar.vrm', 1024);

    $response = $this->actingAs($user)
        ->postJson(route('assistants.vrm.store', ['id' => $assistant->id]), [
            'vrm' => $file,
        ]);

    $response->assertStatus(201)->assertJsonStructure(['vrm_url']);
    expect($assistant->fresh()->vrm)->not->toBeNull();
});

it('rejects a VRM file larger than 50 MB with 422', function () {
    [$user, $assistant] = setUpAssistantForVrm();

    $file = UploadedFile::fake()->create('big.vrm', 51201);

    $response = $this->actingAs($user)
        ->postJson(route('assistants.vrm.store', ['id' => $assistant->id]), [
            'vrm' => $file,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['vrm']);
});

it('rejects a non-VRM file with 422', function () {
    [$user, $assistant] = setUpAssistantForVrm();

    $file = UploadedFile::fake()->create('image.png', 512);

    $response = $this->actingAs($user)
        ->postJson(route('assistants.vrm.store', ['id' => $assistant->id]), [
            'vrm' => $file,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['vrm']);
});

it('scopes VRM upload to owner — another user gets 404', function () {
    [$user, $assistant] = setUpAssistantForVrm();
    $otherUser = User::factory()->create();

    $file = UploadedFile::fake()->create('avatar.vrm', 512);

    $response = $this->actingAs($otherUser)
        ->postJson(route('assistants.vrm.store', ['id' => $assistant->id]), [
            'vrm' => $file,
        ]);

    $response->assertStatus(404);
});

it('replaces an existing VRM file on re-upload', function () {
    [$user, $assistant] = setUpAssistantForVrm();

    $first = UploadedFile::fake()->create('first.vrm', 512);
    $this->actingAs($user)
        ->postJson(route('assistants.vrm.store', ['id' => $assistant->id]), ['vrm' => $first])
        ->assertStatus(201);

    $second = UploadedFile::fake()->create('second.vrm', 512);
    $this->actingAs($user)
        ->postJson(route('assistants.vrm.store', ['id' => $assistant->id]), ['vrm' => $second])
        ->assertStatus(201);

    expect(VrmFile::where('vrmable_id', $assistant->id)->count())->toBe(1);
    expect($assistant->fresh()->vrm->original_name)->toBe('second.vrm');
});

it('deletes the VRM file and returns 200', function () {
    [$user, $assistant] = setUpAssistantForVrm();

    $file = UploadedFile::fake()->create('avatar.vrm', 512);
    $this->actingAs($user)
        ->postJson(route('assistants.vrm.store', ['id' => $assistant->id]), ['vrm' => $file])
        ->assertStatus(201);

    $response = $this->actingAs($user)
        ->deleteJson(route('assistants.vrm.destroy', ['id' => $assistant->id]));

    $response->assertStatus(200);
    expect($assistant->fresh()->vrm)->toBeNull();
});

it('returns 404 when deleting a non-existent VRM file', function () {
    [$user, $assistant] = setUpAssistantForVrm();

    $response = $this->actingAs($user)
        ->deleteJson(route('assistants.vrm.destroy', ['id' => $assistant->id]));

    $response->assertStatus(404);
});

it('persists portrait_type via PATCH', function () {
    [$user, $assistant] = setUpAssistantForVrm();

    $this->actingAs($user)
        ->patchJson(route('assistants.update', ['id' => $assistant->id]), [
            'portrait_type' => 'avatar3d',
        ])
        ->assertStatus(200);

    expect($assistant->fresh()->portrait_type)->toBe(AssistantPortraitType::Avatar3D);
});

it('creates an avatar3d assistant without emotion images', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('assistants.store'), [
            'name' => 'Avatar Assistant',
            'slug' => 'avatar-assistant',
            'portrait_type' => 'avatar3d',
        ]);

    $response->assertStatus(201);
    $assistant = Assistant::find($response->json('id'));
    expect($assistant->portrait_type)->toBe(AssistantPortraitType::Avatar3D);
    expect($assistant->emotions()->count())->toBe(0);
});

it('still requires emotions when creating an image assistant', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('assistants.store'), [
            'name' => 'Image Assistant',
            'slug' => 'image-assistant',
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['emotions']);
});

it('rejects emotions when creating an avatar3d assistant — poses only', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('assistants.store'), [
            'name' => 'Avatar Assistant',
            'slug' => 'avatar-assistant-no-emotions',
            'portrait_type' => 'avatar3d',
            'emotions' => [['name' => 'happy']],
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['emotions']);
});

it('rejects creating an emotion on an existing avatar3d assistant', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $assistant = Assistant::factory()->create(['portrait_type' => 'avatar3d']);
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $assistant->id]);

    $response = $this->actingAs($user)
        ->postJson(route('assistants.emotions.store', ['assistant' => $assistant->id]), [
            'name' => 'happy',
        ]);

    $response->assertStatus(422);
    expect($assistant->emotions()->count())->toBe(0);
});

it('emotions index returns envelope with portrait_type and vrm_url', function () {
    [$user, $assistant] = setUpAssistantForVrm();

    Emotion::factory()->create([
        'assistant_id' => $assistant->id,
        'name' => 'default',
        'restricted' => false,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('emotions.index', ['assistant' => $assistant->id]));

    $response->assertStatus(200)
        ->assertJsonStructure(['portrait_type', 'vrm_url', 'emotions'])
        ->assertJsonPath('portrait_type', 'image')
        ->assertJsonPath('vrm_url', null);
});
