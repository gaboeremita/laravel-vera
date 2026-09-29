<?php

use App\Models\Campaign;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function campaignPayload(array $questIds, array $overrides = []): array
{
    return [
        'key' => 'the-river',
        'title' => 'The River',
        'definition' => ['description' => 'The river floods and the valley decides whom to blame.', 'rubric' => ['guidance' => 'Judge the whole story.', 'dimensions' => [['name' => 'resolve', 'description' => '']], 'tiers' => ['saved', 'lost']]],
        'questIds' => $questIds,
        ...$overrides,
    ];
}

it('creates, lists, updates and deletes a campaign that groups quests', function () {
    [$user, , , $region] = worldStateScenario();
    $first = worldQuest($region->world, [], ['key' => 'first']);
    $second = worldQuest($region->world, [], ['key' => 'second']);

    $id = $this->actingAs($user)->postJson(route('worlds.campaigns.store', $region->world_id), campaignPayload([$first->id, $second->id]))
        ->assertCreated()->assertJsonPath('questIds', [$first->id, $second->id])->json('id');

    $this->getJson(route('worlds.campaigns.index', $region->world_id))->assertOk()->assertJsonPath('0.title', 'The River');

    $this->patchJson(route('worlds.campaigns.update', [$region->world_id, $id]), campaignPayload([$second->id]))->assertOk()->assertJsonPath('questIds', [$second->id]);
    expect($first->fresh()->campaign_id)->toBeNull()->and($second->fresh()->campaign_id)->toBe($id);

    $this->deleteJson(route('worlds.campaigns.destroy', [$region->world_id, $id]))->assertNoContent();
    expect(Campaign::count())->toBe(0)->and($second->fresh()->campaign_id)->toBeNull()->and(Quest::count())->toBe(2);
});

it('keeps a quest in one campaign at most, and quests of other worlds out', function () {
    [$user, , , $region] = worldStateScenario();
    $quest = worldQuest($region->world);
    Campaign::factory()->create(['world_id' => $region->world_id])->quests()->save($quest);
    $elsewhere = Quest::factory()->create();

    $this->actingAs($user)->postJson(route('worlds.campaigns.store', $region->world_id), campaignPayload([$quest->id]))->assertJsonValidationErrors(['questIds']);
    $this->postJson(route('worlds.campaigns.store', $region->world_id), campaignPayload([$elsewhere->id]))->assertJsonValidationErrors(['questIds.0']);
});

it('keeps another user\'s world out of reach', function () {
    [, , , $region] = worldStateScenario();

    $this->actingAs(User::factory()->create())->getJson(route('worlds.campaigns.index', $region->world_id))->assertForbidden();
});
