<?php

namespace App\Http\Requests;

use App\Enums\Posture;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResidentConversationTurnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'positions' => ['nullable', 'array'],
            'positions.user' => ['sometimes', 'array:x,y,z'],
            'positions.user.*' => ['required', 'numeric'],
            'positions.residents' => ['sometimes', 'array'],
            'positions.residents.*' => ['array:x,y,z'],
            'positions.residents.*.*' => ['required', 'numeric'],
            'postures' => ['nullable', 'array'],
            'postures.*' => [Rule::enum(Posture::class)],
        ];
    }
}
