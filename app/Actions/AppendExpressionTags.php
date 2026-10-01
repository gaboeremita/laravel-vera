<?php

namespace App\Actions;

use App\Directors\PromptDirector;
use App\Enums\AssistantPortraitType;
use App\Enums\Posture;
use App\Enums\TurnSection;
use App\Models\Assistant;

class AppendExpressionTags
{
    /**
     * Appends the assistant's expressive-signal prompt section — pose tags
     * for 3D avatar assistants (poses are their only expression/action
     * system, so emotion tags never apply), emotion tags for image-mode
     * assistants — and excludes whichever section doesn't apply from
     * $excludedSections, so a stale section baked into the assistant's
     * stored prompt (e.g. from before it was in this mode) never renders
     * alongside it and confuses the model with two competing tag formats.
     * For 3D avatars the tag format stays with the unchanging sections, while
     * the current posture and the poses that fit it go with this turn's state.
     *
     * @param  array<int, string>  $excludedSections
     */
    public function handle(PromptDirector $director, Assistant $assistantModel, array &$excludedSections, Posture $posture = Posture::Standing): void
    {
        if ($assistantModel->portrait_type === AssistantPortraitType::Avatar3D) {
            $excludedSections[] = 'emotion tags';

            $poses = $assistantModel->promptPoseNames($posture);

            if ($poses['regular'] !== [] || $poses['restricted'] !== []) {
                $director->append('pose tags', [
                    'format' => 'Use [pose: <exact pose name>] to select a pose. Use only a name from the available poses list. Control tags may appear in any order and are removed before the reply is shown.',
                ]);
                $director->addToTurn(TurnSection::CurrentState, 'pose tags', [
                    'posture' => "You are {$posture->value}. The available poses are the ones that fit how you are right now, and a pose keeps you {$posture->value}. Anything else you do goes in your narration.",
                    'available poses' => $poses,
                ]);
            }

            return;
        }

        $excludedSections[] = 'pose tags';

        $emotions = $assistantModel->promptEmotionNames();
        $director->append('emotion tags', [
            'format' => 'Use [emotion: <exact emotion name>] to select an emotion. Use only a name from the available emotions list. Control tags may appear in any order and are removed before the reply is shown.',
            'available emotions' => $emotions,
        ]);
    }
}
