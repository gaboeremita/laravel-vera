<?php

use App\Models\Region;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function setUpRegionForTrack(): array
{
    Storage::fake('public');

    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();

    return [$user, $world];
}

it('uploads an mp3 track and returns 201 with trackUrl', function () {
    [$user, $world] = setUpRegionForTrack();

    $response = $this->actingAs($user)->postJson(route('worlds.regions.track.store', [$world->world_id, $world]), [
        'track' => UploadedFile::fake()->create('song.mp3', 1000, 'audio/mpeg'),
    ]);

    $response->assertStatus(201)->assertJsonStructure(['trackUrl']);
    expect($world->fresh()->track)->not->toBeNull();
});

it('replaces an existing track rather than duplicating it', function () {
    [$user, $world] = setUpRegionForTrack();

    $this->actingAs($user)->postJson(route('worlds.regions.track.store', [$world->world_id, $world]), [
        'track' => UploadedFile::fake()->create('first.mp3', 1000, 'audio/mpeg'),
    ])->assertStatus(201);

    $firstUrl = $world->fresh()->track->url;

    $response = $this->actingAs($user)->postJson(route('worlds.regions.track.store', [$world->world_id, $world]), [
        'track' => UploadedFile::fake()->create('second.wav', 1000, 'audio/wav'),
    ]);

    $response->assertStatus(201);
    $fresh = $world->fresh();
    expect($fresh->track->url)->not->toBe($firstUrl);
    expect($fresh->track->original_name)->toBe('second.wav');
    expect(Track::where('trackable_id', $world->id)->where('trackable_type', Region::class)->count())->toBe(1);
});

it('rejects an unsupported audio format', function () {
    [$user, $world] = setUpRegionForTrack();

    $response = $this->actingAs($user)->postJson(route('worlds.regions.track.store', [$world->world_id, $world]), [
        'track' => UploadedFile::fake()->create('song.flac', 1000, 'audio/flac'),
    ]);

    $response->assertStatus(422);
});

it('rejects a track over the size limit', function () {
    [$user, $world] = setUpRegionForTrack();

    $response = $this->actingAs($user)->postJson(route('worlds.regions.track.store', [$world->world_id, $world]), [
        'track' => UploadedFile::fake()->create('song.mp3', 20481, 'audio/mpeg'),
    ]);

    $response->assertStatus(422);
});

it('forbids a non-owner from uploading or deleting a track', function () {
    $world = Region::factory()->create();
    $user = User::factory()->create();
    Storage::fake('public');

    $this->actingAs($user)->postJson(route('worlds.regions.track.store', [$world->world_id, $world]), [
        'track' => UploadedFile::fake()->create('song.mp3', 1000, 'audio/mpeg'),
    ])->assertForbidden();

    $this->actingAs($user)->deleteJson(route('worlds.regions.track.destroy', [$world->world_id, $world]))->assertForbidden();
});

it('deletes the track and returns 200', function () {
    [$user, $world] = setUpRegionForTrack();

    $this->actingAs($user)->postJson(route('worlds.regions.track.store', [$world->world_id, $world]), [
        'track' => UploadedFile::fake()->create('song.mp3', 1000, 'audio/mpeg'),
    ])->assertStatus(201);

    $this->actingAs($user)->deleteJson(route('worlds.regions.track.destroy', [$world->world_id, $world]))->assertStatus(200);

    expect($world->fresh()->track)->toBeNull();
});

it('returns 404 when deleting a non-existent track', function () {
    [$user, $world] = setUpRegionForTrack();

    $this->actingAs($user)->deleteJson(route('worlds.regions.track.destroy', [$world->world_id, $world]))->assertStatus(404);
});
