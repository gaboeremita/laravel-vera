<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorldRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('worlds', 'slug')->where('user_id', $this->user()->id)],
            'description' => ['required', 'string'],
            'assistantContextPrompt' => ['required', 'string'],
            'npcContextPrompt' => ['required', 'string'],
            'settings' => ['nullable', 'array'],
            'environment' => ['required', 'file', 'extensions:glb', 'max:51200'],
        ];
    }
}
