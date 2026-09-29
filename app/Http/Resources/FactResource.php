<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'topic' => $this->topic,
            'content' => $this->content,
            'disclosure' => $this->disclosure,
            'relayResidentIds' => $this->relays->pluck('id')->all(),
            'usage' => $this->known_facts_count ?? 0,
        ];
    }
}
