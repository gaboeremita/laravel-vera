<?php

use App\Models\PassageLink;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function linkPassage($test, User $user, Region $region, string $passageId, Region $target, string $targetPassageId)
{
    return $test->actingAs($user)->putJson(route('worlds.regions.passages.link.update', [$region->world_id, $region, $passageId]), [
        'targetRegionId' => $target->id,
        'targetPassageId' => $targetPassageId,
    ]);
}

function partnerOf(Region $region, string $passageId): ?array
{
    $link = PassageLink::where('region_id', $region->id)->where('passage_id', $passageId)->first();

    return $link ? [$link->target_region_id, $link->target_passage_id] : null;
}

it('links two passages both ways', function () {
    $user = User::factory()->create();
    $lobby = Region::factory()->forUser($user)->withLayout()->create();
    $penthouse = Region::factory()->withLayout()->create(['world_id' => $lobby->world_id]);

    linkPassage($this, $user, $lobby, 'studio-door', $penthouse, 'terrace-gate')
        ->assertSuccessful()
        ->assertJsonPath('regions.0.links.0.targetPassageId', 'terrace-gate')
        ->assertJsonPath('regions.1.links.0.targetPassageId', 'studio-door');

    expect(partnerOf($lobby, 'studio-door'))->toBe([$penthouse->id, 'terrace-gate'])
        ->and(partnerOf($penthouse, 'terrace-gate'))->toBe([$lobby->id, 'studio-door']);
});

it('links two passages of the same region', function () {
    $user = User::factory()->create();
    $lobby = Region::factory()->forUser($user)->withLayout()->create();

    linkPassage($this, $user, $lobby, 'studio-door', $lobby, 'terrace-gate')->assertSuccessful();

    expect(partnerOf($lobby, 'studio-door'))->toBe([$lobby->id, 'terrace-gate'])
        ->and(partnerOf($lobby, 'terrace-gate'))->toBe([$lobby->id, 'studio-door']);
});

it('replaces the previous partners of both passages when relinking', function () {
    $user = User::factory()->create();
    $lobby = Region::factory()->forUser($user)->withLayout()->create();
    $penthouse = Region::factory()->withLayout()->create(['world_id' => $lobby->world_id]);
    $harbor = Region::factory()->withLayout()->create(['world_id' => $lobby->world_id]);
    linkPassage($this, $user, $lobby, 'studio-door', $penthouse, 'terrace-gate')->assertSuccessful();

    linkPassage($this, $user, $harbor, 'studio-door', $penthouse, 'terrace-gate')->assertSuccessful();

    expect(partnerOf($lobby, 'studio-door'))->toBeNull()
        ->and(partnerOf($penthouse, 'terrace-gate'))->toBe([$harbor->id, 'studio-door'])
        ->and(PassageLink::count())->toBe(2);
});

it('unlinks both directions', function () {
    $user = User::factory()->create();
    $lobby = Region::factory()->forUser($user)->withLayout()->create();
    $penthouse = Region::factory()->withLayout()->create(['world_id' => $lobby->world_id]);
    linkPassage($this, $user, $lobby, 'studio-door', $penthouse, 'terrace-gate')->assertSuccessful();

    $this->actingAs($user)->deleteJson(route('worlds.regions.passages.link.destroy', [$penthouse->world_id, $penthouse, 'terrace-gate']))->assertNoContent();

    expect(PassageLink::count())->toBe(0);
});

it('rejects a link to itself, to a missing passage or to another world', function (Closure $target) {
    $user = User::factory()->create();
    $lobby = Region::factory()->forUser($user)->withLayout()->create();
    [$targetRegion, $targetPassageId] = $target($lobby);

    linkPassage($this, $user, $lobby, 'studio-door', $targetRegion, $targetPassageId)->assertUnprocessable();

    expect(PassageLink::count())->toBe(0);
})->with([
    'itself' => [fn (Region $lobby) => [$lobby, 'studio-door']],
    'missing passage' => [fn (Region $lobby) => [$lobby, 'nowhere']],
    'another world' => [fn (Region $lobby) => [Region::factory()->withLayout()->create(), 'studio-door']],
]);

it('does not link passages of another users world', function () {
    $lobby = Region::factory()->withLayout()->create();

    linkPassage($this, User::factory()->create(), $lobby, 'studio-door', $lobby, 'terrace-gate')->assertForbidden();
});
