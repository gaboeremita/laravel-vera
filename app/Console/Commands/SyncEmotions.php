<?php

namespace App\Console\Commands;

use App\Models\Emotion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SyncEmotions extends Command
{
	protected $signature = 'vera:sync-emotions';
	protected $description = 'Sync emotions from the source directory — adds new, removes missing';

	private const BASE_DIR = 'emotions';
	private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
	private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov'];

	public function handle(): int
	{
		$basePath = storage_path('app/private/' . self::BASE_DIR);

		if (! File::isDirectory($basePath . '/images')) {
			$this->error("Directory not found: {$basePath}/images");
			$this->info('Create it and drop your emotion images there.');
			return self::FAILURE;
		}

		// Collect all emotion names present in the source directories
		$sourceNames = $this->collectSourceNames($basePath);

		// Add new emotions
		$this->syncImages($basePath . '/images', restricted: false);
		$this->syncImages($basePath . '/images/restricted', restricted: true);
		$this->syncVideos($basePath . '/videos', restricted: false);
		$this->syncVideos($basePath . '/videos/restricted', restricted: true);

		// Remove emotions that no longer have source files
		$this->purgeOrphans($sourceNames);

		return self::SUCCESS;
	}

	/**
	 * Collect all emotion names from image files in the source directories.
	 * Only images count — an emotion must have an image to exist.
	 */
	private function collectSourceNames(string $basePath): array
	{
		$names = [];

		$dirs = [
			$basePath . '/images',
			$basePath . '/images/restricted',
		];

		foreach ($dirs as $dir) {
			if (! File::isDirectory($dir)) {
				continue;
			}

			foreach (File::files($dir) as $file) {
				$extension = strtolower($file->getExtension());

				if (in_array($extension, self::IMAGE_EXTENSIONS)) {
					$names[] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
				}
			}
		}

		return $names;
	}

	private function syncImages(string $path, bool $restricted): void
	{
		if (! File::isDirectory($path)) {
			return;
		}

		$label = $restricted ? 'restricted images' : 'images';
		$this->info("Syncing {$label}...");
		$added = 0;
		$skipped = 0;

		foreach (File::files($path) as $file) {
			$extension = strtolower($file->getExtension());

			if (! in_array($extension, self::IMAGE_EXTENSIONS)) {
				continue;
			}

			$name = pathinfo($file->getFilename(), PATHINFO_FILENAME);

			if (Emotion::where('name', $name)->exists()) {
				$this->line("  Skipped: {$name} (already exists)");
				$skipped++;
				continue;
			}

			$subdir = $restricted ? 'images/restricted' : 'images';
			$storagePath = self::BASE_DIR . '/' . $subdir . '/' . $file->getFilename();
			Storage::disk('public')->put($storagePath, File::get($file));

			$emotion = Emotion::create([
				'name' => $name,
				'restricted' => $restricted,
			]);

			$emotion->image()->create([
				'path' => $storagePath,
				'disk' => 'public',
				'mime_type' => File::mimeType($file->getPathname()),
				'size' => $file->getSize(),
			]);

			$this->info("  Added: {$name}" . ($restricted ? ' [restricted]' : ''));
			$added++;
		}

		$this->info("  Total — Added: {$added}, Skipped: {$skipped}");
		$this->newLine();
	}

	private function syncVideos(string $path, bool $restricted): void
	{
		if (! File::isDirectory($path)) {
			return;
		}

		$label = $restricted ? 'restricted videos' : 'videos';
		$this->info("Syncing {$label}...");
		$added = 0;
		$skipped = 0;
		$orphaned = 0;

		foreach (File::files($path) as $file) {
			$extension = strtolower($file->getExtension());

			if (! in_array($extension, self::VIDEO_EXTENSIONS)) {
				continue;
			}

			$name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
			$emotion = Emotion::where('name', $name)->first();

			if (! $emotion) {
				$this->warn("  Orphaned: {$file->getFilename()} (no emotion '{$name}' exists)");
				$orphaned++;
				continue;
			}

			if ($emotion->video) {
				$this->line("  Skipped: {$name} (video already exists)");
				$skipped++;
				continue;
			}

			$subdir = $restricted ? 'videos/restricted' : 'videos';
			$storagePath = self::BASE_DIR . '/' . $subdir . '/' . $file->getFilename();
			Storage::disk('public')->put($storagePath, File::get($file));

			$emotion->video()->create([
				'path' => $storagePath,
				'disk' => 'public',
				'mime_type' => File::mimeType($file->getPathname()),
				'size' => $file->getSize(),
			]);

			$this->info("  Added video: {$name}" . ($restricted ? ' [restricted]' : ''));
			$added++;
		}

		$this->info("  Total — Added: {$added}, Skipped: {$skipped}, Orphaned: {$orphaned}");
	}

	/**
	 * Remove emotions from the DB that no longer have a source image file.
	 * Also cleans up their stored files from public storage.
	 */
	private function purgeOrphans(array $sourceNames): void
	{
		$this->newLine();
		$this->info('Checking for orphaned emotions...');
		$removed = 0;

		$dbEmotions = Emotion::all();

		foreach ($dbEmotions as $emotion) {
			if (in_array($emotion->name, $sourceNames)) {
				continue;
			}

			// Delete stored image file
			if ($emotion->image) {
				Storage::disk($emotion->image->disk)->delete($emotion->image->path);
				$emotion->image->delete();
			}

			// Delete stored video file
			if ($emotion->video) {
				Storage::disk($emotion->video->disk)->delete($emotion->video->path);
				$emotion->video->delete();
			}

			$this->warn("  Removed: {$emotion->name}");
			$emotion->delete();
			$removed++;
		}

		$this->info("  Orphans removed: {$removed}");
	}
}
