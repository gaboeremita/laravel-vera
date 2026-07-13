<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Emotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmotionController extends Controller
{
	public function index(Request $request, int $assistant): JsonResponse
	{
		return response()->json(
			Emotion::with(['image', 'video'])
				->where('assistant_id', $assistant)
				->get()
				->map(fn (Emotion $emotion) => [
					'name' => $emotion->name,
					'image_url' => $emotion->image?->url,
					'video_url' => $emotion->video?->url,
				])
		);
	}

	public function store(Request $request, int $assistant): JsonResponse
	{
		$assistantModel = $request->user()->assistants()->findOrFail($assistant);

		$validated = $request->validate([
			'name' => ['required', 'string', 'max:255'],
			'image' => ['required', 'file', 'image', 'max:10480'],
		]);

		$emotion = $assistantModel->emotions()->create([
			'name' => $validated['name'],
			'restricted' => false,
		]);

		$path = $validated['image']->store("emotions/{$assistant}", 'public');

		$emotion->image()->create([
			'path' => $path,
			'disk' => 'public',
			'mime_type' => $validated['image']->getMimeType(),
			'size' => $validated['image']->getSize(),
		]);

		$emotion->load('image');

		return response()->json([
			'id' => $emotion->id,
			'name' => $emotion->name,
			'image_url' => $emotion->image?->url,
		], 201);
	}

	public function update(Request $request, int $assistant, int $emotion): JsonResponse
	{
		$assistantModel = $request->user()->assistants()->findOrFail($assistant);

		$validated = $request->validate([
			'image' => ['required', 'file', 'image', 'max:10480'],
		]);

		$emotionModel = $assistantModel->emotions()->findOrFail($emotion);

		if ($emotionModel->image) {
			Storage::disk($emotionModel->image->disk)->delete($emotionModel->image->path);
			$emotionModel->image()->delete();
		}

		$path = $validated['image']->store("emotions/{$assistant}", 'public');

		$emotionModel->image()->create([
			'path' => $path,
			'disk' => 'public',
			'mime_type' => $validated['image']->getMimeType(),
			'size' => $validated['image']->getSize(),
		]);

		$emotionModel->load('image');

		return response()->json([
			'id' => $emotionModel->id,
			'name' => $emotionModel->name,
			'image_url' => $emotionModel->image?->url,
		]);
	}

	public function destroy(Request $request, int $assistant, int $emotion): JsonResponse
	{
		$assistantModel = $request->user()->assistants()->findOrFail($assistant);

		$emotionModel = $assistantModel->emotions()->findOrFail($emotion);

		if ($emotionModel->image) {
			Storage::disk($emotionModel->image->disk)->delete($emotionModel->image->path);
		}

		$emotionModel->delete();

		return response()->json(['message' => 'Emotion deleted']);
	}
}
