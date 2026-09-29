<?php

namespace App\Contracts;

/**
 * Something that happened in a session that quest conditions can depend on.
 * The quests listener hears every trigger and checks only the conditions
 * that contain one of its leaves.
 */
interface QuestTrigger
{
    public function sessionId(): int;

    /**
     * The condition keys this trigger can make true, e.g. ['enterRegion'].
     *
     * @return array<int, string>
     */
    public function leaves(): array;

    /**
     * Whether a leaf's value names what happened. Only triggers for things
     * that happen (entering, talking, using) match; a trigger for something
     * held returns false, since held things are read as they are.
     */
    public function matches(string $leaf, mixed $value): bool;
}
