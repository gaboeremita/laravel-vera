<?php

use App\Actions\StorePoseAnimation;
use App\Models\Pose;
use App\Models\PoseAnimationFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function storedPoseAnimation(string $path, ?string $content): PoseAnimationFile
{
    if ($content !== null) {
        Storage::disk('public')->put($path, $content);
    }

    return Pose::factory()->create()->animationFile()->create([
        'path' => $path,
        'disk' => 'public',
        'mime_type' => 'application/octet-stream',
        'size' => strlen($content ?? ''),
        'original_name' => basename($path),
    ]);
}

it('renames stored pose animations after their content and merges identical ones', function () {
    Storage::fake('public');
    $first = storedPoseAnimation('poses/1/10/aaa.fbx', 'wave animation');
    $copy = storedPoseAnimation('poses/2/20/bbb.fbx', 'wave animation');
    $other = storedPoseAnimation('poses/3/30/ccc.vrma', 'spin animation');
    $wavePath = StorePoseAnimation::pathFor(hash('sha256', 'wave animation'), 'fbx');
    $spinPath = StorePoseAnimation::pathFor(hash('sha256', 'spin animation'), 'vrma');

    $this->artisan('poses:share-files')->assertSuccessful();

    expect($first->fresh()->path)->toBe($wavePath);
    expect($copy->fresh()->path)->toBe($wavePath);
    expect($other->fresh()->path)->toBe($spinPath);
    expect(Storage::disk('public')->allFiles())->toEqualCanonicalizing([$wavePath, $spinPath]);
    expect(Storage::disk('public')->get($wavePath))->toBe('wave animation');
});

it('leaves a pose animation whose stored file is missing as it is', function () {
    Storage::fake('public');
    $lost = storedPoseAnimation('poses/1/10/lost.fbx', null);

    $this->artisan('poses:share-files')->assertSuccessful();

    expect($lost->fresh()->path)->toBe('poses/1/10/lost.fbx');
});

it('changes nothing when run again', function () {
    Storage::fake('public');
    $animation = storedPoseAnimation('poses/1/10/aaa.fbx', 'wave animation');
    $this->artisan('poses:share-files')->assertSuccessful();
    $path = $animation->fresh()->path;

    $this->artisan('poses:share-files')->assertSuccessful();

    expect($animation->fresh()->path)->toBe($path);
    expect(Storage::disk('public')->allFiles())->toBe([$path]);
});
