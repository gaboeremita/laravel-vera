<?php

namespace App\Http\Requests;

use App\Models\Region;
use App\Models\World;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateActivityTermsRequest extends FormRequest
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
        $itemOfWorld = Rule::exists('items', 'id')->where('world_id', $world->id);

        return [
            'requiredItemId' => ['nullable', 'integer', $itemOfWorld],
            'consumesRequired' => ['boolean'],
            'cost' => ['integer', 'min:0'],
            'givesCredits' => ['integer', 'min:0'],
            'givesItems' => ['array'],
            'givesItems.*.itemId' => ['required', 'integer', 'distinct', $itemOfWorld],
            'givesItems.*.quantity' => ['required', 'integer', 'min:1'],
            'requirement' => ['nullable', 'string'],
            'outcome' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Region $region */
                $region = $this->route('region');
                if (! array_key_exists((string) $this->route('activity'), $region->objectActivities((string) $this->route('object')))) {
                    $validator->errors()->add('activity', 'That object offers no activity with this id.');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForTerms(): array
    {
        $validated = $this->validated();

        return [
            'required_item_id' => $validated['requiredItemId'] ?? null,
            'consumes_required' => $validated['consumesRequired'] ?? false,
            'cost' => $validated['cost'] ?? 0,
            'gives_credits' => $validated['givesCredits'] ?? 0,
            'gives_items' => collect($validated['givesItems'] ?? [])->map(fn (array $entry) => ['itemId' => (int) $entry['itemId'], 'quantity' => (int) $entry['quantity']])->values()->all(),
            'requirement' => filled($validated['requirement'] ?? null) ? $validated['requirement'] : null,
            'outcome' => filled($validated['outcome'] ?? null) ? $validated['outcome'] : null,
        ];
    }
}
