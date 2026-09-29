<?php

namespace App\Http\Requests;

use App\Models\Fact;
use App\Models\WorldResident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveFactRequest extends FormRequest
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
        /** @var WorldResident $resident */
        $resident = $this->route('resident');
        $fact = $this->route('fact');

        return [
            'topic' => ['required', 'string', 'max:120', Rule::unique('facts', 'topic')->where('world_resident_id', $resident->id)->ignore($fact instanceof Fact ? $fact->id : null)],
            'content' => ['required', 'string'],
            'disclosure' => ['required', 'string'],
            'relayResidentIds' => ['array'],
            'relayResidentIds.*' => ['integer', 'distinct', Rule::exists('world_residents', 'id')->where('world_id', $resident->world_id), Rule::notIn([$resident->id])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['relayResidentIds.*.not_in' => 'The resident who holds a fact can\'t also be one who learns it from the player.'];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var WorldResident $resident */
                $resident = $this->route('resident');
                if (! $resident->canCallToolsFor($this->user())) {
                    $validator->errors()->add('topic', self::toolsUnsupportedReason($resident));
                }
            },
        ];
    }

    public static function toolsUnsupportedReason(WorldResident $resident): string
    {
        return "{$resident->assistant->name}'s model can't call tools, so they could never share what they know. Choose a model with tool calling for them to hold facts.";
    }

    /**
     * @return array{topic: string, content: string, disclosure: string}
     */
    public function attributesForFact(): array
    {
        return $this->safe()->only(['topic', 'content', 'disclosure']);
    }

    /**
     * @return array<int, int>
     */
    public function relayResidentIds(): array
    {
        return array_map(intval(...), $this->validated('relayResidentIds', []));
    }
}
