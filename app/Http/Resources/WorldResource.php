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
            'environment_url' => $this->environment_disk && $this->environment_path ? Storage::disk($this->environment_disk)->url($this->environment_path) : null,
            'assistant_context_prompt' => $this->assistant_context_prompt,
            'npc_context_prompt' => $this->npc_context_prompt,
            'settings' => $this->settings,
            'residents' => $this->relationLoaded('residents')
                ? WorldResidentResource::collection($this->residents)
                : [],
        ];
    }
}
