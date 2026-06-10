<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
	public function login(Request $request): JsonResponse
	{
		$credentials = $request->validate([
			'email' => ['required', 'email'],
			'password' => ['required'],
		]);

		if (! Auth::attempt($credentials, true)) {
			return response()->json(['message' => 'Invalid credentials'], 401);
		}

		$request->session()->regenerate();

		return response()->json(['message' => 'Authenticated']);
	}

	public function logout(Request $request): JsonResponse
	{
		Auth::logout();
		$request->session()->invalidate();
		$request->session()->regenerateToken();

		return response()->json(['message' => 'Logged out']);
	}
}