<?php

namespace App\Http\Requests;

use App\Models\Item;
use App\Models\World;
use App\Models\WorldResident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var World $world */
        $world = $this->route('world');
        $item = $this->route('item');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('items', 'name')->where('world_id', $world->id)->ignore($item instanceof Item ? $item->id : null)],
            'description' => ['required', 'string'],
            'basePrice' => ['nullable', 'integer', 'min:0'],
            'contents' => ['nullable', 'string'],
            'useRequirement' => ['nullable', 'string'],
            'consumedOnUse' => ['boolean'],
            'releasesCredits' => ['integer', 'min:0'],
            'releasesItems' => ['array'],
            'releasesItems.*.itemId' => ['required', 'integer', Rule::exists('items', 'id')->where('world_id', $world->id)],
            'releasesItems.*.quantity' => ['required', 'integer', 'min:1'],
            'revealsFactId' => ['nullable', 'integer', Rule::exists('facts', 'id')->where(fn ($query) => $query->whereIn('world_resident_id', WorldResident::where('world_id', $world->id)->select('id')))],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForItem(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'base_price' => $validated['basePrice'] ?? null,
            'contents' => $validated['contents'] ?? null,
            'use_requirement' => $validated['useRequirement'] ?? null,
            'consumed_on_use' => $validated['consumedOnUse'] ?? false,
            'releases_credits' => $validated['releasesCredits'] ?? 0,
            'releases_items' => collect($validated['releasesItems'] ?? [])->map(fn (array $entry) => ['itemId' => (int) $entry['itemId'], 'quantity' => (int) $entry['quantity']])->values()->all(),
            'reveals_fact_id' => $validated['revealsFactId'] ?? null,
        ];
    }
}
