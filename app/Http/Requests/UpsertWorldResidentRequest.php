<?php

namespace App\Http\Requests;

use App\Enums\WorldResidentBehavior;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpsertWorldResidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('world')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'position' => ['required', 'array:x,y,z'],
            'position.x' => ['required', 'numeric'],
            'position.y' => ['required', 'numeric'],
            'position.z' => ['required', 'numeric'],
            'rotation' => ['nullable', 'array:x,y,z'],
            'rotation.x' => ['required_with:rotation', 'numeric'],
            'rotation.y' => ['required_with:rotation', 'numeric'],
            'rotation.z' => ['required_with:rotation', 'numeric'],
            'behavior' => ['required', new Enum(WorldResidentBehavior::class)],
            'behavior_settings' => ['nullable', 'array'],
            'behavior_settings.radius' => ['nullable', 'numeric', 'min:0.1', 'max:3'],
        ];
    }
}
