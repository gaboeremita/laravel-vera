<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'basePrice' => $this->base_price,
            'contents' => $this->contents,
            'useRequirement' => $this->use_requirement,
            'consumedOnUse' => $this->consumed_on_use,
            'releasesCredits' => $this->releases_credits,
            'releasesItems' => $this->releases_items ?? [],
            'cardImageUrl' => $this->whenLoaded('cardImage', fn () => $this->cardImage?->url),
            'usage' => $this->when(isset($this->usage), fn () => $this->usage),
        ];
    }
}
