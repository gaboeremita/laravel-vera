# Data Model: Quests for Worlds

## New tables

### `campaigns`

A named group of quests with its own rubric (research R1, R11).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_id` | FK `worlds`, cascade | |
| `key` | string(80) | unique per world; lowercase letters, digits and dashes |
| `title` | string(120) | |
| `definition` | json | `{ description, rubric: { guidance, dimensions: [{ name, description }], tiers: string[] } }` |
| timestamps | | |

### `quests`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_id` | FK `worlds`, cascade | |
| `campaign_id` | FK `campaigns`, nullable, null on delete | at most one campaign per quest |
| `key` | string(80) | unique per world; what `requires` names |
| `title` | string(120) | |
| `definition` | json | the shape in research R2 |
| timestamps | | |

### `world_session_quests`

One run of a quest in a session (R4).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_session_id` | FK `world_sessions`, cascade | |
| `quest_id` | FK `quests`, cascade | |
| `run` | unsigned int | 1 for the first run |
| `status` | enum `QuestStatus` | |
| `state` | json | `{ finishedBeats: string[], flags: { [name]: { by: ?string, reason: ?string } }, seen: string[], questions: { [id]: true } }` |
| `ending` | json, nullable | `{ tier, title, epilogue, scores: [{ dimension, score, reason }], resultingFlags: string[] }` |
| `ending_status` | enum `EndingStatus`, nullable | set when the run ends |
| `started_at` | timestamp, nullable | |
| `ended_at` | timestamp, nullable | |
| timestamps | | |

Unique (`world_session_id`, `quest_id`, `run`). `seen` holds the keys of latched event leaves (R2), each a stable hash of the watched condition's path and the leaf.

### `quest_events`

Append-only log of a run (R5).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_session_quest_id` | FK `world_session_quests`, cascade | |
| `beat` | string(80), nullable | the beat it concerns |
| `type` | enum `QuestEventType` | |
| `payload` | json | what caused it and its details |
| `by_creator` | bool, default false | |
| `created_at` | timestamp | no `updated_at` |

### `quest_offers`

A giver's pending offer in one conversation (R9).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_session_id` | FK `world_sessions`, cascade | |
| `world_session_quest_id` | FK `world_session_quests`, cascade | the available run offered |
| `conversation_id` | FK `conversations`, cascade | |
| `world_resident_id` | FK `world_residents`, cascade | the giver |
| `status` | enum `QuestOfferStatus` | |
| `answered_at` | timestamp, nullable | |
| timestamps | | |

### `world_session_campaigns`

A campaign's ending in a session, created once the campaign is over (R11).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_session_id` | FK `world_sessions`, cascade | |
| `campaign_id` | FK `campaigns`, cascade | |
| `ending` | json, nullable | same shape as a run's ending |
| `ending_status` | enum `EndingStatus` | |
| timestamps | | |

Unique (`world_session_id`, `campaign_id`).

## Enums

- `App\Enums\QuestStatus`: `Available`, `Active`, `Completed`, `Failed`, `Abandoned`.
- `App\Enums\EndingStatus`: `Pending`, `Written`, `Failed`.
- `App\Enums\QuestEventType`: `Started`, `BeatFinished`, `BeatUndone`, `FlagSet`, `FlagCleared`, `QuestionSignalled`, `QuestionJudged`, `Offered`, `OfferDeclined`, `OfferWithdrawn`, `Completed`, `Failed`, `Abandoned`, `Reset`, `DefinitionEdited`, `EndingWritten`, `EndingFailed`.
- `App\Enums\QuestOfferStatus`: `Pending`, `Accepted`, `Declined`, `Withdrawn`.

## Validation

- `key` matches `^[a-z0-9]+(-[a-z0-9]+)*$`, at most 80 characters, unique per world; `title` required, at most 120.
- A definition passes `ValidateQuestDefinition` (research R14): shape, references, no beat cycle, no quest cycle.
- Beat, question, dimension and tier names are unique within their list; beat and question ids use the same pattern as `key`.
- `campaign_id` is a campaign of the same world.
- A campaign's `definition` has a description and a rubric with at least one dimension.

## State transitions

| From | To | By |
|---|---|---|
| (no run) | `Available` | `SyncSessionQuests`, when requirements hold |
| (no run) | `Active` | `SyncSessionQuests`, for `auto` quests on their first run |
| `Available` | `Active` | `start.when` holds, an offer is accepted, or `start_quest` |
| `Active` | `Completed` / `Failed` | `complete` / `fail` holds, or `end_quest` |
| `Active` | `Abandoned` | the player abandons it |
| any | `Available` or `Active` | `reset_quest`, back to how the definition starts it |

Ending a run sets `ending_status = Pending`; the assessment sets `Written` or `Failed`, and running it again goes back through `Pending`.

## Lifecycle

- **Quest deleted**: its runs, their events and offers go.
- **Campaign deleted**: its quests stay, with `campaign_id` null; its session rows go.
- **Session deleted**: its runs, events, offers and campaign rows go.
- **Quest edited**: open runs drop beats that no longer exist (R16).
- **Referenced world data**: can't be deleted while a quest names it (R15).
