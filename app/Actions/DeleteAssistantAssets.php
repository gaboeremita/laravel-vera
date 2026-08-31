<?php

namespace App\Actions;

use App\Models\Assistant;
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
            ->merge($assistant->poses->map(fn ($pose) => $pose->animationFile))
            ->filter()
            ->map(fn ($file) => ['disk' => $file->disk, 'path' => $file->path]);

        DB::transaction(fn () => $assistant->delete());

        $files->each(fn (array $file) => Storage::disk($file['disk'])->delete($file['path']));
    }
}
