<?php

namespace App\Http\Requests;

use App\Enums\AssistantKind;
use App\Models\AssistantUser;
use App\Models\WorldResident;
use App\Services\LlmProviders\LlmManager;
use Illuminate\Validation\Validator;

class UpdateResidentStartingInventoryRequest extends StartingInventoryRequest
{
    protected function allowsUnlimited(): bool
    {
        return true;
    }

    protected function itemFlag(): ?string
    {
        return 'forSale';
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ($this->input('items') === [] && $this->input('credits') === 0)) {
                    return;
                }

                /** @var WorldResident $resident */
                $resident = $this->route('resident');
                if (! $this->canHandOver($resident)) {
                    $validator->errors()->add('credits', "{$resident->assistant->name}'s model can't call tools, so they could never give or ask for anything. Choose a model with tool calling for them to hold items or credits.");
                }
            },
        ];
    }

    private function canHandOver(WorldResident $resident): bool
    {
        if ($resident->assistant->kind !== AssistantKind::WorldNpc) {
            return true;
        }

        $assistantUser = AssistantUser::where('assistant_id', $resident->assistant_id)->where('user_id', $this->user()->id)->first();

        return $assistantUser !== null && (new LlmManager)->resolveModelForAssistantUser($assistantUser)?->supports_tools === true;
    }
}
