<?php

namespace App\Actions;

use App\Directors\PromptDirector;
use App\Enums\AssistantPortraitType;
use App\Enums\Posture;
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
                    'format' => "You are {$posture->value}. Use [pose: <exact pose name>] to select a pose. Use only a name from the available poses list: these are the poses that fit how you are right now, and a pose keeps you {$posture->value}. Anything else you do goes in your narration. Control tags may appear in any order and are removed before the reply is shown.",
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
