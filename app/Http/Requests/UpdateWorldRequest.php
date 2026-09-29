<?php

namespace App\Http\Requests;

use App\Models\AiProvider;
use App\Models\World;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorldRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:255', Rule::unique('worlds', 'slug')->where(fn ($query) => $query->whereIn('id', $this->user()->worlds()->pluck('worlds.id')))->ignore($this->route('world'))],
            'description' => ['required', 'string'],
            'assistantContextPrompt' => ['required', 'string'],
            'npcContextPrompt' => ['required', 'string'],
            'spawnRegionId' => ['nullable', 'integer', 'required_with:spawnPassageId'],
            'spawnPassageId' => ['nullable', 'string', 'required_with:spawnRegionId'],
            'reviewReveals' => ['sometimes', 'boolean'],
            'narratorModelId' => ['nullable', 'integer', Rule::exists('ai_models', 'id')->whereIn('provider_id', AiProvider::where('user_id', $this->user()->id)->pluck('id')->all())],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->input('spawnRegionId') === null) {
                    return;
                }

                /** @var World $world */
                $world = $this->route('world');
                $region = $world->regions()->find($this->integer('spawnRegionId'));
                if ($region?->passage($this->string('spawnPassageId')->toString()) === null) {
                    $validator->errors()->add('spawnPassageId', 'The spawn point must be a passage of a region of this world.');
                }
            },
        ];
    }
}
