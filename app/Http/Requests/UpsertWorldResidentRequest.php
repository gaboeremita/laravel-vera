<?php

namespace App\Http\Requests;

use App\Enums\Posture;
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
            'posture' => ['nullable', new Enum(Posture::class)],
            'behavior' => ['required', new Enum(WorldResidentBehavior::class)],
            'behaviorSettings' => ['nullable', 'array:radius,homeSpot,route,area,decisionSeconds'],
            'behaviorSettings.radius' => ['nullable', 'numeric', 'min:0.1', 'max:3'],
            'behaviorSettings.homeSpot' => ['nullable', 'array:spotId,activityId'],
            'behaviorSettings.homeSpot.spotId' => ['required_with:behaviorSettings.homeSpot', 'string', 'regex:/^[a-z0-9-]+$/'],
            'behaviorSettings.homeSpot.activityId' => ['required_with:behaviorSettings.homeSpot', 'string', 'regex:/^[a-z0-9-]+$/'],
            'behaviorSettings.route' => ['required_if:behavior,route', 'nullable', 'array', 'list', 'min:2', 'max:20'],
            'behaviorSettings.route.*' => ['required', 'array:target,point,activity,pause'],
            'behaviorSettings.route.*.target' => ['required_without:behaviorSettings.route.*.point', 'nullable', 'string', 'regex:/^[a-z0-9-]+$/'],
            'behaviorSettings.route.*.point' => ['required_without:behaviorSettings.route.*.target', 'nullable', 'array:x,y,z'],
            'behaviorSettings.route.*.point.x' => ['required_with:behaviorSettings.route.*.point', 'numeric'],
            'behaviorSettings.route.*.point.y' => ['required_with:behaviorSettings.route.*.point', 'numeric'],
            'behaviorSettings.route.*.point.z' => ['required_with:behaviorSettings.route.*.point', 'numeric'],
            'behaviorSettings.route.*.activity' => ['nullable', 'string', 'regex:/^[a-z0-9-]+$/'],
            'behaviorSettings.route.*.pause' => ['nullable', 'numeric', 'min:0', 'max:600'],
            'behaviorSettings.area' => ['nullable', 'array', 'list'],
            'behaviorSettings.area.*' => ['required', 'string', 'distinct', 'regex:/^[a-z0-9-]+$/'],
            'behaviorSettings.decisionSeconds' => ['nullable', 'array:min,max'],
            'behaviorSettings.decisionSeconds.min' => ['required_with:behaviorSettings.decisionSeconds', 'numeric', 'min:10', 'max:600'],
            'behaviorSettings.decisionSeconds.max' => ['required_with:behaviorSettings.decisionSeconds', 'numeric', 'gte:behaviorSettings.decisionSeconds.min', 'max:600'],
            'openingMessage' => ['nullable', 'string'],
            'customPrompt' => ['nullable', 'string'],
            'zoneAccess' => ['nullable', 'array:tags,zones'],
            'zoneAccess.tags' => ['sometimes', 'array', 'list'],
            'zoneAccess.tags.*' => ['required', 'string', 'distinct:ignore_case', 'max:64'],
            'zoneAccess.zones' => ['sometimes', 'array', 'list'],
            'zoneAccess.zones.*' => ['required', 'string', 'distinct', 'regex:/^[a-z0-9-]+$/'],
        ];
    }
}
