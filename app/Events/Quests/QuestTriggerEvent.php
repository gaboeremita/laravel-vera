<?php

namespace App\Events\Quests;

use App\Contracts\QuestTrigger;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A trigger is heard once the change that caused it is committed, so quests
 * never read state that could still roll back.
 */
abstract class QuestTriggerEvent implements QuestTrigger, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $sessionId) {}

    public function sessionId(): int
    {
        return $this->sessionId;
    }

    public function matches(string $leaf, mixed $value): bool
    {
        return false;
    }

    public function cause(): ?string
    {
        return null;
    }
}
