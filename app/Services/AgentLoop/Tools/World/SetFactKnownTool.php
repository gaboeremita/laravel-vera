<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\LearnFact;
use App\Contracts\AgentTool;
use App\Enums\RevealSource;
use App\Models\Fact;
use App\Models\KnownFact;
use App\Models\WorldSession;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Creator mode's control over what the player knows.
 */
class SetFactKnownTool implements AgentTool
{
    /** @var array<int, KnownFact> facts that became known this turn */
    public array $learned = [];

    /** @var ?Collection<int, Fact> */
    private ?Collection $facts = null;

    public function __construct(private readonly WorldSession $session) {}

    public function name(): string
    {
        return 'set_fact_known';
    }

    public function description(): string
    {
        return 'Makes the user know a secret of this world, or forget it, as the creator directs.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'fact' => ['type' => 'string', 'enum' => $this->facts()->map(fn (Fact $fact) => $fact->label())->values()->all(), 'description' => 'Whose secret, and what it is about.'],
                'known' => ['type' => 'boolean', 'description' => 'True to make the user know it, false to make them forget it.'],
            ],
            'required' => ['fact', 'known'],
        ];
    }

    public function handle(array $arguments): array
    {
        $wanted = mb_strtolower(trim((string) ($arguments['fact'] ?? '')));
        $fact = $this->facts()->first(fn (Fact $candidate) => mb_strtolower($candidate->label()) === $wanted)
            ?? throw new RuntimeException(sprintf('There is no secret called "%s" in this world.', $arguments['fact'] ?? ''));
        $known = (bool) ($arguments['known'] ?? true);
        $learnFact = app(LearnFact::class);

        $learnFact->recordAttempt($this->session, $fact, RevealSource::Creator, [
            'holder' => $fact->holder,
            'holderName' => $fact->holder->assistant->name,
            'reason' => $known ? 'Marked known by the creator' : null,
            'reviewed' => false,
            'approved' => $known,
            'verdict' => $known ? null : 'Marked unknown by the creator',
        ]);

        if (! $known) {
            KnownFact::where('world_session_id', $this->session->id)->where('fact_id', $fact->id)->delete();

            return ['status' => 'forgotten', 'note' => "The user no longer knows about {$fact->topic}."];
        }

        $learned = $learnFact->handle($this->session, $fact, RevealSource::Creator, 'Creator', $fact->content);
        if ($learned !== null) {
            $this->learned[] = $learned;
        }

        return ['status' => 'known', 'note' => "The user now knows about {$fact->topic}."];
    }

    public function timeoutSeconds(): int
    {
        return config('agent.tool_timeout');
    }

    public function retryAttempts(): int
    {
        return 1;
    }

    /**
     * @return Collection<int, Fact>
     */
    private function facts(): Collection
    {
        $worldId = $this->session->worldUser->world_id;

        return $this->facts ??= Fact::with('holder.assistant')->whereHas('holder', fn ($query) => $query->where('world_id', $worldId))->orderBy('topic')->get();
    }
}
