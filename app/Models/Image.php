<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Image extends Model
{
	protected $fillable = [
		'path',
		'disk',
		'mime_type',
		'size',
		'original_name',
	];

	public function imageable(): MorphTo
	{
		return $this->morphTo();
	}

	/**
	 * Get the full accessible URL for this image.
	 */
	public function getUrlAttribute(): string
	{
		return Storage::disk($this->disk)->url($this->path);
	}

	/**
	 * Decode a base64 image, save to storage, and attach to a model.
	 */
	public static function storeFromBase64(string $base64, Model $imageable, string $storagePath): static
	{
		$imageData = base64_decode($base64);
		$mimeType = self::detectMimeType($imageData);
		$extension = self::mimeToExtension($mimeType);
		$filename = Str::uuid() . '.' . $extension;
		$path = "{$storagePath}/{$filename}";

		Storage::disk('public')->put($path, $imageData);

		return $imageable->image()->create([
			'path' => $path,
			'disk' => 'public',
			'mime_type' => $mimeType,
			'size' => strlen($imageData),
		]);
	}

	private static function detectMimeType(string $data): string
	{
		$signatures = [
			"\x89PNG" => 'image/png',
			"\xFF\xD8\xFF" => 'image/jpeg',
			"GIF87a" => 'image/gif',
			"GIF89a" => 'image/gif',
			"RIFF" => 'image/webp',
		];

		foreach ($signatures as $signature => $mime) {
			if (str_starts_with($data, $signature)) {
				return $mime;
			}
		}

		return 'image/jpeg';
	}

	private static function mimeToExtension(string $mime): string
	{
		return match ($mime) {
			'image/png' => 'png',
			'image/gif' => 'gif',
			'image/webp' => 'webp',
			default => 'jpg',
		};
	}
}
