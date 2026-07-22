<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VadAssetController extends Controller
{
	/**
	 * Files live under storage/, not public/ — nginx (via Herd/Valet) serves
	 * public/ files directly with a default mime.types table that has no .mjs
	 * mapping, sending application/octet-stream, which browsers refuse to
	 * execute as an ES module.
	 */
	public function __invoke(string $file): BinaryFileResponse
	{
		$file = basename($file);

		if (! str_ends_with($file, '.mjs')) {
			abort(404);
		}

		$path = storage_path("app/vad/{$file}");

		if (! is_file($path)) {
			abort(404);
		}

		return response()->file($path, [
			'Content-Type' => 'text/javascript',
		]);
	}
}
