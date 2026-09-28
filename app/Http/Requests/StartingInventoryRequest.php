<?php

namespace App\Http\Requests;

use App\Models\World;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class StartingInventoryRequest extends FormRequest
{
    /**
     * Whether this holder may start with unlimited credits or quantities.
     */
    abstract protected function allowsUnlimited(): bool;

    /**
     * The per-item flag this holder uses, if any: forSale for residents, takeable for objects.
     */
    abstract protected function itemFlag(): ?string;

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
        $amount = $this->allowsUnlimited() ? ['present', 'nullable', 'integer', 'min:0'] : ['required', 'integer', 'min:0'];

        return [
            'credits' => $amount,
            'items' => ['present', 'array'],
            'items.*.itemId' => ['required', 'integer', 'distinct', Rule::exists('items', 'id')->where('world_id', $world->id)],
            'items.*.quantity' => $this->allowsUnlimited() ? ['present', 'nullable', 'integer', 'min:1'] : ['required', 'integer', 'min:1'],
            ...($this->itemFlag() !== null ? ["items.*.{$this->itemFlag()}" => ['boolean']] : []),
        ];
    }

    /**
     * @return array<int, array{itemId: int, quantity: ?int, forSale?: bool, takeable?: bool}>
     */
    public function items(): array
    {
        return collect($this->validated('items'))->map(fn (array $entry) => [
            'itemId' => (int) $entry['itemId'],
            'quantity' => isset($entry['quantity']) ? (int) $entry['quantity'] : null,
            ...($this->itemFlag() !== null ? [$this->itemFlag() => (bool) ($entry[$this->itemFlag()] ?? false)] : []),
        ])->all();
    }

    public function credits(): ?int
    {
        $credits = $this->validated('credits');

        return $credits === null ? null : (int) $credits;
    }
}
