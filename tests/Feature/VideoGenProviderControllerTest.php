<?php

use App\Models\AssistantUser;
use App\Models\User;
use App\Models\VideoGenModel;
use App\Models\VideoGenProvider;
use App\Services\VideoGenProviders\VideoGenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates, lists, updates and deletes a provider without ever returning its key', function () {
    $user = User::factory()->create();

    $created = $this->actingAs($user)->postJson(route('video-gen-providers.store'), [
        'name' => 'OpenRouter',
        'url' => 'https://openrouter.ai/api/v1/videos',
        'api_key' => 'secret-key',
        'format' => 'openrouter',
    ])->assertCreated()->assertJsonMissingPath('api_key')->assertJsonPath('has_key', true);

    $providerId = $created->json('id');
    expect(VideoGenProvider::findOrFail($providerId)->api_key)->toBe('secret-key');

    $this->actingAs($user)->getJson(route('video-gen-providers.index'))
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'OpenRouter')
        ->assertJsonMissingPath('0.api_key');

    $this->actingAs($user)->patchJson(route('video-gen-providers.update', ['id' => $providerId]), [
        'name' => 'OpenRouter Video',
        'format' => 'openrouter',
    ])->assertSuccessful()->assertJsonPath('name', 'OpenRouter Video');

    $this->actingAs($user)->deleteJson(route('video-gen-providers.destroy', ['id' => $providerId]))->assertSuccessful();

    expect(VideoGenProvider::count())->toBe(0);
});

it('creates, updates and deletes a model under a provider', function () {
    $user = User::factory()->create();
    $provider = VideoGenProvider::factory()->for($user)->create();

    $created = $this->actingAs($user)->postJson(route('video-gen-models.store', ['provider' => $provider->id]), [
        'name' => 'Seedance 2.0',
        'endpoint' => 'bytedance/seedance-2.0',
        'config' => ['duration' => 5, 'aspect_ratio' => '16:9'],
    ])->assertCreated();

    $modelId = $created->json('id');

    $this->actingAs($user)->patchJson(route('video-gen-models.update', ['provider' => $provider->id, 'model' => $modelId]), [
        'config' => ['duration' => 8],
    ])->assertSuccessful();

    expect(VideoGenModel::findOrFail($modelId)->config)->toBe(['duration' => 8]);

    $this->actingAs($user)->deleteJson(route('video-gen-models.destroy', ['provider' => $provider->id, 'model' => $modelId]))->assertSuccessful();

    expect(VideoGenModel::count())->toBe(0);
});

it('validates model config against the provider config schema', function () {
    $user = User::factory()->create();
    $provider = VideoGenProvider::factory()->for($user)->create([
        'config_schema' => [['name' => 'duration', 'type' => 'integer']],
    ]);

    $this->actingAs($user)->postJson(route('video-gen-models.store', ['provider' => $provider->id]), [
        'name' => 'Seedance 2.0',
        'endpoint' => 'bytedance/seedance-2.0',
        'config' => ['unknown' => true],
    ])->assertUnprocessable()->assertJsonValidationErrors('config');
});

it('hides another user\'s providers and models', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $model = VideoGenModel::factory()->for(VideoGenProvider::factory()->for($owner), 'provider')->create();

    $this->actingAs($intruder)->getJson(route('video-gen-providers.index'))->assertSuccessful()->assertJsonCount(0);
    $this->actingAs($intruder)->patchJson(route('video-gen-providers.update', ['id' => $model->provider_id]), ['name' => 'Taken', 'format' => 'openrouter'])->assertNotFound();
    $this->actingAs($intruder)->deleteJson(route('video-gen-models.destroy', ['provider' => $model->provider_id, 'model' => $model->id]))->assertNotFound();
});

it('selects, shows and clears the assistant\'s video model', function () {
    $assistantUser = AssistantUser::factory()->create();
    $user = $assistantUser->user;
    $model = VideoGenModel::factory()->for(VideoGenProvider::factory()->for($user), 'provider')->create();

    $this->actingAs($user)->putJson(route('settings.selectVideoGenModel', ['assistant' => $assistantUser->assistant_id]), [
        'video_gen_model_id' => $model->id,
    ])->assertSuccessful();

    $this->actingAs($user)->getJson(route('settings.show', ['assistant' => $assistantUser->assistant_id]))
        ->assertSuccessful()
        ->assertJsonPath('video_gen_model_id', $model->id);

    expect(app(VideoGenManager::class)->resolveVideoGenModel($assistantUser)?->id)->toBe($model->id);

    $this->actingAs($user)->putJson(route('settings.selectVideoGenModel', ['assistant' => $assistantUser->assistant_id]), [
        'video_gen_model_id' => null,
    ])->assertSuccessful();

    expect(app(VideoGenManager::class)->resolveVideoGenModel($assistantUser))->toBeNull();
});

it('refuses to select another user\'s video model', function () {
    $assistantUser = AssistantUser::factory()->create();
    $foreignModel = VideoGenModel::factory()->create();

    $this->actingAs($assistantUser->user)->putJson(route('settings.selectVideoGenModel', ['assistant' => $assistantUser->assistant_id]), [
        'video_gen_model_id' => $foreignModel->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('video_gen_model_id');
});

it('leaves the assistant with no video model when the selected one is deleted', function () {
    $assistantUser = AssistantUser::factory()->create();
    $user = $assistantUser->user;
    $model = VideoGenModel::factory()->for(VideoGenProvider::factory()->for($user), 'provider')->create();

    $this->actingAs($user)->putJson(route('settings.selectVideoGenModel', ['assistant' => $assistantUser->assistant_id]), [
        'video_gen_model_id' => $model->id,
    ])->assertSuccessful();

    $this->actingAs($user)->deleteJson(route('video-gen-models.destroy', ['provider' => $model->provider_id, 'model' => $model->id]))->assertSuccessful();

    expect(app(VideoGenManager::class)->resolveVideoGenModel($assistantUser))->toBeNull();
});
