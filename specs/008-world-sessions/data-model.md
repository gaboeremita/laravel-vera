# Phase 1 Data Model: World Sessions

## Entity: WorldSession

Represents one continuous thread of a user's activity within a specific
world (FR-001–FR-009, spec Key Entities: World Session).

| Field | Type | Notes |
|---|---|---|
| `id` | bigint, PK | |
| `world_id` | bigint, FK → `worlds.id`, cascade on delete | Required. Scopes the session to exactly one world (FR-009). |
| `title` | string, nullable | Defaults to `'New session'` at creation, matching `Conversation`'s pattern. User-editable (max 100 chars) for future rename support. |
| `created_at` | timestamp | |
| `updated_at` | timestamp | Bumped whenever the session's saved state changes; drives list ordering (FR-002). |

**Relationships**:
- `WorldSession belongsTo World` (`world_id`).
- `World belongsTo User` (existing) — a session's owning user is reached
  through `worldSession->world->user`. No direct `user_id` on `WorldSession`
  (see research.md: Decision on ownership).

**Validation rules**:
- `world_id` MUST reference a world owned by the authenticated user (FR-010)
  — enforced in the controller via route-model binding + an ownership check
  on `World`, the same way `WorldController` already scopes world access, not
  via a model-level rule.
- `title`, when provided on create/update, MUST be a string of at most 100
  characters (mirrors `ConversationController::update`'s validation).

**State transitions**: None beyond create → (optionally renamed) → delete.
No status/lifecycle field is introduced — the spec does not require one.

**Deletion**: Deleting a `WorldSession` MUST make its state permanently
inaccessible (FR-007). Deleting a `World` cascades to delete all of its
sessions (`cascadeOnDelete` on `world_id`), consistent with `World`'s existing
cascade behavior for its `WorldResident` rows.

## Non-goals for this data model

- No `WorldUser` pivot (see research.md).
- No separate "last active" timestamp distinct from `updated_at`.
- No session-state/snapshot columns are added here — how a world visit's
  progress is actually persisted (e.g. world scene/physics state) is assumed
  to already exist or be out of scope per the spec's Assumptions section;
  `WorldSession` here is the addressable "thread" record the sessions page
  lists, selects, and deletes, not a new persistence format for in-world
  state.
