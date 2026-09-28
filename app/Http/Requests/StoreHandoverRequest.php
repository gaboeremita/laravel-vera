<?php

namespace App\Http\Requests;

use App\Models\WorldUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHandoverRequest extends FormRequest
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
        $worldId = (int) $this->route('world');
        WorldUser::where('world_id', $worldId)->where('user_id', $this->user()->id)->firstOrFail();

        return [
            'residentId' => ['required', 'integer', Rule::exists('world_residents', 'id')->where('world_id', $worldId)],
            'credits' => ['required', 'integer', 'min:0'],
            'items' => ['present', 'array'],
            'items.*.itemId' => ['required', 'integer', 'distinct', Rule::exists('items', 'id')->where('world_id', $worldId)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function itemQuantities(): array
    {
        return collect($this->validated('items'))->mapWithKeys(fn (array $entry) => [(int) $entry['itemId'] => (int) $entry['quantity']])->all();
    }
}
