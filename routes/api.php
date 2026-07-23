<?php

use App\Http\Controllers\Api\AiModelController;
use App\Http\Controllers\Api\AiProviderController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AssistantMemoryPromptController;
use App\Http\Controllers\Api\AssistantPromptController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationMemoryController;
use App\Http\Controllers\Api\AssistantEmotionController;
use App\Http\Controllers\Api\EmotionController;
use App\Http\Controllers\Api\ArchiveController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\VoiceController;
use App\Http\Controllers\Api\VoiceModelController;
use App\Http\Controllers\Api\VoiceProviderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user())->name('user.show');

    Route::get('/assistants', [AssistantController::class, 'index'])->name('assistants.index');
    Route::post('/assistants', [AssistantController::class, 'store'])->name('assistants.store');
    Route::get('/assistants/{id}', [AssistantController::class, 'show'])->name('assistants.show');
    Route::patch('/assistants/{id}', [AssistantController::class, 'update'])->name('assistants.update');
    Route::delete('/assistants/{id}', [AssistantController::class, 'destroy'])->name('assistants.destroy');

    Route::prefix('assistants/{assistant}')->group(function () {
        Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::put('/settings/model', [SettingsController::class, 'selectModel'])->name('settings.selectModel');
        Route::put('/settings/voice-model', [SettingsController::class, 'selectVoiceModel'])->name('settings.selectVoiceModel');
        Route::put('/settings/voice', [SettingsController::class, 'updateVoice'])->name('settings.updateVoice');
        Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
        Route::get('/conversations/{id}/messages', [ConversationController::class, 'show'])->name('conversations.show');
        Route::post('/conversations/{id}/messages', [ConversationController::class, 'sendMessage'])->name('conversations.sendMessage');
        Route::delete('/conversations/{id}', [ConversationController::class, 'destroy'])->name('conversations.destroy');
        Route::patch('/conversations/{id}', [ConversationController::class, 'update'])->name('conversations.update');
        Route::get('/conversations/{id}/memory', [ConversationMemoryController::class, 'show'])->name('memory.show');
        Route::put('/conversations/{id}/memory', [ConversationMemoryController::class, 'update'])->name('memory.update');
        Route::post('/conversations/{id}/memory/summarize', [ConversationMemoryController::class, 'summarize'])->name('memory.summarize');
        Route::post('/conversations/{id}/memory/unlock', [ConversationMemoryController::class, 'unlock'])->name('memory.unlock');
		Route::get('/prompt', [AssistantPromptController::class, 'show'])->name('prompt.show');
		Route::post('/prompt', [AssistantPromptController::class, 'store'])->name('prompt.store');
		Route::put('/prompt', [AssistantPromptController::class, 'update'])->name('prompt.update');
		Route::delete('/prompt', [AssistantPromptController::class, 'destroy'])->name('prompt.destroy');
		Route::get('/memory-prompt', [AssistantMemoryPromptController::class, 'show'])->name('memory-prompt.show');
		Route::put('/memory-prompt', [AssistantMemoryPromptController::class, 'update'])->name('memory-prompt.update');

        Route::post('/voice/transcribe', [VoiceController::class, 'transcribe'])->name('voice.transcribe');
        Route::post('/voice/synthesize', [VoiceController::class, 'synthesize'])->name('voice.synthesize');

        Route::get('/emotions', [EmotionController::class, 'index'])->name('emotions.index');
        Route::prefix('/emotions')->name('assistants.emotions.')->group(function () {
            Route::post('/', [AssistantEmotionController::class, 'store'])->name('store');
            Route::post('/{emotion}', [AssistantEmotionController::class, 'update'])->name('update');
            Route::delete('/{emotion}', [AssistantEmotionController::class, 'destroy'])->name('destroy');
        });
    });

    Route::get('/ai-providers', [AiProviderController::class, 'index'])->name('ai-providers.index');
    Route::post('/ai-providers', [AiProviderController::class, 'store'])->name('ai-providers.store');
    Route::patch('/ai-providers/{id}', [AiProviderController::class, 'update'])->name('ai-providers.update');
    Route::delete('/ai-providers/{id}', [AiProviderController::class, 'destroy'])->name('ai-providers.destroy');

    Route::post('/ai-providers/{provider}/models', [AiModelController::class, 'store'])->name('ai-models.store');
    Route::patch('/ai-providers/{provider}/models/{model}', [AiModelController::class, 'update'])->name('ai-models.update');
    Route::delete('/ai-providers/{provider}/models/{model}', [AiModelController::class, 'destroy'])->name('ai-models.destroy');

    Route::get('/voice-providers', [VoiceProviderController::class, 'index'])->name('voice-providers.index');
    Route::patch('/voice-providers/{id}', [VoiceProviderController::class, 'updatePrompt'])->name('voice-providers.updatePrompt');
    Route::patch('/voice-providers/{provider}/models/{model}', [VoiceModelController::class, 'updatePrompt'])->name('voice-models.updatePrompt');

    Route::get('/archives', [ArchiveController::class, 'index'])->name('archives.index');
    Route::get('/archives/{id}', [ArchiveController::class, 'show'])->name('archives.show');
    Route::get('/archives/{id}/export', [ArchiveController::class, 'export'])->name('archives.export');
    Route::post('/archives', [ArchiveController::class, 'save'])->name('archives.store');
    Route::post('/archives/{id}', [ArchiveController::class, 'save'])->name('archives.save');

});
