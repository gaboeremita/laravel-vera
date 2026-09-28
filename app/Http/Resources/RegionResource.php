<?php

namespace App\Http\Resources;

use App\Http\Controllers\Api\ActivityTermsController;
use App\Models\ActivityTerms;
use App\Models\PassageLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class RegionResource extends JsonResource
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
            'worldId' => $this->world_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'environmentUrl' => $this->environment_disk && $this->environment_path ? Storage::disk($this->environment_disk)->url($this->environment_path) : null,
            'assistantContextPrompt' => $this->assistant_context_prompt,
            'npcContextPrompt' => $this->npc_context_prompt,
            'settings' => $this->settings,
            'layout' => $this->layout ?? ['floors' => [], 'zones' => [], 'objects' => [], 'passages' => []],
            'cardImageUrl' => $this->whenLoaded('cardImage', fn () => $this->cardImage?->url),
            'portraitImageUrl' => $this->whenLoaded('portraitImage', fn () => $this->portraitImage?->url),
            'trackUrl' => $this->whenLoaded('track', fn () => $this->track?->url),
            'trackOriginalName' => $this->whenLoaded('track', fn () => $this->track?->original_name),
            'activityTerms' => $this->whenLoaded('activityTerms', fn () => $this->activityTerms->map(fn (ActivityTerms $terms) => [
                ...ActivityTermsController::present($terms),
                'requiredItemName' => $terms->requiredItem?->name,
            ])->values()),
            'links' => $this->whenLoaded('passageLinks', fn () => $this->passageLinks->map(fn (PassageLink $link) => [
                'passageId' => $link->passage_id,
                'targetRegionId' => $link->target_region_id,
                'targetRegionName' => $link->targetRegion->name,
                'targetPassageId' => $link->target_passage_id,
                'targetPassageName' => $link->targetRegion->passage($link->target_passage_id)['name'] ?? $link->target_passage_id,
            ])->values()),
        ];
    }
}
