<?php

namespace App\Actions;

use App\Models\Pose;
use App\Models\PoseAnimationFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StorePoseAnimation
{
    private const DISK = 'public';

    /**
     * Stores an uploaded animation under a name taken from its content, so
     * identical uploads share one file and one URL, and attaches it to the pose.
     */
    public function handle(Pose $pose, UploadedFile $file): PoseAnimationFile
    {
        $previous = $pose->animationFile;
        // The extension comes from the client's filename: playback picks its
        // loader from it, and MIME sniffing reads a .vrma file as .glb.
        $path = self::pathFor(hash_file('sha256', $file->getRealPath()), $file->getClientOriginalExtension());

        if (! Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->putFileAs(dirname($path), $file, basename($path));
        }

        try {
            $animationFile = $pose->animationFile()->updateOrCreate([], [
                'path' => $path,
                'disk' => self::DISK,
                'mime_type' => 'application/octet-stream',
                'size' => $file->getSize(),
                'original_name' => $file->getClientOriginalName(),
            ]);
        } catch (\Throwable $e) {
            PoseAnimationFile::releaseStorage(self::DISK, $path);
            throw $e;
        }

        if ($previous) {
            PoseAnimationFile::releaseStorage($previous->disk, $previous->path);
        }

        return $animationFile;
    }

    public static function pathFor(string $contentHash, string $extension): string
    {
        return 'poses/'.$contentHash.'.'.strtolower($extension);
    }
}
