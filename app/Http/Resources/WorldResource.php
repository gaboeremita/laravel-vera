<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'environmentUrl' => $this->environment_disk && $this->environment_path ? Storage::disk($this->environment_disk)->url($this->environment_path) : null,
            'assistantContextPrompt' => $this->assistant_context_prompt,
            'npcContextPrompt' => $this->npc_context_prompt,
            'settings' => $this->settings,
            'cardImageUrl' => $this->whenLoaded('cardImage', fn () => $this->cardImage?->url),
            'portraitImageUrl' => $this->whenLoaded('portraitImage', fn () => $this->portraitImage?->url),
            'trackUrl' => $this->whenLoaded('track', fn () => $this->track?->url),
            'trackOriginalName' => $this->whenLoaded('track', fn () => $this->track?->original_name),
            'residents' => $this->relationLoaded('residents')
                ? WorldResidentResource::collection($this->residents)
                : [],
        ];
    }
}
