<?php

namespace App\Models;

use App\Enums\AssistantKind;
use App\Enums\AssistantMode;
use App\Enums\AssistantPortraitType;
use App\Enums\Posture;
use Database\Factories\AssistantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;

#[Fillable(['name', 'slug', 'description', 'prompt', 'opening_message', 'archive_id', 'mode', 'agent_config', 'portrait_type', 'kind'])]
class Assistant extends Model
{
    /** @use HasFactory<AssistantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'prompt' => 'array',
            'mode' => AssistantMode::class,
            'agent_config' => 'array',
            'portrait_type' => AssistantPortraitType::class,
            'kind' => AssistantKind::class,
        ];
    }

    public function archive(): BelongsTo
    {
        return $this->belongsTo(Archive::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(AssistantUser::class)
            ->withTimestamps();
    }

    public function emotions(): HasMany
    {
        return $this->hasMany(Emotion::class);
    }

    public function poses(): HasMany
    {
        return $this->hasMany(Pose::class);
    }

    public function vrm(): MorphOne
    {
        return $this->morphOne(VrmFile::class, 'vrmable');
    }

    public function cardImage(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    public function worldResidents(): HasMany
    {
        return $this->hasMany(WorldResident::class);
    }

    /**
     * @return array{regular: array<string>, intimate: array<string>}
     */
    public function promptEmotionNames(): array
    {
        return [
            'regular' => $this->emotions()->where('restricted', false)->pluck('name')->toArray(),
            'intimate' => $this->emotions()->where('restricted', true)->pluck('name')->toArray(),
        ];
    }

    /**
     * @return array{available: array{regular: array<int, string>, restricted: array<int, string>}, standingOnly: array{regular: array<int, string>, restricted: array<int, string>}}
     */
    public function promptPoseNames(Posture $posture = Posture::Standing): array
    {
        $poses = $this->poses()->orderBy('id')->get(['name', 'posture', 'restricted']);
        $available = $poses->filter(fn (Pose $pose) => $pose->posture === $posture);
        $availableNames = $available->pluck('name');
        $standingOnly = $poses->filter(fn (Pose $pose) => $pose->posture === Posture::Standing && ! $availableNames->contains($pose->name));

        return ['available' => self::splitRestrictedPoses($available), 'standingOnly' => self::splitRestrictedPoses($standingOnly)];
    }

    /**
     * @param  Collection<int, Pose>  $poses
     * @return array{regular: array<int, string>, restricted: array<int, string>}
     */
    public static function splitRestrictedPoses(Collection $poses): array
    {
        [$restricted, $regular] = $poses->partition(fn (Pose $pose) => $pose->restricted);

        return ['regular' => $regular->pluck('name')->unique()->values()->all(), 'restricted' => $restricted->pluck('name')->unique()->values()->all()];
    }

    /**
     * The postures each pose exists in, by pose name, in the order the poses were made.
     *
     * @return array<string, array<int, string>>
     */
    public function posturesByPoseName(): array
    {
        return $this->poses()->orderBy('id')->get(['name', 'posture'])
            ->groupBy('name')
            ->map(fn ($versions) => $versions->map(fn (Pose $pose) => $pose->posture->value)->values()->all())
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function poseNames(): array
    {
        return $this->poses()->orderBy('id')->pluck('name')->unique()->values()->all();
    }
}
