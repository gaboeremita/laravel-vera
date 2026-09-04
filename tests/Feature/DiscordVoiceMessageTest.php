<?php

use App\Contracts\SttProvider;
use App\Contracts\TtsProvider;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\Settings;
use App\Models\VoiceModel;
use App\Models\VoiceProvider;
use App\Services\TtsProviders\TtsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function setUpDiscordAssistant(array $settingsData = []): array
{
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant');

    if (! empty($settingsData)) {
        $settings = Settings::where('user_id', $user->id)
            ->where('assistant_id', $assistant->id)
            ->first();
        $settings->update(['data' => [...$settings->data, ...$settingsData]]);
    }

    return [$user, $assistant];
}

function setUpDiscordAssistantWithTts(array $settingsData = []): array
{
    [$user, $assistant] = setUpDiscordAssistant($settingsData);

    $voiceProvider = VoiceProvider::create([
        'user_id' => $user->id,
        'name' => 'Fake TTS',
        'url' => 'https://fake-tts.test',
        'format' => 'openai_compatible',
    ]);

    $voiceModel = VoiceModel::create([
        'provider_id' => $voiceProvider->id,
        'name' => 'Fake Voice Model',
        'endpoint' => 'tts-1',
        'voices' => ['nova'],
        'config' => ['timeout' => 30],
    ]);

    $settings = Settings::where('user_id', $user->id)
        ->where('assistant_id', $assistant->id)
        ->first();
    $settings->update(['data' => [...$settings->data, 'tts_model_id' => $voiceModel->id, 'tts_voice' => 'nova']]);

    return [$user, $assistant];
}

function mockStt(string $transcription = 'Hello, how are you?'): void
{
    app()->bind(SttProvider::class, function () use ($transcription) {
        $mock = Mockery::mock(SttProvider::class);
        $mock->shouldReceive('transcribe')->andReturn($transcription);

        return $mock;
    });
}

function mockFailingStt(): void
{
    app()->bind(SttProvider::class, function () {
        $mock = Mockery::mock(SttProvider::class);
        $mock->shouldReceive('transcribe')->andThrow(new RuntimeException('STT service unavailable'));

        return $mock;
    });
}

function mockTts(string $audioBytes = 'fake-audio-bytes', string $contentType = 'audio/mpeg'): void
{
    app()->bind(TtsManager::class, function () use ($audioBytes, $contentType) {
        $ttsMock = Mockery::mock(TtsProvider::class);
        $ttsMock->shouldReceive('synthesize')->andReturn($audioBytes);
        $ttsMock->shouldReceive('contentType')->andReturn($contentType);

        $managerMock = Mockery::mock(TtsManager::class);
        $managerMock->shouldReceive('forAssistantUser')->andReturn($ttsMock);

        return $managerMock;
    });
}

function mockFailingTts(): void
{
    app()->bind(TtsManager::class, function () {
        $ttsMock = Mockery::mock(TtsProvider::class);
        $ttsMock->shouldReceive('synthesize')->andThrow(new RuntimeException('TTS service unavailable'));

        $managerMock = Mockery::mock(TtsManager::class);
        $managerMock->shouldReceive('forAssistantUser')->andReturn($ttsMock);

        return $managerMock;
    });
}

// === US1: Voice-In Tests ===

test('[US1] discord-messages endpoint accepts audio and audioContentType, transcribes, and returns text', function () {
    [$user, $assistant] = setUpDiscordAssistant();
    mockStt('Hello, how are you?');

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('I am doing great!')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-123',
            'content' => null,
            'audio' => base64_encode('fake-audio-data'),
            'audioContentType' => 'audio/ogg',
        ],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('I am doing great!');
});

