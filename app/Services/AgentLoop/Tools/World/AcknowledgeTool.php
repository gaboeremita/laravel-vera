<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;
use App\Events\Quests\FactAcknowledged;
use App\Models\Fact;
use App\Models\FactAcknowledgement;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Support\Collection;
use RuntimeException;

class AcknowledgeTool implements AgentTool
{
    public function __construct(
        private readonly WorldSession $session,
        private readonly WorldResident $resident,
    ) {}

    public function name(): string
    {
        return 'acknowledge';
    }

    public function description(): string
    {
        return 'Takes in something the user has just told you that you wanted to find out. Call it when they tell you, then act on it in character.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'fact' => ['type' => 'string', 'enum' => $this->knownFacts()->pluck('topic')->values()->all(), 'description' => 'What they told you about.'],
            ],
            'required' => ['fact'],
        ];
    }

    public function handle(array $arguments): array
    {
        $wanted = mb_strtolower(trim((string) ($arguments['fact'] ?? '')));
        $fact = $this->knownFacts()->first(fn (Fact $candidate) => mb_strtolower($candidate->topic) === $wanted)
            ?? throw new RuntimeException('The user hasn\'t learned that, so they couldn\'t have told you.');

        $acknowledgement = FactAcknowledgement::firstOrCreate(['world_session_id' => $this->session->id, 'fact_id' => $fact->id, 'world_resident_id' => $this->resident->id]);
        if ($acknowledgement->wasRecentlyCreated) {
            FactAcknowledged::dispatch($this->session->id);
        }

        return ['status' => 'acknowledged', 'note' => 'You know it now; act on it.'];
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
     * The facts they can act on that the user has actually learned.
     *
     * @return Collection<int, Fact>
     */
    public function knownFacts(): Collection
    {
        return $this->resident->relayedFacts()
            ->whereIn('facts.id', $this->session->knownFacts()->select('fact_id'))
            ->orderBy('topic')
            ->get();
    }
}
