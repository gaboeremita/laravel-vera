<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\EmbedArchiveEntry;
use App\Models\Archive;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArchiveController extends Controller
{
	/**
	 * List all archives belonging to the authenticated user.
	 */
	public function index(Request $request): JsonResponse
	{
		$archives = $request->user()
			->archives()
			->get(['id', 'name']);

		return response()->json($archives);
	}

	/**
	 * Return a specific archive with its entries and tags.
	 */
	public function show(Request $request, int $id): JsonResponse
	{
		$archive = $request->user()
			->archives()
			->with('entries.tags')
			->findOrFail($id);

		return response()->json($archive);
	}

	/**
	 * Create or update an archive and all its entries in a single save.
	 * If {id} is provided, updates that archive. Otherwise creates a new one.
	 */
	public function save(Request $request, ?int $id = null): JsonResponse
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

		return DB::transaction(function () use ($request, $validated, $id) {
			if ($id) {
				$archive = $request->user()->archives()->findOrFail($id);
				$archive->update([
					'name' => $validated['name'],
					'description' => $validated['description'],
				]);
			} else {
				$archive = $request->user()->archives()->create([
					'name' => $validated['name'],
					'description' => $validated['description'],
				]);
			}

			$incomingIds = collect($validated['entries'])
				->pluck('id')
				->filter()
				->all();

			$archive->entries()
				->whereNotIn('id', $incomingIds)
				->delete();

			foreach ($validated['entries'] as $entryData) {
				$entry = $archive->entries()->updateOrCreate(
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

				if ($entry->wasChanged('content') || $entry->wasRecentlyCreated) {
					EmbedArchiveEntry::dispatch($entry);
				}
			}

			return response()->json(
				$archive->fresh(['entries.tags']),
			);
		});
	}

	/**
	 * Export an archive and its entries as a Markdown file.
	 */
	public function export(Request $request, int $id): BinaryFileResponse
	{
		$archive = $request->user()
			->archives()
			->with('entries.tags')
			->findOrFail($id);

		$markdown = "# {$archive->name}\n\n{$archive->description}\n";

		foreach ($archive->entries as $entry) {
			$markdown .= "\n## {$entry->title}\n\n";

			if (! empty($entry->keywords)) {
				$markdown .= '**Keywords:** '.implode(', ', $entry->keywords)."\n\n";
			}

			if ($entry->tags->isNotEmpty()) {
				$markdown .= '**Tags:** '.$entry->tags->pluck('name')->implode(', ')."\n\n";
			}

			$markdown .= "{$entry->content}\n";
		}

		$filename = Str::slug($archive->name).'.md';
		$path = 'exports/'.Str::uuid().'.md';

		Storage::disk('local')->put($path, $markdown);

		return response()
			->download(Storage::disk('local')->path($path), $filename)
			->deleteFileAfterSend(true);
	}
}
