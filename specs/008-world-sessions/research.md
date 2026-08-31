# Phase 0 Research: World Sessions

No `NEEDS CLARIFICATION` markers remain in the spec or Technical Context, so
this phase documents the design decisions made by following existing codebase
conventions rather than resolving open unknowns.

## Decision: Session ownership via `World`, not a new pivot

**Decision**: `WorldSession` gets a direct `world_id` foreign key. No
`WorldUser` pivot is introduced.

**Rationale**: `Conversation` scopes through `AssistantUser` because an
`Assistant` can be shared/used by multiple users, so conversations need a
per-user-per-assistant pivot. `World` (`app/Models/World.php`) already has a
direct `belongsTo(User)` — each world is owned by exactly one user. A session
belonging to a world therefore already inherits single-user scoping through
the world; adding a pivot would duplicate ownership information the `World`
row already carries.

**Alternatives considered**: Mirroring `AssistantUser` with a `WorldUser`
pivot was considered for consistency's sake, but rejected — `World` has no
multi-user sharing today, so the pivot would carry no information beyond
`world.user_id` and would be pure speculative structure (Constitution
Principle VII).

## Decision: Controller/route shape mirrors `ConversationController`

**Decision**: `WorldSessionController` implements `index`, `store`, `update`
(rename), `destroy`, nested under `worlds/{world}/sessions` the same way
conversations nest under `assistants/{assistant}/conversations`.

**Rationale**: The spec explicitly asks for a page "similar to assistants
page" with select/continue/new/delete — this is the same interaction shape
`ConversationController` and `ConversationsPage.jsx` already implement for
assistants. Reusing the pattern keeps the API and frontend consistent with
the rest of the app and satisfies FR-001–FR-009 without inventing a new
convention.

**Alternatives considered**: A flat `/world-sessions?world_id=` endpoint was
considered but rejected in favor of nesting under `worlds/{world}`, matching
how `worlds.residents.*` and the assistant-scoped routes already nest
sub-resources under their parent in `routes/api.php`.

## Decision: Ordering and "last active" field

**Decision**: List sessions ordered by `updated_at desc`, same as
`ConversationController::index` does today (`selects id, title, updated_at`).
No new "last active" timestamp is introduced — `updated_at` (bumped whenever
the session's state changes) serves this purpose, matching FR-002.

**Rationale**: Reuses an existing, already-tested ordering convention instead
of introducing a parallel timestamp field with overlapping meaning.

**Alternatives considered**: A dedicated `last_active_at` column was
considered for clarity, but rejected as redundant with `updated_at` given no
requirement distinguishes "last modified" from "last active" for a session.

## Decision: Default title and rename support

**Decision**: New sessions default to a title of `'New session'` (mirroring
`Conversation`'s `'New conversation'` default), with `PATCH` support to
rename, matching `ConversationController::update`'s `title` validation
(`string`, `max:100`).

**Rationale**: The spec's Assumptions section flags renaming as a reasonable
but unrequested extension; providing the same `update` endpoint the
Conversations feature already has costs nothing extra structurally (same
controller shape) and keeps the two features symmetric, but the sessions
**page** itself only needs to expose select/new/delete per the spec's actual
scope — rename is included at the API layer for parity but is not a required
UI element for this feature's user stories.
