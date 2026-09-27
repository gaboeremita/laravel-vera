<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TravelRequest extends FormRequest
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
            'regionId' => ['required', 'integer'],
            'passageId' => ['required', 'string', 'max:100'],
            'followerIds' => ['sometimes', 'array', 'max:20'],
            'followerIds.*' => ['integer', 'distinct'],
        ];
    }
}
