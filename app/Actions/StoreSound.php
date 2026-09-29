<?php

namespace App\Actions;

use App\Models\Sound;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoreSound
{
    /**
     * Stores the file under its content hash, or returns the sound already stored with that hash.
     */
    public function handle(UploadedFile $file): Sound
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $existing = Sound::where('hash', $hash)->first();
        if ($existing !== null) {
            return $existing;
        }

        $path = $file->storeAs('sounds', $hash.'.'.($file->guessExtension() ?? $file->getClientOriginalExtension()), 'public');

        try {
            return Sound::create([
                'hash' => $hash,
                'disk' => 'public',
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'original_name' => $file->getClientOriginalName(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }
    }

    /**
     * Deletes the sound and its file once nothing uses it.
     */
    public function releaseIfUnused(Sound $sound): void
    {
        if ($sound->items()->exists()) {
            return;
        }

        Storage::disk($sound->disk)->delete($sound->path);
        $sound->delete();
    }
}
