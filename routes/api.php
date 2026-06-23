<?php

use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\EmotionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/conversations/{id}/messages', [ConversationController::class, 'sendMessage']);
	Route::get('/conversations', [ConversationController::class, 'index']);
	Route::get('/conversations/{id}/messages', [ConversationController::class, 'show']);
	Route::post('/conversations', [ConversationController::class, 'store']);
	Route::delete('/conversations/{id}', [ConversationController::class, 'destroy']);
	Route::patch('/conversations/{id}', [ConversationController::class, 'update']);
	Route::get('/emotions', [EmotionController::class, 'index']);
});
