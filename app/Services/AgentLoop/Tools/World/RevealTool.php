<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\LearnFact;
use App\Actions\ReviewReveal;
use App\Contracts\AgentTool;
use App\Enums\RevealSource;
use App\Enums\TurnMode;
use App\Models\Conversation;
use App\Models\Fact;
use App\Models\KnownFact;
use App\Models\Region;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Support\Collection;
use RuntimeException;

class RevealTool implements AgentTool
{
    public const NOT_NOW = 'It doesn\'t feel like the right moment yet.';

    /** @var array<int, KnownFact> facts that became known this turn */
    public array $learned = [];

    /** @var array<int, array{status: string, note: string}> the answer given to each fact rejected this turn, by fact id */
    private array $rejected = [];

    /** @var ?Collection<int, Fact> */
    private ?Collection $facts = null;

    public function __construct(
        private readonly WorldSession $session,
        private readonly Conversation $conversation,
        private readonly WorldResident $holder,
        private readonly TurnMode $mode,
        private readonly Region $region,
        private readonly ?string $zone = null,
    ) {}

    public function name(): string
    {
        return 'reveal';
    }

    public function description(): string
    {
        if ($this->mode === TurnMode::Creator) {
            return 'Reveals any secret of this world to the user, as the creator directs. It returns the secret; tell it in your own words.';
        }

        return 'Shares one of your secrets with the user when you decide the moment is right. Give your reason. When the moment fits, it returns what you know, and you tell it in your own words.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'fact' => ['type' => 'string', 'enum' => $this->facts()->map(fn (Fact $fact) => $this->nameOf($fact))->values()->all(), 'description' => 'What the secret is about.'],
                'reason' => ['type' => 'string', 'description' => 'Why you share it now, in a sentence.'],
            ],
            'required' => ['fact', 'reason'],
        ];
    }

    public function handle(array $arguments): array
    {
        $wanted = mb_strtolower(trim((string) ($arguments['fact'] ?? '')));
        $fact = $this->facts()->first(fn (Fact $candidate) => mb_strtolower($this->nameOf($candidate)) === $wanted)
            ?? throw new RuntimeException(sprintf('You hold no secret about "%s".', $arguments['fact'] ?? ''));
        $reason = trim((string) ($arguments['reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('Give your reason for sharing it now.');
        }

        if (isset($this->rejected[$fact->id])) {
            return $this->rejected[$fact->id];
        }

        $alreadyKnown = KnownFact::where('world_session_id', $this->session->id)->where('fact_id', $fact->id)->exists();
        $reviewed = $this->mode === TurnMode::InCharacter && ! $alreadyKnown && $this->session->worldUser->world->review_reveals;
        $verdict = $reviewed
            ? app(ReviewReveal::class)->handle($this->session, $this->conversation, $this->holder, $fact, $reason, $this->region, $this->zone)
            : ['approved' => true, 'verdict' => null];

        $learnFact = app(LearnFact::class);
        $holder = $this->mode === TurnMode::Creator ? $fact->holder : $this->holder;
        $learnFact->recordAttempt($this->session, $fact, $this->source(), [
            'holder' => $holder,
            'holderName' => $holder->assistant->name,
            'reason' => $reason,
            'reviewed' => $reviewed,
            'approved' => $verdict['approved'],
            'verdict' => $verdict['verdict'],
        ]);

        if (! $verdict['approved']) {
            return $this->rejected[$fact->id] = ['status' => 'not_now', 'note' => self::NOT_NOW];
        }

        $known = $this->mode === TurnMode::Creator
            ? $learnFact->handle($this->session, $fact, RevealSource::Creator, 'Creator', $fact->content)
            : $learnFact->handle($this->session, $fact, $this->source(), $this->holder->assistant->name, '');
        if ($known !== null) {
            $this->learned[] = $known;
        }

        return ['status' => 'revealed', 'content' => $fact->content, 'note' => 'Tell them in your own words.'];
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
        return $this->facts ??= $this->mode === TurnMode::Creator
            ? Fact::with('holder.assistant')->whereHas('holder', fn ($query) => $query->where('world_id', $this->holder->world_id))->orderBy('topic')->get()
            : $this->holder->facts()->orderBy('topic')->get();
    }

    private function nameOf(Fact $fact): string
    {
        return $this->mode === TurnMode::Creator ? $fact->label() : $fact->topic;
    }

    private function source(): RevealSource
    {
        return match ($this->mode) {
            TurnMode::Creator => RevealSource::Creator,
            TurnMode::OocTurn => RevealSource::OocTurn,
            default => RevealSource::InCharacter,
        };
    }
}
