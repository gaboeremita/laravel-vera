<?php

namespace App\Actions;

use App\Models\PassageLink;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkPassages
{
    /**
     * Links two passages both ways, removing any link either of them had.
     *
     * @throws ValidationException
     */
    public function link(Region $region, string $passageId, Region $target, string $targetPassageId): void
    {
        if ($region->passage($passageId) === null) {
            throw ValidationException::withMessages(['passage' => 'There is no passage with this id in the region.']);
        }
        if ($target->world_id !== $region->world_id) {
            throw ValidationException::withMessages(['targetRegionId' => 'The target region belongs to another world.']);
        }
        if ($target->passage($targetPassageId) === null) {
            throw ValidationException::withMessages(['targetPassageId' => 'There is no passage with this id in the target region.']);
        }
        if ($target->is($region) && $targetPassageId === $passageId) {
            throw ValidationException::withMessages(['targetPassageId' => 'A passage cannot lead to itself.']);
        }

        DB::transaction(function () use ($region, $passageId, $target, $targetPassageId): void {
            $this->removeLink($region, $passageId);
            $this->removeLink($target, $targetPassageId);

            PassageLink::create(['region_id' => $region->id, 'passage_id' => $passageId, 'target_region_id' => $target->id, 'target_passage_id' => $targetPassageId]);
            PassageLink::create(['region_id' => $target->id, 'passage_id' => $targetPassageId, 'target_region_id' => $region->id, 'target_passage_id' => $passageId]);
        });
    }

    public function unlink(Region $region, string $passageId): void
    {
        DB::transaction(fn () => $this->removeLink($region, $passageId));
    }

    private function removeLink(Region $region, string $passageId): void
    {
        $link = PassageLink::where('region_id', $region->id)->where('passage_id', $passageId)->first();
        if ($link === null) {
            return;
        }

        PassageLink::where('region_id', $link->target_region_id)->where('passage_id', $link->target_passage_id)->delete();
        $link->delete();
    }
}
