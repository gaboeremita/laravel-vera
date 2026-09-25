<?php

namespace App\Http\Requests;

use App\Actions\ResolveUserActivity;
use App\Enums\Posture;
use App\Http\Controllers\Api\ResidentActivityController;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResidentDecisionRequest extends FormRequest
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
            'residentPosture' => ['nullable', Rule::enum(Posture::class)],
            'occupiedSpots' => ['nullable', 'array'],
            'occupiedSpots.*' => ['string', 'max:100'],
            'previous' => ['nullable', 'array'],
            'previous.activityId' => ['required_with:previous', 'integer'],
            'previous.outcome' => ['required_with:previous', Rule::in(ResidentActivityController::OUTCOMES)],
            'previous.reason' => ['nullable', 'string', 'max:500'],
            ...ResolveUserActivity::rules(),
        ];
    }
}
