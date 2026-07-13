<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Emotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AssistantEmotionController extends Controller
{
	public function store(Request $request, int $assistantId): JsonResponse
	{
		$assistant = $request->user()
			->assistants()
			->findOrFail($assistantId);

		$validated = $request->validate([
			'name' => ['required', 'string', 'max:255'],
			'image' => ['required', 'file', 'image', 'max:10480'],
		]);

		if ($assistant->emotions()->where('name', $validated['name'])->exists()) {
			return response()->json([
				'message' => 'This emotion name already exists.',
				'errors' => ['name' => ['This emotion name already exists.']],
			], 422);
		}

		$emotion = $assistant->emotions()->create([
			'name' => $validated['name'],
			'restricted' => false,
		]);

		$this->storeImageForEmotion($emotion, $validated['image'], $assistant->id);

		return response()->json([
			'id' => $emotion->id,
			'name' => $emotion->name,
			'image_url' => $emotion->image->url,
		], 201);
	}

	public function update(Request $request, int $assistantId, int $emotionId): JsonResponse
	{
		$assistant = $request->user()
			->assistants()
			->findOrFail($assistantId);

		$emotion = $assistant->emotions()->findOrFail($emotionId);

		$validated = $request->validate([
			'name' => ['sometimes', 'string', 'max:255'],
			'image' => ['sometimes', 'file', 'image', 'max:10480'],
		]);

		if (isset($validated['name']) && $validated['name'] !== $emotion->name) {
			if ($assistant->emotions()->where('name', $validated['name'])->exists()) {
				return response()->json([
					'message' => 'This emotion name already exists.',
					'errors' => ['name' => ['This emotion name already exists.']],
				], 422);
			}

			$emotion->update(['name' => $validated['name']]);
		}

		if (isset($validated['image'])) {
			if ($emotion->image) {
				Storage::disk($emotion->image->disk)->delete($emotion->image->path);
				$emotion->image->delete();
			}

			$this->storeImageForEmotion($emotion, $validated['image'], $assistant->id);
		}

		$emotion->load('image');

		return response()->json([
			'id' => $emotion->id,
			'name' => $emotion->name,
			'image_url' => $emotion->image?->url,
		]);
	}

	public function destroy(Request $request, int $assistantId, int $emotionId): JsonResponse
	{
		$assistant = $request->user()
			->assistants()
			->findOrFail($assistantId);

		$emotion = $assistant->emotions()->findOrFail($emotionId);

		if ($emotion->name === 'default') {
			return response()->json([
				'message' => 'The default emotion cannot be deleted.',
			], 422);
		}

		if ($emotion->image) {
			Storage::disk($emotion->image->disk)->delete($emotion->image->path);
		}

		$emotion->delete();

		return response()->json(['message' => 'Emotion deleted']);
	}

	private function storeImageForEmotion(Emotion $emotion, UploadedFile $file, int $assistantId): void
	{
		$path = $file->store("emotions/{$assistantId}", 'public');

		$emotion->image()->create([
			'path' => $path,
			'disk' => 'public',
			'mime_type' => $file->getMimeType(),
			'size' => $file->getSize(),
		]);
	}
}