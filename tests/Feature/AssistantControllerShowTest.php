<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('show includes the mode for an agent-mode assistant', function () {
    [$user, $assistant] = setUpAgentAssistant('agent');

    $response = $this->actingAs($user)->getJson(route('assistants.show', ['id' => $assistant->id]));

    $response->assertSuccessful();
    $response->assertJson(['mode' => 'agent']);
});

test('show includes the mode for an assistant-mode assistant', function () {
    [$user, $assistant] = setUpAgentAssistant('assistant');

    $response = $this->actingAs($user)->getJson(route('assistants.show', ['id' => $assistant->id]));

    $response->assertSuccessful();
    $response->assertJson(['mode' => 'assistant']);
});
