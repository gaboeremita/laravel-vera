<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorldResidentResource extends JsonResource
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
            'assistant' => [
                'id' => $this->assistant->id,
                'name' => $this->assistant->name,
                'kind' => $this->assistant->kind->value,
                'vrm_url' => $this->assistant->vrm?->url,
                'opening_message' => $this->assistant->opening_message,
            ],
            'position' => $this->position,
            'rotation' => $this->rotation,
            'behavior' => $this->behavior->value,
            'behavior_settings' => $this->behavior_settings,
        ];
    }
}
