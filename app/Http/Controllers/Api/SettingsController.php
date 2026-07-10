<?php

namespace App\Http\Controllers\Api;

use App\Enums\Theme;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class SettingsController extends Controller
{
	public function show(Request $request): JsonResponse
	{
		$settings = $request->user()->settings;

		return response()->json([
			'selected_theme' => $settings?->data['theme'] ?? 'default',
			'available_themes' => array_column(Theme::cases(), 'value'),
		]);
	}

	public function update(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'theme' => ['required', 'string', new Enum(Theme::class)],
		]);

		$settings = $request->user()->settings()->firstOrCreate([], ['data' => []]);
		$settings->update(['data' => $validated]);

		return response()->json($settings->data);
	}
}
