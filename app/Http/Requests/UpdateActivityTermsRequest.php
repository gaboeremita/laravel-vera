<?php

namespace App\Http\Requests;

use App\Actions\Activities\ValidateActivityResponses;
use App\Models\Region;
use App\Models\World;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateActivityTermsRequest extends FormRequest
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

        return [
            'responses' => ['present', 'array', 'list', 'max:20'],
            'vendorResidentId' => ['nullable', 'integer', Rule::exists('world_residents', 'id')->where('world_id', $world->id)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Region $region */
                $region = $this->route('region');
                if (! array_key_exists((string) $this->route('activity'), $region->objectActivities((string) $this->route('object')))) {
                    $validator->errors()->add('activity', 'That object offers no activity with this id.');
                }
                if (! is_array($this->input('responses'))) {
                    return;
                }
                foreach (app(ValidateActivityResponses::class)->errors($this->input('responses'), $this->route('world')) as $path => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($path, $message);
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForTerms(): array
    {
        return [
            'responses' => app(ValidateActivityResponses::class)->normalize($this->input('responses')),
            'vendor_resident_id' => $this->validated('vendorResidentId'),
        ];
    }
}
