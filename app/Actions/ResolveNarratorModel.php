<?php

namespace App\Actions;

use App\Contracts\LlmProvider;
use App\Exceptions\NarratorUnavailable;
use App\Models\World;
use App\Services\LlmProviders\LlmManager;
use InvalidArgumentException;

/**
 * The model that speaks for the world rather than for a character: the
 * world's narrator model, or the app's default one.
 */
class ResolveNarratorModel
{
    /**
     * @throws NarratorUnavailable when the world has no narrator model and the app has no default one
     */
    public function handle(World $world): LlmProvider
    {
        $llmManager = new LlmManager;
        $model = $world->narratorModel()->with('provider')->first();
        if ($model !== null) {
            return $llmManager->fromModel($model);
        }

        try {
            return $llmManager->fromConfig();
        } catch (InvalidArgumentException) {
            throw new NarratorUnavailable('No narrator model is set for this world. Choose one in the world\'s configuration.');
        }
    }
}
