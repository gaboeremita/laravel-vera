<?php

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('supports_tools can be set when creating a model', function () {
    $user = User::factory()->create();
    $provider = AiProvider::create([
        'user_id' => $user->id,
        'name' => 'Test Provider',
        'url' => 'https://example.test',
        'config_schema' => [],
        'format' => 'generic',
    ]);

    $response = $this->actingAs($user)->postJson(
        route('ai-models.store', ['provider' => $provider->id]),
        ['name' => 'Test Model', 'endpoint' => 'test/model', 'supports_tools' => true],
    );

    $response->assertCreated();
    expect(AiModel::first()->supports_tools)->toBeTrue();
});

test('supports_tools can be toggled when updating a model', function () {
    $user = User::factory()->create();
    $provider = AiProvider::create([
        'user_id' => $user->id,
        'name' => 'Test Provider',
        'url' => 'https://example.test',
        'config_schema' => [],
        'format' => 'generic',
    ]);
    $model = AiModel::create([
        'provider_id' => $provider->id,
        'name' => 'Test Model',
        'endpoint' => 'test/model',
        'config' => [],
        'supports_tools' => false,
    ]);

    $response = $this->actingAs($user)->patchJson(
        route('ai-models.update', ['provider' => $provider->id, 'model' => $model->id]),
        ['supports_tools' => true],
    );

    $response->assertSuccessful();
    expect($model->refresh()->supports_tools)->toBeTrue();
});
