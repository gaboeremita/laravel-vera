<?php

namespace App\Http\Requests;

use App\Actions\Quests\ValidateQuestDefinition;
use App\Models\Campaign;
use App\Models\Quest;
use App\Models\World;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCampaignRequest extends FormRequest
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
        /** @var World $world */
        $world = $this->route('world');
        $campaign = $this->route('campaign');

        return [
            'key' => ['required', 'string', 'max:80', 'regex:'.ValidateQuestDefinition::ID_PATTERN, Rule::unique('campaigns', 'key')->where('world_id', $world->id)->ignore($campaign instanceof Campaign ? $campaign->id : null)],
            'title' => ['required', 'string', 'max:120'],
            'definition' => ['required', 'array'],
            'definition.description' => ['present', 'nullable', 'string'],
            'definition.rubric' => ['required', 'array'],
            'definition.rubric.guidance' => ['nullable', 'string'],
            'definition.rubric.dimensions' => ['required', 'array', 'min:1'],
            'definition.rubric.dimensions.*.name' => ['required', 'string', 'distinct'],
            'definition.rubric.dimensions.*.description' => ['nullable', 'string'],
            'definition.rubric.tiers' => ['present', 'array'],
            'definition.rubric.tiers.*' => ['string', 'distinct'],
            'questIds' => ['present', 'array'],
            'questIds.*' => ['integer', 'distinct', Rule::exists('quests', 'id')->where('world_id', $world->id)],
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
     * A quest belongs to at most one campaign.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('questIds*')) {
                    return;
                }
                $campaign = $this->route('campaign');
                Quest::whereKey($this->input('questIds', []))
                    ->whereNotNull('campaign_id')
                    ->when($campaign instanceof Campaign, fn ($query) => $query->where('campaign_id', '!=', $campaign->id))
                    ->get()
                    ->each(fn (Quest $quest) => $validator->errors()->add('questIds', "{$quest->title} is already in another campaign."));
            },
        ];
    }

    /**
     * @return array{key: string, title: string, definition: array<string, mixed>}
     */
    public function attributesForCampaign(): array
    {
        return $this->safe()->only(['key', 'title', 'definition']);
    }

    /**
     * @return array<int, int>
     */
    public function questIds(): array
    {
        return array_map(intval(...), $this->validated('questIds', []));
    }
}
