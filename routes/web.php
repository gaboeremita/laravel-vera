<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\VadAssetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::get('/vendor/vad/{file}', VadAssetController::class)->where('file', '.*\.mjs$');

Route::get('/{any}', function () {
	return view('welcome');
})->where('any', '.*');