test('[US1] transcribed text is stored as message content', function () {
    [$user, $assistant] = setUpDiscordAssistant();
    mockStt('This is a test message');

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Got it!')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-456',
            'content' => null,
            'audio' => base64_encode('fake-audio-data'),
            'audioContentType' => 'audio/ogg',
        ],
    );

    $assistantUser = AssistantUser::where('user_id', $user->id)->where('assistant_id', $assistant->id)->first();
    $conversation = $assistantUser->conversations()->where('discord_channel_id', 'test-channel-456')->first();
    $userMessage = $conversation->messages()->where('role', 'user')->first();

    expect($userMessage->content)->toBe('This is a test message');
});

test('[US1] message with both text content and audio appends transcription to content', function () {
    [$user, $assistant] = setUpDiscordAssistant();
    mockStt('and here is the voice part');

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('I see both parts!')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-789',
            'content' => 'Here is text',
            'audio' => base64_encode('fake-audio-data'),
            'audioContentType' => 'audio/mpeg',
        ],
    );

    $assistantUser = AssistantUser::where('user_id', $user->id)->where('assistant_id', $assistant->id)->first();
    $conversation = $assistantUser->conversations()->where('discord_channel_id', 'test-channel-789')->first();
    $userMessage = $conversation->messages()->where('role', 'user')->first();

    expect($userMessage->content)->toContain('Here is text');
    expect($userMessage->content)->toContain('and here is the voice part');
});

test('[US1] request with audio but missing audioContentType is rejected with 422', function () {
    [$user, $assistant] = setUpDiscordAssistant();

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-aaa',
            'content' => null,
            'audio' => base64_encode('fake-audio-data'),
        ],
    );

    $response->assertStatus(422);
});

// === US2: Voice-Out Tests ===

test('[US2] when audio is present and voice mode is both, response includes audioBase64 and audioContentType', function () {
    [$user, $assistant] = setUpDiscordAssistantWithTts(['discordVoiceResponseMode' => 'both']);
    mockStt('Hello there');
    mockTts('fake-tts-audio', 'audio/mpeg');

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Hi! How can I help?')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-tts',
            'content' => null,
            'audio' => base64_encode('fake-audio-data'),
            'audioContentType' => 'audio/ogg',
        ],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('Hi! How can I help?');
    expect($response->json('audioBase64'))->toBe(base64_encode('fake-tts-audio'));
    expect($response->json('audioContentType'))->toBe('audio/mpeg');
});

test('[US2] when TTS synthesis fails, response falls back to text-only', function () {
    [$user, $assistant] = setUpDiscordAssistantWithTts(['discordVoiceResponseMode' => 'both']);
    mockStt('Hello');
    mockFailingTts();

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Hi there!')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-ttsfail',
            'content' => null,
            'audio' => base64_encode('fake-audio-data'),
            'audioContentType' => 'audio/ogg',
        ],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('Hi there!');
    expect($response->json('audioBase64'))->toBeNull();
});

// === US3: Voice Response Mode Tests ===

test('[US3] when discordVoiceResponseMode is textOnly, response has no audioBase64', function () {
    [$user, $assistant] = setUpDiscordAssistantWithTts(['discordVoiceResponseMode' => 'textOnly']);
    mockStt('Hello');

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Text only reply')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-textonly',
            'content' => null,
            'audio' => base64_encode('fake-audio-data'),
            'audioContentType' => 'audio/ogg',
        ],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('Text only reply');
    expect($response->json('audioBase64'))->toBeNull();
});

test('[US3] when discordVoiceResponseMode is voiceOnly, response has audioBase64 but content is null', function () {
    [$user, $assistant] = setUpDiscordAssistantWithTts(['discordVoiceResponseMode' => 'voiceOnly']);
    mockStt('Hello');
    mockTts('voice-only-audio', 'audio/wav');

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('This gets turned to voice only')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-voiceonly',
            'content' => null,
            'audio' => base64_encode('fake-audio-data'),
            'audioContentType' => 'audio/ogg',
        ],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBeNull();
    expect($response->json('audioBase64'))->toBe(base64_encode('voice-only-audio'));
    expect($response->json('audioContentType'))->toBe('audio/wav');
});

