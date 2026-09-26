<?php

namespace App\Actions;

use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\PoseAnimationFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteAssistantAssets
{
    public function handle(Assistant $assistant): void
    {
        $assistant->loadMissing(['vrm', 'cardImage', 'emotions.image', 'poses.animationFile']);

        /** @var Collection<int, array{disk: string, path: string}> $files */
        $files = collect([$assistant->vrm, $assistant->cardImage])
            ->merge($assistant->emotions->map(fn ($emotion) => $emotion->image))
            ->filter()
            ->map(fn ($file) => ['disk' => $file->disk, 'path' => $file->path]);
        if ($assistant->vrm?->lod_path !== null) {
            $files->push(['disk' => $assistant->vrm->disk, 'path' => $assistant->vrm->lod_path]);
        }
        /** @var Collection<int, array{disk: string, path: string}> $poseFiles */
        $poseFiles = $assistant->poses->map(fn ($pose) => $pose->animationFile)
            ->filter()
            ->map(fn ($file) => ['disk' => $file->disk, 'path' => $file->path]);

        DB::transaction(function () use ($assistant): void {
            Conversation::involving($assistant)->delete();
            $assistant->delete();
        });

        $files->each(fn (array $file) => Storage::disk($file['disk'])->delete($file['path']));
        $poseFiles->each(fn (array $file) => PoseAnimationFile::releaseStorage($file['disk'], $file['path']));
    }
}
