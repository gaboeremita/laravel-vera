<?php

namespace App\Actions\Activities\Effects;

use App\Actions\Activities\ActivityUse;
use App\Actions\LearnFact;
use App\Enums\RevealSource;
use App\Models\Fact;
use App\Models\World;
use App\Models\WorldResident;

/**
 * The player learns a fact.
 */
class RevealFact extends Effect
{
    public function __construct(private readonly LearnFact $learnFact) {}

    public function type(): string
    {
        return 'revealFact';
    }

    public function validate(array $config, World $world): array
    {
        $exists = Fact::whereKey((int) ($config['fact'] ?? 0))->whereIn('world_resident_id', WorldResident::where('world_id', $world->id)->select('id'))->exists();

        return $exists ? [] : ['Choose one of the world\'s facts.'];
    }

    public function normalize(array $config): array
    {
        return ['fact' => (int) $config['fact']];
    }

    public function apply(array $config, ActivityUse $use): void
    {
        $fact = Fact::find($config['fact']);
        if ($fact === null) {
            return;
        }

        $known = $this->learnFact->fromTheWorld($use->session, $fact, RevealSource::Activity, $use->objectName(), $use->narration ?? $fact->content);
        if ($known !== null) {
            $use->learnedFacts[] = $known;
        }
    }

    public function describe(array $config, ActivityUse $use): string
    {
        $content = Fact::find($config['fact'])?->content;

        return $content === null ? '' : "The player learns this, and the narration reveals it: {$content}";
    }
}
