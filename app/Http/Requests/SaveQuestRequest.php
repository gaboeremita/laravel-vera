<?php

namespace App\Http\Requests;

use App\Actions\Quests\ValidateQuestDefinition;
use App\Models\Quest;
use App\Models\World;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveQuestRequest extends FormRequest
{
    /** @var array<int, string> */
    private array $warnings = [];

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
        $quest = $this->route('quest');

        return [
            'key' => ['required', 'string', 'max:80', 'regex:'.ValidateQuestDefinition::ID_PATTERN, Rule::unique('quests', 'key')->where('world_id', $world->id)->ignore($quest instanceof Quest ? $quest->id : null)],
            'title' => ['required', 'string', 'max:120'],
            'campaignId' => ['nullable', 'integer', Rule::exists('campaigns', 'id')->where('world_id', $world->id)],
            'definition' => ['required', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['key.regex' => 'Use lowercase letters, digits and dashes.'];
    }

    /**
     * Problems inside the definition are reported under `definition.<path>`,
     * the path the editor shows them at.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['key', 'definition'])) {
                    return;
                }

                $quest = $this->route('quest');
                $result = app(ValidateQuestDefinition::class)->handle($this->route('world'), $this->input('key'), $this->input('definition'), $quest instanceof Quest ? $quest : null, $this->user());
                foreach ($result['errors'] as $path => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add(rtrim("definition.{$path}", '.'), $message);
                    }
                }
                $this->warnings = $result['warnings'];
            },
        ];
    }

    /**
     * @return array{key: string, title: string, campaign_id: ?int, definition: array<string, mixed>}
     */
    public function attributesForQuest(): array
    {
        return [
            'key' => $this->validated('key'),
            'title' => $this->validated('title'),
            'campaign_id' => $this->validated('campaignId'),
            'definition' => $this->validated('definition'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
