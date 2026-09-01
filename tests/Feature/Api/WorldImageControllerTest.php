<?php

use App\Models\Image;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function setUpWorldForImage(): array
{
    Storage::fake('public');

    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();

    return [$user, $world];
}

it('uploads a card image and returns 201 with image_url', function () {
    [$user, $world] = setUpWorldForImage();

    $response = $this->actingAs($user)->postJson(route('worlds.image.card.store', $world), [
        'image' => UploadedFile::fake()->image('card.png'),
    ]);

    $response->assertStatus(201)->assertJsonStructure(['image_url']);
    expect($world->fresh()->cardImage)->not->toBeNull();
});

it('uploads a portrait image and returns 201 with image_url', function () {
    [$user, $world] = setUpWorldForImage();

    $response = $this->actingAs($user)->postJson(route('worlds.image.portrait.store', $world), [
        'image' => UploadedFile::fake()->image('portrait.png'),
    ]);

    $response->assertStatus(201)->assertJsonStructure(['image_url']);
    expect($world->fresh()->portraitImage)->not->toBeNull();
});

it('keeps card and portrait images independent of each other', function () {
    [$user, $world] = setUpWorldForImage();

    $this->actingAs($user)->postJson(route('worlds.image.card.store', $world), ['image' => UploadedFile::fake()->image('card.png')])->assertStatus(201);
    $this->actingAs($user)->postJson(route('worlds.image.portrait.store', $world), ['image' => UploadedFile::fake()->image('portrait.png')])->assertStatus(201);

    $fresh = $world->fresh();
    expect($fresh->cardImage->original_name)->toBe('card.png');
    expect($fresh->portraitImage->original_name)->toBe('portrait.png');
    expect(Image::where('imageable_id', $world->id)->where('imageable_type', World::class)->count())->toBe(2);
});

it('replaces an existing card image rather than duplicating it', function () {
    [$user, $world] = setUpWorldForImage();

    $this->actingAs($user)->postJson(route('worlds.image.card.store', $world), ['image' => UploadedFile::fake()->image('first.png')])->assertStatus(201);
    $this->actingAs($user)->postJson(route('worlds.image.card.store', $world), ['image' => UploadedFile::fake()->image('second.png')])->assertStatus(201);

    expect(Image::where('imageable_id', $world->id)->where('imageable_type', World::class)->where('role', 'card')->count())->toBe(1);
    expect($world->fresh()->cardImage->original_name)->toBe('second.png');
});

it('deletes the card image and returns 200', function () {
    [$user, $world] = setUpWorldForImage();

    $this->actingAs($user)->postJson(route('worlds.image.card.store', $world), ['image' => UploadedFile::fake()->image('card.png')])->assertStatus(201);

    $this->actingAs($user)->deleteJson(route('worlds.image.card.destroy', $world))->assertStatus(200);

    expect($world->fresh()->cardImage)->toBeNull();
});

it('deletes the portrait image without touching the card image', function () {
    [$user, $world] = setUpWorldForImage();

    $this->actingAs($user)->postJson(route('worlds.image.card.store', $world), ['image' => UploadedFile::fake()->image('card.png')])->assertStatus(201);
    $this->actingAs($user)->postJson(route('worlds.image.portrait.store', $world), ['image' => UploadedFile::fake()->image('portrait.png')])->assertStatus(201);

    $this->actingAs($user)->deleteJson(route('worlds.image.portrait.destroy', $world))->assertStatus(200);

    $fresh = $world->fresh();
    expect($fresh->portraitImage)->toBeNull();
    expect($fresh->cardImage)->not->toBeNull();
});

it('returns 404 when deleting a non-existent image', function () {
    [$user, $world] = setUpWorldForImage();

    $this->actingAs($user)->deleteJson(route('worlds.image.card.destroy', $world))->assertStatus(404);
});

it('returns 404/403 uploading an image for a world the user has no access to', function () {
    $world = World::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('worlds.image.card.store', $world), [
        'image' => UploadedFile::fake()->image('card.png'),
    ])->assertForbidden();
});
