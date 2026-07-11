<?php

use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\EmotionController;
use App\Http\Controllers\Api\LorebookController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
	Route::get('/user', fn (Request $request) => $request->user())->name('user.show');
	Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
	Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

	Route::prefix('assistants/{assistant}')->group(function () {
		Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
		Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
		Route::get('/conversations/{id}/messages', [ConversationController::class, 'show'])->name('conversations.show');
		Route::post('/conversations/{id}/messages', [ConversationController::class, 'sendMessage'])->name('conversations.sendMessage');
		Route::delete('/conversations/{id}', [ConversationController::class, 'destroy'])->name('conversations.destroy');
		Route::patch('/conversations/{id}', [ConversationController::class, 'update'])->name('conversations.update');
	});

	Route::get('/emotions', [EmotionController::class, 'index'])->name('emotions.index');
	Route::get('/lorebook', [LorebookController::class, 'show'])->name('lorebook.show');
	Route::post('/lorebook', [LorebookController::class, 'save'])->name('lorebook.save');

});
