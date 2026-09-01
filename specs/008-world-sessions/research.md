# Phase 0 Research: World Sessions

No `NEEDS CLARIFICATION` markers remain in the spec or Technical Context, so
this phase documents the design decisions made by following existing codebase
conventions.

## Decision: `World` moves to a `WorldUser` pivot, matching `Assistant`/`AssistantUser`

**Decision**: Introduce a `world_user` pivot table and `WorldUser` model,
mirroring `assistant_user`/`AssistantUser` exactly (`world_id`, `user_id`,
`unique(['world_id', 'user_id'])`, timestamps). `World` drops its direct
`user_id` column and `belongsTo(User)` relation; `User::worlds()` becomes a
`belongsToMany(World::class)->using(WorldUser::class)->withTimestamps()`,
matching `User::assistants()`. `WorldPolicy` checks pivot membership
(`$world->users->contains($user)`) instead of `$world->user_id === $user->id`.

**Rationale**: Explicit direction — `World` must have the exact same
relationship shape as `Assistant` has today, not an approximation of it. This
also makes `WorldSession` able to belong to `WorldUser` the same way
`Conversation` belongs to `AssistantUser`, keeping the two feature families
structurally identical end to end.

**Alternatives considered**: An earlier version of this plan gave
`WorldSession` a direct `world_id` FK, treating `World`'s existing single-user
`user_id` column as sufficient scoping. That was rejected — `World` itself
needed to change to the shared pivot model, not just `WorldSession`.

**Migration approach**: Three new migrations (append-only per Constitution
Principle II — no existing migration file is edited):
1. `create_world_user_table` — creates `world_user` (same shape as
   `assistant_user`).
2. `backfill_world_user_from_worlds_user_id` — data migration: for every row
   in `worlds`, insert a corresponding `world_user` row from its `user_id`.
3. `drop_user_id_from_worlds_table` — drops `worlds.user_id` once backfilled.

## Decision: `WorldSession` belongs to `WorldUser`

**Decision**: `WorldSession` gets a `world_user_id` foreign key (constrained
to `world_user`, cascade on delete), exactly mirroring `Conversation`'s
`assistant_user_id` foreign key to `assistant_user`.

**Rationale**: With `World` now shared via `WorldUser`, a session (like a
conversation) is a per-user thread against a shared resource — the pivot is
the correct owning row, not the resource itself.

## Decision: Controller/route shape mirrors `ConversationController`

**Decision**: `WorldSessionController` implements `index`, `store`, `update`
(rename), `destroy`, nested under `worlds/{world}/sessions` the same way
conversations nest under `assistants/{assistant}/conversations`. Each action
resolves the requesting user's `WorldUser` row for the route-bound `World`
(creating it on first access is out of scope here — worlds are already
provisioned per-user via existing `WorldController::store`/sharing flows) and
scopes all session queries through it.

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

## Decision: `WorldSession` stores the user's last position

**Decision**: `WorldSession` gets a `position` column (JSON, nullable),
holding whatever coordinate shape the world view already uses internally
(e.g. `{x, y, z}`), updated whenever the user's position changes meaningfully
in the world and read back when the session is resumed.

**Rationale**: Direct answer to the 2026-08-31 clarification — "what I care
about most is the position of the user, and the conversations." No existing
column captures this today (confirmed: no persistence found in
`WorldPage.jsx`/`WorldScene.jsx`).

**Alternatives considered**: Separate `x`/`y`/`z` columns were considered for
queryability, but rejected — nothing needs to query or sort by position, so a
single JSON column (matching the existing `settings`/`agent_config` JSON-cast
pattern already used on `World`/`Assistant`) avoids unnecessary schema
rigidity.

## Decision: `Conversation` gains a nullable `world_session_id`, scoping it to one session

**Decision**: Add a nullable `world_session_id` (FK → `world_sessions`,
cascade on delete) to `conversations`, via a new migration — `Conversation`
gains `belongsTo(WorldSession)`. `ConversationController::store` and
`WorldChat.jsx`'s conversation-resolution logic change from "find or create
the resident's one conversation for this assistant" to "find or create the
conversation for this assistant **scoped to the given `world_session_id`**."
Conversations created outside any world (direct assistant chat) keep
`world_session_id` null, unaffected by this change.

**Rationale**: Direct answer to the second clarification — starting a new
session must give fresh conversations with every resident, not continue a
shared history. Today's `WorldChat.jsx` (`resolveConversation`) always reuses
"the first conversation for this assistant" regardless of world or session,
which is the exact behavior that must change.

**Alternatives considered**: A separate `WorldSessionConversation` join table
(session × conversation) was considered, to avoid adding a nullable column to
an existing high-traffic table. Rejected as speculative — a conversation
belongs to exactly one session (or none, for direct assistant chat), which is
a plain one-to-many relationship; a join table would model a many-to-many
relationship nothing requires.

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
