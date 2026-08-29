<?php

use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Image;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function setUpAssistantForImage(): array
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

it('uploads a card image and returns 201 with image_url', function () {
    [$user, $assistant] = setUpAssistantForImage();

    $file = UploadedFile::fake()->image('card.png');

    $response = $this->actingAs($user)
        ->postJson(route('assistants.image.store', ['id' => $assistant->id]), [
            'image' => $file,
        ]);

    $response->assertStatus(201)->assertJsonStructure(['image_url']);
    expect($assistant->fresh()->cardImage)->not->toBeNull();
});

it('rejects a non-image file with 422', function () {
    [$user, $assistant] = setUpAssistantForImage();

    $file = UploadedFile::fake()->create('not-an-image.txt', 10);

    $response = $this->actingAs($user)
        ->postJson(route('assistants.image.store', ['id' => $assistant->id]), [
            'image' => $file,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['image']);
});

it('scopes card image upload to owner — another user gets 404', function () {
    [$user, $assistant] = setUpAssistantForImage();
    $otherUser = User::factory()->create();

    $file = UploadedFile::fake()->image('card.png');

    $response = $this->actingAs($otherUser)
        ->postJson(route('assistants.image.store', ['id' => $assistant->id]), [
            'image' => $file,
        ]);

    $response->assertStatus(404);
});

it('replaces an existing card image on re-upload', function () {
    [$user, $assistant] = setUpAssistantForImage();

    $first = UploadedFile::fake()->image('first.png');
    $this->actingAs($user)
        ->postJson(route('assistants.image.store', ['id' => $assistant->id]), ['image' => $first])
        ->assertStatus(201);

    $second = UploadedFile::fake()->image('second.png');
    $this->actingAs($user)
        ->postJson(route('assistants.image.store', ['id' => $assistant->id]), ['image' => $second])
        ->assertStatus(201);

    expect(Image::where('imageable_id', $assistant->id)->where('imageable_type', Assistant::class)->count())->toBe(1);
    expect($assistant->fresh()->cardImage->original_name)->toBe('second.png');
});

it('deletes the card image and returns 200', function () {
    [$user, $assistant] = setUpAssistantForImage();

    $file = UploadedFile::fake()->image('card.png');
    $this->actingAs($user)
        ->postJson(route('assistants.image.store', ['id' => $assistant->id]), ['image' => $file])
        ->assertStatus(201);

    $response = $this->actingAs($user)
        ->deleteJson(route('assistants.image.destroy', ['id' => $assistant->id]));

    $response->assertStatus(200);
    expect($assistant->fresh()->cardImage)->toBeNull();
});

it('returns 404 when deleting a non-existent card image', function () {
    [$user, $assistant] = setUpAssistantForImage();

    $response = $this->actingAs($user)
        ->deleteJson(route('assistants.image.destroy', ['id' => $assistant->id]));

    $response->assertStatus(404);
});

it('show returns vrm_original_name and image_url', function () {
    [$user, $assistant] = setUpAssistantForImage();

    $response = $this->actingAs($user)
        ->getJson(route('assistants.show', ['id' => $assistant->id]));

    $response->assertStatus(200)
        ->assertJsonStructure(['vrm_original_name', 'image_url']);
});
