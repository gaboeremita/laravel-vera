<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assistant;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AssistantController extends Controller
{
	public function index(Request $request): JsonResponse
	{
		$assistants = $request->user()
			->assistants()
			->withCount(['emotions'])
			->get()
			->map(function (Assistant $assistant) use ($request) {
				$pivotId = $assistant->pivot->id;

				// Default emotion image for card avatar
				$defaultEmotion = $assistant->emotions()
					->where('name', 'default')
					->with('image')
					->first();

				// Conversation stats via the pivot
				$stats = DB::table('conversations')
					->where('assistant_user_id', $pivotId)
					->selectRaw('count(*) as conversations_count, max(updated_at) as last_activity')
					->first();

				return [
					'id' => $assistant->id,
					'name' => $assistant->name,
					'slug' => $assistant->slug,
					'description' => $assistant->description,
					'image_url' => $defaultEmotion?->image?->url,
					'conversations_count' => (int) ($stats->conversations_count ?? 0),
					'last_activity' => $stats->last_activity,
				];
			});

		return response()->json($assistants);
	}

	public function show(Request $request, int $id): JsonResponse
	{
		$assistant = $request->user()
			->assistants()
			->findOrFail($id);

		$mapEmotion = fn ($emotion) => [
			'id' => $emotion->id,
			'name' => $emotion->name,
			'image_url' => $emotion->image?->url,
		];

		$emotions = $assistant->emotions()
			->where('restricted', false)
			->with('image')
			->get()
			->map($mapEmotion);

		$restrictedEmotions = $assistant->emotions()
			->where('restricted', true)
			->with('image')
			->get()
			->map($mapEmotion);

		return response()->json([
			'id' => $assistant->id,
			'name' => $assistant->name,
			'slug' => $assistant->slug,
			'description' => $assistant->description,
			'opening_message' => $assistant->opening_message,
			'prompt' => $assistant->prompt,
			'archive_id' => $assistant->archive_id,
			'emotions' => $emotions,
			'restricted_emotions' => $restrictedEmotions,
		]);
	}

	public function store(Request $request): JsonResponse
	{
		if (is_string($request->input('prompt'))) {
			$request->merge(['prompt' => json_decode($request->input('prompt'), true)]);
		}

		$validated = $request->validate([
			'name' => ['required', 'string', 'max:255'],
			'slug' => ['required', 'string', 'max:255', 'unique:assistants,slug'],
			'description' => ['nullable', 'string'],
			'opening_message' => ['nullable', 'string'],
			'prompt' => ['nullable', 'array'],
			'archive_id' => ['nullable', 'integer', 'exists:archives,id'],
			'emotions' => ['required', 'array', 'min:1'],
			'emotions.*.name' => ['required', 'string', 'max:255'],
			'emotions.*.image' => ['required', 'file', 'image', 'max:10480'],
			'restricted_emotions' => ['sometimes', 'array'],
			'restricted_emotions.*.name' => ['required', 'string', 'max:255'],
			'restricted_emotions.*.image' => ['required', 'file', 'image', 'max:10480'],
		]);

		// At least one emotion must be named "default"
		$hasDefault = collect($validated['emotions'])
			->contains(fn ($e) => $e['name'] === 'default');

		if (!$hasDefault) {
			return response()->json([
				'message' => 'A "default" emotion is required.',
				'errors' => ['emotions' => ['A "default" emotion is required.']],
			], 422);
		}

		$assistant = DB::transaction(function () use ($request, $validated) {
			$assistant = Assistant::create([
				'name' => $validated['name'],
				'slug' => $validated['slug'],
				'description' => $validated['description'] ?? null,
				'opening_message' => $validated['opening_message'] ?? null,
				'prompt' => $validated['prompt'] ?? [],
				'archive_id' => $validated['archive_id'] ?? null,
			]);

			// Create the pivot
			$request->user()->assistants()->attach($assistant->id);

			// Store emotions with images
			$storeEmotion = function (array $emotionData, bool $restricted) use ($assistant): void {
				$emotion = $assistant->emotions()->create([
					'name' => $emotionData['name'],
					'restricted' => $restricted,
				]);

				$path = $emotionData['image']->store("emotions/{$assistant->id}", 'public');

				$emotion->image()->create([
					'path' => $path,
					'disk' => 'public',
					'mime_type' => $emotionData['image']->getMimeType(),
					'size' => $emotionData['image']->getSize(),
				]);
			};

			foreach ($validated['emotions'] as $emotionData) {
				$storeEmotion($emotionData, false);
			}

			foreach ($validated['restricted_emotions'] ?? [] as $emotionData) {
				$storeEmotion($emotionData, true);
			}

			return $assistant;
		});

		return response()->json($assistant, 201);
	}

	public function update(Request $request, int $id): JsonResponse
	{
		$assistant = $request->user()
			->assistants()
			->findOrFail($id);

		$validated = $request->validate([
			'name' => ['sometimes', 'string', 'max:255'],
			'slug' => ['sometimes', 'string', 'max:255', "unique:assistants,slug,{$assistant->id}"],
			'description' => ['nullable', 'string'],
			'opening_message' => ['nullable', 'string'],
			'prompt' => ['nullable', 'array'],
			'archive_id' => ['nullable', 'integer', 'exists:archives,id'],
		]);

		$assistant->update($validated);

		return response()->json($assistant);
	}

	public function destroy(Request $request, int $id): JsonResponse
	{
		$request->user()
			->assistants()
			->findOrFail($id)
			->delete();

		return response()->json(['message' => 'Assistant deleted']);
	}
}