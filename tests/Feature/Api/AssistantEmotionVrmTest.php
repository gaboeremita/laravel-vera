<?php

use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function setUpAssistantForEmotionVrm(): array
{
    $user = User::factory()->create();
    $assistant = Assistant::factory()->create(['portrait_type' => 'avatar3d']);
    AssistantUser::factory()->create([
        'user_id' => $user->id,
        'assistant_id' => $assistant->id,
    ]);

    return [$user, $assistant];
}

it('creates an emotion with vrm_blendshapes and no image', function () {
    [$user, $assistant] = setUpAssistantForEmotionVrm();

    $response = $this->actingAs($user)
        ->postJson(route('assistants.emotions.store', ['assistant' => $assistant->id]), [
            'name' => 'happy',
            'vrm_blendshapes' => [
                ['expression' => 'happy', 'weight' => 100],
                ['expression' => 'surprised', 'weight' => 25],
            ],
        ]);

    $response->assertStatus(201);
    expect($response->json('vrm_blendshapes'))->toBe([
        ['expression' => 'happy', 'weight' => 1],
        ['expression' => 'surprised', 'weight' => 0.25],
    ]);

    $emotion = $assistant->emotions()->where('name', 'happy')->first();
    expect($emotion->image)->toBeNull();
});

it('updates vrm_blendshapes independently of image', function () {
    [$user, $assistant] = setUpAssistantForEmotionVrm();

    $create = $this->actingAs($user)
        ->postJson(route('assistants.emotions.store', ['assistant' => $assistant->id]), [
            'name' => 'sad',
            'vrm_blendshapes' => [['expression' => 'sad', 'weight' => 50]],
        ]);

    $emotionId = $create->json('id');

    $update = $this->actingAs($user)
        ->postJson(route('assistants.emotions.update', ['assistant' => $assistant->id, 'emotion' => $emotionId]), [
            'vrm_blendshapes' => [['expression' => 'sad', 'weight' => 90]],
        ]);

    $update->assertStatus(200);
    expect($update->json('vrm_blendshapes'))->toBe([
        ['expression' => 'sad', 'weight' => 0.9],
    ]);
});

it('rejects a blendshape weight above 100', function () {
    [$user, $assistant] = setUpAssistantForEmotionVrm();

    $response = $this->actingAs($user)
        ->postJson(route('assistants.emotions.store', ['assistant' => $assistant->id]), [
            'name' => 'angry',
            'vrm_blendshapes' => [['expression' => 'angry', 'weight' => 150]],
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['vrm_blendshapes.0.weight']);
});

it('reads real emotion names for avatar3d assistants instead of a hardcoded list', function () {
    [$user, $assistant] = setUpAssistantForEmotionVrm();

    $this->actingAs($user)->postJson(route('assistants.emotions.store', ['assistant' => $assistant->id]), [
        'name' => 'mischievous',
        'vrm_blendshapes' => [['expression' => 'happy', 'weight' => 60]],
    ])->assertStatus(201);

    $this->actingAs($user)->postJson(route('assistants.emotions.store', ['assistant' => $assistant->id]), [
        'name' => 'seduced',
        'vrm_blendshapes' => [['expression' => 'relaxed', 'weight' => 80]],
        'restricted' => true,
    ])->assertStatus(201);

    $names = $assistant->fresh()->promptEmotionNames();

    expect($names['regular'])->toBe(['mischievous']);
    expect($names['intimate'])->toBe(['seduced']);
});

it('creates an avatar3d assistant with an emotion carrying vrm_blendshapes at creation time', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('assistants.store'), [
        'name' => 'Avatar Assistant',
        'slug' => 'avatar-assistant-emotions',
        'portrait_type' => 'avatar3d',
        'emotions' => [
            [
                'name' => 'happy',
                'vrm_blendshapes' => [['expression' => 'happy', 'weight' => 100]],
            ],
        ],
    ]);

    $response->assertStatus(201);
    $assistant = Assistant::find($response->json('id'));
    $emotion = $assistant->emotions()->where('name', 'happy')->first();

    expect($emotion)->not->toBeNull();
    expect($emotion->vrm_blendshapes)->toBe([['expression' => 'happy', 'weight' => 1]]);
    expect($emotion->image)->toBeNull();
});
