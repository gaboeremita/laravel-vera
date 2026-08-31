# Phase 1 Data Model: World Sessions

## Entity: WorldUser (pivot)

Mirrors `AssistantUser` exactly — the join between a `World` and a `User` who
has access to it.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint, PK | |
| `world_id` | bigint, FK → `worlds.id`, cascade on delete | |
| `user_id` | bigint, FK → `users.id`, cascade on delete | |
| `created_at` / `updated_at` | timestamp | |

Unique on `(world_id, user_id)` — a user has at most one pivot row per world,
matching `assistant_user`'s uniqueness rule.

**Relationships**:
- `WorldUser belongsTo World`.
- `WorldUser belongsTo User`.
- `WorldUser hasMany WorldSession` (`world_user_id`).
- `World belongsToMany User` `using(WorldUser::class)`.
- `User belongsToMany World` `using(WorldUser::class)` (replaces
  `User::worlds(): HasMany`).

## Entity: WorldSession

Represents one continuous thread of a user's activity within a specific
world (FR-001–FR-009, spec Key Entities: World Session).

| Field | Type | Notes |
|---|---|---|
| `id` | bigint, PK | |
| `world_user_id` | bigint, FK → `world_user.id`, cascade on delete | Required. Scopes the session to one user's access to one world (FR-009, FR-010) — mirrors `Conversation.assistant_user_id`. |
| `title` | string, nullable | Defaults to `'New session'` at creation, matching `Conversation`'s pattern. User-editable (max 100 chars) for future rename support. |
| `created_at` | timestamp | |
| `updated_at` | timestamp | Bumped whenever the session's saved state changes; drives list ordering (FR-002). |

**Relationships**:
- `WorldSession belongsTo WorldUser` (`world_user_id`).
- A session's world is reached via `worldSession->worldUser->world`; its
  owning user via `worldSession->worldUser->user` — exactly the same
  indirection `Conversation` uses today for `Assistant`/`User`.

**Validation rules**:
- `world_user_id` MUST correspond to the authenticated user's own `WorldUser`
  row for the route-bound `World` — enforced in the controller (resolve the
  requester's `WorldUser` for `{world}`, 403/404 if none exists), never
  trusted from client input.
- `title`, when provided on create/update, MUST be a string of at most 100
  characters (mirrors `ConversationController::update`'s validation).

**State transitions**: None beyond create → (optionally renamed) → delete.
No status/lifecycle field is introduced — the spec does not require one.

**Deletion**: Deleting a `WorldSession` MUST make its state permanently
inaccessible (FR-007). Deleting a `WorldUser` (e.g. a user's access to a world
is revoked) cascades to delete that user's sessions for that world. Deleting a
`World` cascades to delete its `WorldUser` rows, which in turn cascades to
their `WorldSession` rows.

## Changes to existing entities

- **World**: drops `user_id` column and `belongsTo(User)` relation; gains
  `belongsToMany(User::class)->using(WorldUser::class)` and a `worldUsers():
  HasMany` relation, matching `Assistant`'s shape.
- **User**: `worlds(): HasMany` becomes `worlds(): BelongsToMany` (via
  `WorldUser`), matching `User::assistants()`.
- **WorldPolicy**: `view`/`update`/`delete` check pivot membership
  (`$world->users->contains($user)` or an equivalent `WorldUser::query()`
  check) instead of `$world->user_id === $user->id`.

## Non-goals for this data model

- No session-state/snapshot columns are added here — how a world visit's
  progress is actually persisted (e.g. world scene/physics state) is assumed
  to already exist or be out of scope per the spec's Assumptions section;
  `WorldSession` here is the addressable "thread" record the sessions page
  lists, selects, and deletes, not a new persistence format for in-world
  state.
- No change to `WorldResident` — it already keys off `world_id` +
  `assistant_id` and is unaffected by how `World` is owned by users.
