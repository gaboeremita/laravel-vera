<?php

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('saves the caching settings when a model is created and when it is updated', function () {
    $user = User::factory()->create();
    $provider = AiProvider::factory()->for($user)->create();

    $this->actingAs($user)->postJson(route('ai-models.store', ['provider' => $provider->id]), [
        'name' => 'Claude via gateway',
        'endpoint' => 'vendor/model',
        'cache_marks' => true,
        'conversation_key_field' => 'session_id',
    ])->assertCreated();

    $model = AiModel::firstOrFail();
    expect($model->cache_marks)->toBeTrue()
        ->and($model->conversation_key_field)->toBe('session_id');

    $this->actingAs($user)->patchJson(route('ai-models.update', ['provider' => $provider->id, 'model' => $model->id]), [
        'cache_marks' => false,
        'conversation_key_field' => 'prompt_cache_key',
    ])->assertSuccessful();

    expect($model->fresh()->cache_marks)->toBeFalse()
        ->and($model->fresh()->conversation_key_field)->toBe('prompt_cache_key');
});

it('refuses a conversation ID field that is not a plain field name', function (string $field) {
    $user = User::factory()->create();
    $model = AiModel::factory()->for(AiProvider::factory()->for($user), 'provider')->create();

    $this->actingAs($user)->patchJson(route('ai-models.update', ['provider' => $model->provider_id, 'model' => $model->id]), [
        'conversation_key_field' => $field,
    ])->assertUnprocessable()->assertJsonValidationErrors('conversation_key_field');
})->with(['session id', 'a-b', '1field']);

it('does not let a user change another user\'s model', function () {
    $model = AiModel::factory()->create();

    $this->actingAs(User::factory()->create())->patchJson(route('ai-models.update', ['provider' => $model->provider_id, 'model' => $model->id]), [
        'cache_marks' => true,
    ])->assertNotFound();
});
