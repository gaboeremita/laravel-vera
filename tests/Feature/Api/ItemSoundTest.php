<?php

use App\Models\Sound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function chimeBytes(): string
{
    $samples = str_repeat(pack('v', 0), 800);

    return 'RIFF'.pack('V', 36 + strlen($samples)).'WAVE'
        .'fmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16)
        .'data'.pack('V', strlen($samples)).$samples;
}

function chime(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('chime.wav', chimeBytes());
}

it('stores one file per sound, shared by every item that uses it', function () {
    Storage::fake('public');
    [$user, , , $region] = worldStateScenario();
    $coffee = worldItem($region, ['name' => 'Canned coffee']);
    $soda = worldItem($region, ['name' => 'Melon soda']);

    $first = $this->actingAs($user)->post(route('worlds.items.sound.store', [$region->world_id, $coffee->id]), ['sound' => chime()])
        ->assertCreated()->json();
    $second = $this->post(route('worlds.items.sound.store', [$region->world_id, $soda->id]), ['sound' => chime()])
        ->assertCreated()->json();

    expect(Sound::count())->toBe(1)
        ->and($first['soundHash'])->toBe(hash('sha256', chimeBytes()))
        ->and($second['soundUrl'])->toBe($first['soundUrl']);
    Storage::disk('public')->assertExists(Sound::sole()->path);
});

it('keeps a shared sound until the last item lets go of it', function () {
    Storage::fake('public');
    [$user, , , $region] = worldStateScenario();
    $coffee = worldItem($region);
    $soda = worldItem($region);
    $this->actingAs($user)->post(route('worlds.items.sound.store', [$region->world_id, $coffee->id]), ['sound' => chime()]);
    $this->post(route('worlds.items.sound.store', [$region->world_id, $soda->id]), ['sound' => chime()]);
    $path = Sound::sole()->path;

    $this->deleteJson(route('worlds.items.sound.destroy', [$region->world_id, $coffee->id]))->assertNoContent();
    expect(Sound::count())->toBe(1);

    $this->deleteJson(route('worlds.items.destroy', [$region->world_id, $soda->id]))->assertNoContent();
    expect(Sound::count())->toBe(0);
    Storage::disk('public')->assertMissing($path);
});

it('refuses files that are not audio', function () {
    Storage::fake('public');
    [$user, , , $region] = worldStateScenario();
    $coffee = worldItem($region);

    $this->actingAs($user)->postJson(route('worlds.items.sound.store', [$region->world_id, $coffee->id]), ['sound' => UploadedFile::fake()->image('not-a-sound.png')])
        ->assertJsonValidationErrors('sound');
});
