<?php

namespace App\Http\Requests;

use App\Models\Region;
use Illuminate\Validation\Validator;

class UpdateObjectStartingInventoryRequest extends StartingInventoryRequest
{
    protected function allowsUnlimited(): bool
    {
        return true;
    }

    protected function itemFlag(): ?string
    {
        return 'takeable';
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
                if ($region->layoutObject((string) $this->route('object')) === null) {
                    $validator->errors()->add('object', 'There is no object with this id in the region.');
                }
            },
        ];
    }
}
