<?php

namespace App\Http\Resources;

use App\Models\PassageLink;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorldResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'assistantContextPrompt' => $this->assistant_context_prompt,
            'npcContextPrompt' => $this->npc_context_prompt,
            'spawnRegionId' => $this->spawn_region_id,
            'spawnPassageId' => $this->spawn_passage_id,
            'hasSpawn' => $this->spawnPassage() !== null,
            'narratorModelId' => $this->narrator_model_id,
            'reviewReveals' => $this->review_reveals ?? true,
            'cardImageUrl' => $this->whenLoaded('cardImage', fn () => $this->cardImage?->url),
            'portraitImageUrl' => $this->whenLoaded('portraitImage', fn () => $this->portraitImage?->url),
            'regionCount' => $this->whenCounted('regions'),
            'regions' => $this->whenLoaded('regions', fn () => $this->regions->map(fn (Region $region) => [
                'id' => $region->id,
                'name' => $region->name,
                'passages' => collect($region->layout['passages'] ?? [])->map(fn (array $passage) => ['id' => $passage['id'], 'name' => $passage['name']])->values(),
                'links' => $region->passageLinks->map(fn (PassageLink $link) => [
                    'passageId' => $link->passage_id,
                    'targetRegionId' => $link->target_region_id,
                    'targetPassageId' => $link->target_passage_id,
                ])->values(),
                'warnings' => [
                    'noPassages' => empty($region->layout['passages']),
                    'unlinkedPassages' => count($region->layout['passages'] ?? []) - $region->passageLinks->count(),
                ],
            ])->values()),
            'residents' => $this->whenLoaded('residents', fn () => WorldResidentResource::collection($this->residents)),
        ];
    }
}
