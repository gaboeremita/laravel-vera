<?php

use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{User, Assistant}
 */
function ownedTermRulesAssistant(Assistant $assistant): array
{
    $user = User::factory()->create();
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $assistant->id]);

    return [$user, $assistant];
}

it('saves the term rule settings and keeps the other agent settings', function () {
    [$user, $assistant] = ownedTermRulesAssistant(
        Assistant::factory()->withTermRules('alpha -> beta')->create(['agent_config' => ['step_limit' => 7]]),
    );

    $this->actingAs($user)
        ->patchJson(route('assistants.update', ['id' => $assistant->id]), [
            'agent_config' => ['termRules' => ['section' => 'termRuleSection', 'markTerms' => true, 'swapInvariant' => false, 'highlightMissing' => true]],
        ])
        ->assertOk();

    $assistant->refresh();
    expect($assistant->agent_config['step_limit'])->toBe(7)
        ->and($assistant->termRuleSettings())->toBe(['section' => 'termRuleSection', 'markTerms' => true, 'swapInvariant' => false, 'highlightMissing' => true]);
});

it('returns the stored term rule settings', function () {
    [$user, $assistant] = ownedTermRulesAssistant(Assistant::factory()->withTermRules('alpha -> beta', ['markTerms' => true])->create());

    $this->actingAs($user)
        ->getJson(route('assistants.show', ['id' => $assistant->id]))
        ->assertOk()
        ->assertJsonPath('agent_config.termRules.section', 'termRuleSection')
        ->assertJsonPath('agent_config.termRules.markTerms', true)
        ->assertJsonPath('agent_config.termRules.highlightMissing', false);
});

it('returns default term rule settings when none are stored', function () {
    [$user, $assistant] = ownedTermRulesAssistant(Assistant::factory()->create());

    $this->actingAs($user)
        ->getJson(route('assistants.show', ['id' => $assistant->id]))
        ->assertOk()
        ->assertJsonPath('agent_config.termRules', ['section' => null, 'markTerms' => false, 'swapInvariant' => false, 'highlightMissing' => false]);
});

it('accepts term rule settings when an assistant is created', function () {
    $user = User::factory()->create();
    $sectionKey = fake()->word();

    $response = $this->actingAs($user)
        ->postJson(route('assistants.store'), [
            'name' => fake()->firstName(),
            'slug' => fake()->unique()->slug(2),
            'portrait_type' => 'avatar3d',
            'prompt' => json_encode([$sectionKey => 'alpha -> beta']),
            'agent_config' => json_encode(['termRules' => ['section' => $sectionKey, 'markTerms' => true]]),
        ])
        ->assertCreated();

    $assistant = Assistant::findOrFail($response->json('id'));
    expect($assistant->termRuleSettings())->toMatchArray(['section' => $sectionKey, 'markTerms' => true]);
});

it('refuses a section that is missing or holds no text', function (array $prompt) {
    [$user, $assistant] = ownedTermRulesAssistant(Assistant::factory()->create(['prompt' => $prompt]));

    $this->actingAs($user)
        ->patchJson(route('assistants.update', ['id' => $assistant->id]), [
            'agent_config' => ['termRules' => ['section' => 'picked']],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('agent_config.termRules.section');
})->with([
    'missing' => [['identity' => 'someone']],
    'list' => [['picked' => ['alpha -> beta']]],
    'object' => [['picked' => ['nested' => 'alpha -> beta']]],
]);

it('refuses settings while a rule line fails to parse, naming each failing line', function () {
    [$user, $assistant] = ownedTermRulesAssistant(Assistant::factory()->create([
        'prompt' => ['picked' => "alpha -> beta\ngamma ->\nplain text\n -> delta"],
    ]));

    $response = $this->actingAs($user)
        ->patchJson(route('assistants.update', ['id' => $assistant->id]), [
            'agent_config' => ['termRules' => ['section' => 'picked']],
        ])
        ->assertUnprocessable();

    expect($response->json('errors')['agent_config.termRules.section'])->toBe([
        'Line 2 is not a valid rule: "gamma ->"',
        'Line 4 is not a valid rule: "-> delta"',
    ]);
});

it('refuses a prompt save while a rule line in the picked section fails to parse', function () {
    [$user, $assistant] = ownedTermRulesAssistant(Assistant::factory()->withTermRules('alpha -> beta')->create());

    $response = $this->actingAs($user)
        ->putJson(route('prompt.update', ['assistant' => $assistant->id]), [
            'prompt' => ['termRuleSection' => "alpha -> beta\ngamma -> , "],
        ])
        ->assertUnprocessable();

    expect($response->json('errors.prompt'))->toBe(['Line 2 is not a valid rule: "gamma -> ,"']);
});

it('accepts lines without an arrow as ordinary prompt text', function () {
    [$user, $assistant] = ownedTermRulesAssistant(Assistant::factory()->withTermRules('alpha -> beta')->create());

    $this->actingAs($user)
        ->putJson(route('prompt.update', ['assistant' => $assistant->id]), [
            'prompt' => ['termRuleSection' => "Use these rules.\nalpha -> beta (invariant) (case)"],
        ])
        ->assertOk();
});

it('lets a prompt save rename or remove the picked section', function () {
    [$user, $assistant] = ownedTermRulesAssistant(Assistant::factory()->withTermRules('alpha -> beta')->create());

    $this->actingAs($user)
        ->putJson(route('prompt.update', ['assistant' => $assistant->id]), [
            'prompt' => ['renamed' => 'alpha ->'],
        ])
        ->assertOk();
});

it('hides another user\'s assistant', function () {
    $assistant = Assistant::factory()->withTermRules('alpha -> beta')->create();

    $this->actingAs(User::factory()->create())
        ->patchJson(route('assistants.update', ['id' => $assistant->id]), [
            'agent_config' => ['termRules' => ['markTerms' => true]],
        ])
        ->assertNotFound();
});
