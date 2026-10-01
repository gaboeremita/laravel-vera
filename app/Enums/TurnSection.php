<?php

namespace App\Enums;

enum TurnSection: string
{
    case CurrentState = 'current_state';
    case RecentActivity = 'recent_activity';
    case RetrievedKnowledge = 'retrieved_knowledge';
    case RelationshipState = 'relationship_state';

    public function heading(): string
    {
        return match ($this) {
            self::CurrentState => 'CURRENT STATE',
            self::RecentActivity => 'RECENT ACTIVITY',
            self::RetrievedKnowledge => 'RETRIEVED KNOWLEDGE',
            self::RelationshipState => 'RELATIONSHIP STATE',
        };
    }
}
