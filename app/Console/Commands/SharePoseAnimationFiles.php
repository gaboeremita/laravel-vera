<?php

namespace App\Console\Commands;

use App\Actions\StorePoseAnimation;
use App\Models\PoseAnimationFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SharePoseAnimationFiles extends Command
{
    protected $signature = 'poses:share-files';

    protected $description = 'Rename stored pose animations after their content, so identical files are kept once and shared';

    public function handle(): int
    {
        $renamed = 0;
        $missing = 0;
        $before = PoseAnimationFile::query()->distinct()->count('path');

        PoseAnimationFile::query()->select(['disk', 'path'])->distinct()->get()->each(function (PoseAnimationFile $stored) use (&$renamed, &$missing): void {
            $disk = Storage::disk($stored->disk);
            if (! $disk->exists($stored->path)) {
                $this->warn("Missing {$stored->disk}:{$stored->path}, left as it is.");
                $missing++;

                return;
            }

            $target = StorePoseAnimation::pathFor(hash('sha256', $disk->get($stored->path)), pathinfo($stored->path, PATHINFO_EXTENSION));
            if ($target === $stored->path) {
                return;
            }

            if (! $disk->exists($target)) {
                $disk->copy($stored->path, $target);
            }
            PoseAnimationFile::where('disk', $stored->disk)->where('path', $stored->path)->update(['path' => $target]);
            PoseAnimationFile::releaseStorage($stored->disk, $stored->path);
            $renamed++;
        });

        $after = PoseAnimationFile::query()->distinct()->count('path');
        $this->info("Renamed {$renamed} stored files; {$before} distinct files became {$after}.".($missing > 0 ? " {$missing} missing." : ''));

        return self::SUCCESS;
    }
}
