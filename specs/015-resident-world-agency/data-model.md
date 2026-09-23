# Data Model: Resident World Agency

## worlds (changed)

| Column | Type | Notes |
|--------|------|-------|
| `layout` | json, nullable | Parsed markers: `{ floors: [], zones: [], objects: [] }`. Replaced wholesale on every environment upload (FR-034). `null` or empty means no zones (FR-010). |

Layout shapes, all in world space:

- **Floor**: `id`, `name`, `minY`, `maxY`.
- **Zone**: `id`, `name`, `description`, `floorId`, `parentId`, `private`, `outline` (`[[x, z], …]`), `minY`, `maxY`, `entry` (`{x, y, z}`), `activities`.
- **Object**: `id`, `name`, `description`, `position`, `zoneId` (resolved at import, FR-007), `spots`.
- **Spot**: `id`, `position`, `facing` (yaw in radians), `approach` (`{x, y, z}`, 0.6 m along facing), `activities`.
- **Activity**: `id`, `name`, `posture` (nullable: `sitting` | `lying` | `reclining`), `pose` (nullable, played once in that posture).

Validation follows [contracts/environment-markers.md](contracts/environment-markers.md).

## poses (changed)

| Column | Change |
|--------|--------|
| `posture` | New string, default `standing`; one of `standing`, `sitting`, `lying`, `reclining`. |
| unique (`assistant_id`, `name`) | Replaced by unique (`assistant_id`, `name`, `posture`) in a new migration. |

Each posture's resting pose is the pose named `default` with that posture. No new world motion slots are added.

## world_residents (changed)

| Column | Change |
|--------|--------|
| `behavior` | Enum `WorldResidentBehavior` gains `Autonomous` (`autonomous`). Existing values unchanged. |

## world_session_residents (new)

A resident's saved state within one world session (FR-032).

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | |
| `world_session_id` | fk → world_sessions, cascade | |
| `world_resident_id` | fk → world_residents, cascade | Unique together with `world_session_id`. |
| `position` | json | `{x, y, z}` |
| `rotation` | json | `{y}` |
| `spot_id` | string, nullable | Layout spot id she occupies. |
| `activity_id` | string, nullable | Layout activity id she is holding. |
| `posture` | string | `standing`, `sitting`, `lying`, `reclining` or `swimming`; restored on return. |
| `exit_position` | json, nullable | `{x, y, z}`, the floor point she stands up to when she leaves the spot. |
| timestamps | | |

Ownership: reached only through `WorldUser → WorldSession`, and the resident must belong to the session's world (Principle IV).

## resident_activities (new)

The recent-activity history (FR-022) and the record of outcomes (FR-020).

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | |
| `world_session_id` | fk → world_sessions, cascade | |
| `world_resident_id` | fk → world_residents, cascade | |
| `source` | string | `idle` (self-chosen) or `requested` (from conversation or a direct control). |
| `verb` | string | `go_to`, `use`, `zone`, `follow`, `stop`, `stay`, `pose`. |
| `target` | string, nullable | Zone, object or spot id, or pose name. |
| `activity` | string, nullable | Activity id for `use` and `zone`. |
| `reason` | text, nullable | Her stated reason for idle steps. |
| `zone_id` | string, nullable | Zone she was in when it started. |
| `outcome` | string, nullable | `null` while running, then `completed`, `failed` or `interrupted`. |
| `outcome_reason` | text, nullable | |
| `finished_at` | timestamp, nullable | |
| timestamps | | `created_at` is the start time. |

Index: (`world_session_id`, `world_resident_id`, `created_at`) for "last 8 activities" and the 8-second decision floor.

State transitions: created with `outcome = null` → exactly one of `completed`, `failed`, `interrupted`. An outcome is never overwritten once set.

## messages (unchanged)

Idle lines are stored as ordinary `assistant` messages in the resident's session conversation.

## Factories

`WorldSessionResidentFactory` and `ResidentActivityFactory` are new. `WorldFactory` gains a `withLayout()` state that produces a small valid layout (two floors, three zones, one object with a spot) for tests.
