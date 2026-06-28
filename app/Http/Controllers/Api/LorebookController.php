<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\EmbedLoreEntry;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LorebookController extends Controller
{
	/**
	 * Return the user's lorebook with entries and tags, or null if none exists.
	 */
	public function show(Request $request): JsonResponse
	{
		$lorebook = $request->user()
			->lorebooks()
			->with('entries.tags')
			->first();

		if (! $lorebook) {
			return response()->json(null);
		}

		return response()->json($lorebook);
	}

	/**
	 * Create or update the user's lorebook and all its entries in a single save.
	 */
	public function save(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'name' => ['required', 'string', 'max:100'],
			'description' => ['required', 'string'],
			'entries' => ['present', 'array'],
			'entries.*.id' => ['sometimes', 'integer'],
			'entries.*.title' => ['required', 'string', 'max:100'],
			'entries.*.content' => ['required', 'string'],
			'entries.*.keywords' => ['nullable', 'array'],
			'entries.*.keywords.*' => ['string', 'max:50'],
			'entries.*.tags' => ['nullable', 'array'],
			'entries.*.tags.*' => ['string', 'max:50'],
		]);

		return DB::transaction(function () use ($request, $validated) {
			$lorebook = auth()->user()
				->lorebooks()
				->updateOrCreate(
					['user_id' => auth()->id()],
					[
						'name' => $validated['name'],
						'description' => $validated['description'],
					],
				);

			$incomingIds = collect($validated['entries'])
				->pluck('id')
				->filter()
				->all();

			$lorebook->entries()
				->whereNotIn('id', $incomingIds)
				->delete();

			foreach ($validated['entries'] as $entryData) {
				$entry = $lorebook->entries()->updateOrCreate(
					['id' => $entryData['id'] ?? null],
					[
						'title' => $entryData['title'],
						'content' => $entryData['content'],
						'keywords' => $entryData['keywords'] ?? [],
					],
				);

				if (isset($entryData['tags'])) {
					$tagIds = collect($entryData['tags'])->map(function (string $name) use ($request) {
						return Tag::firstOrCreate(
							['name' => $name, 'user_id' => auth()->id()],
						)->id;
					})->all();

					$entry->tags()->sync($tagIds);
				} else {
					$entry->tags()->detach();
				}

				// Dispatch embedding job only if content changed
				if ($entry->wasChanged('content') || $entry->wasRecentlyCreated) {
					EmbedLoreEntry::dispatch($entry);
				}
			}

			return response()->json(
				$lorebook->fresh(['entries.tags']),
			);
		});
	}
}
