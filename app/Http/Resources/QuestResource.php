<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestResource extends JsonResource
{
    /**
     * @param  array<int, string>  $problems
     */
    public function __construct(mixed $resource, private readonly array $problems = [])
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $this->title,
            'campaignId' => $this->campaign_id,
            'definition' => $this->definition,
            'sessionCount' => (int) ($this->session_count ?? 0),
            'problems' => $this->problems,
        ];
    }
}