test('[US3] settings endpoint accepts and persists discordVoiceResponseMode', function () {
    [$user, $assistant] = setUpDiscordAssistant();

    $response = $this->actingAs($user)->putJson(
        route('settings.update', $assistant),
        ['discordVoiceResponseMode' => 'voiceOnly'],
    );

    $response->assertSuccessful();

    $settings = Settings::where('user_id', $user->id)->where('assistant_id', $assistant->id)->first();
    expect($settings->data['discordVoiceResponseMode'])->toBe('voiceOnly');
});

// === US4: /send-voice-message Command Tests ===

test('[US4] /send-voice-message prefix is detected, stripped, and response includes audioBase64', function () {
    [$user, $assistant] = setUpDiscordAssistantWithTts(['discordVoiceResponseMode' => 'both']);
    mockTts('command-audio', 'audio/mpeg');

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Here is my voice reply!')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-cmd',
            'content' => '/send-voice-message how are you?',
        ],
    );

    $response->assertSuccessful();
    expect($response->json('audioBase64'))->toBe(base64_encode('command-audio'));
    expect($response->json('audioContentType'))->toBe('audio/mpeg');

    $assistantUser = AssistantUser::where('user_id', $user->id)->where('assistant_id', $assistant->id)->first();
    $conversation = $assistantUser->conversations()->where('discord_channel_id', 'test-channel-cmd')->first();
    $userMessage = $conversation->messages()->where('role', 'user')->first();
    expect($userMessage->content)->toBe('how are you?');
});

test('[US4] /send-voice-message overrides textOnly mode and still produces audio', function () {
    [$user, $assistant] = setUpDiscordAssistantWithTts(['discordVoiceResponseMode' => 'textOnly']);
    mockTts('override-audio', 'audio/mpeg');

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Override reply')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-override',
            'content' => '/send-voice-message tell me something',
        ],
    );

    $response->assertSuccessful();
    expect($response->json('audioBase64'))->toBe(base64_encode('override-audio'));
});

test('[US4] /send-voice-message with no additional text produces a conversational reply with audio', function () {
    [$user, $assistant] = setUpDiscordAssistantWithTts(['discordVoiceResponseMode' => 'both']);
    mockTts('empty-cmd-audio', 'audio/mpeg');

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Hey there!')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-empty-cmd',
            'content' => '/send-voice-message',
        ],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('Hey there!');
    expect($response->json('audioBase64'))->toBe(base64_encode('empty-cmd-audio'));
});

// === Polish: Cross-cutting Tests ===

test('context continuity — voice message followed by text message references same conversation', function () {
    [$user, $assistant] = setUpDiscordAssistant();
    mockStt('My name is Gabriel');

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Nice to meet you, Gabriel!')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-continuity',
            'content' => null,
            'audio' => base64_encode('fake-audio-data'),
            'audioContentType' => 'audio/ogg',
        ],
    )->assertSuccessful();

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('You told me your name is Gabriel.')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-continuity',
            'content' => 'What did I just tell you?',
        ],
    )->assertSuccessful();

    $assistantUser = AssistantUser::where('user_id', $user->id)->where('assistant_id', $assistant->id)->first();
    $conversations = $assistantUser->conversations()->where('discord_channel_id', 'test-channel-continuity')->get();
    expect($conversations)->toHaveCount(1);
    expect($conversations->first()->messages()->count())->toBe(4);
});

test('STT failure returns a user-friendly error message', function () {
    [$user, $assistant] = setUpDiscordAssistant();
    mockFailingStt();

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-sttfail',
            'content' => null,
            'audio' => base64_encode('fake-audio-data'),
            'audioContentType' => 'audio/ogg',
        ],
    );

    $response->assertStatus(502);
});

test('empty transcription result produces a message indicating Vera could not understand', function () {
    [$user, $assistant] = setUpDiscordAssistant();
    mockStt('');

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'test-channel-empty',
            'content' => null,
            'audio' => base64_encode('fake-audio-data'),
            'audioContentType' => 'audio/ogg',
        ],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toContain("couldn't");
});
