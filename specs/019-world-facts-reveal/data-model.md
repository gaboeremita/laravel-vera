# Data Model: Facts and Reveal Safeguards

## New tables

### `facts`

A secret held by one resident (research R1).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_resident_id` | FK `world_residents`, cascade | the holder |
| `topic` | string(120) | required, unique per resident; the only part the holder sees before the player knows it |
| `content` | text | required; the secret itself |
| `disclosure` | text | required; when the holder shares it, in plain language |

### `fact_relays`

Residents who can act on a fact once the player knows it.

| Column | Type | Notes |
|---|---|---|
| `fact_id` | FK `facts`, cascade | |
| `world_resident_id` | FK `world_residents`, cascade | a resident of the same world, never the holder |

Primary key (`fact_id`, `world_resident_id`).

### `known_facts`

Facts the player knows, per session.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_session_id` | FK `world_sessions`, cascade | |
| `fact_id` | FK `facts`, cascade | |
| `source` | enum `RevealSource` | how the player learned it |
| `source_name` | string | the holder's, item's or object's name, or "Creator" |
| `summary` | text | what the player was told, written once when learned (research R13) |
| `created_at` | timestamp | |

Unique (`world_session_id`, `fact_id`). `set_fact_known(…, false)` deletes the row.

### `fact_acknowledgements`

Facts residents learned from the player.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_session_id` | FK `world_sessions`, cascade | |
| `fact_id` | FK `facts`, cascade | |
| `world_resident_id` | FK `world_residents`, cascade | |
| `created_at` | timestamp | |

Unique (`world_session_id`, `fact_id`, `world_resident_id`).

### `reveal_attempts`

Every attempt to make a fact known (research R6).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_session_id` | FK `world_sessions`, cascade | |
| `fact_id` | FK `facts`, nullable, null on delete | |
| `world_resident_id` | FK `world_residents`, nullable, null on delete | the holder; null for items, activities and creator reveals of a fact with no speaker |
| `fact_topic` | string(120) | copied at the time |
| `holder_name` | string | copied at the time; the item or object name for those sources |
| `source` | enum `RevealSource` | |
| `reason` | text, nullable | the holder's reason; null for items and activities |
| `reviewed` | bool | whether the review ran |
| `approved` | bool | |
| `verdict` | text, nullable | the review's explanation, or the failure message |
| `created_at` | timestamp | |

## Changed tables

| Table | Column | Type | Notes |
|---|---|---|---|
| `worlds` | `review_reveals` | bool, default true | the review of in-character reveals (FR-006) |
| `conversations` | `creator_mode_at` | timestamp, nullable | set when creator mode is activated in this conversation (R9) |
| `users` | `creator_password` | string, nullable | `hashed` cast, hidden (R8) |
| `credit_transactions` | `by_creator` | bool, default false | set for creator grants and removals (R10) |
| `items` | `reveals_fact_id` | FK `facts`, nullable, null on delete | a fact of the same world (R11) |
| `activity_terms` | `reveals_fact_id` | FK `facts`, nullable, null on delete | a fact of the same world (R11) |

## Enum

`App\Enums\RevealSource`: `InCharacter`, `OocTurn`, `Creator`, `Item`, `Activity`.

## Validation

- `topic`, `content`, `disclosure` required; `topic` ≤ 120 characters and unique per resident.
- Relay residents and `reveals_fact_id` facts must belong to the same world; a relay resident can't be the holder.
- Saving any fact for a resident whose model can't call tools returns 422 (R12).
- The creator password is 8 to 200 characters; an empty value clears it.

## Lifecycle

- **Resident removed**: their facts, relays and everything cascading from those facts go with them.
- **Fact deleted**: known facts, acknowledgements and relays go; log rows and item or activity links keep null.
- **Session deleted**: its known facts, acknowledgements and log go.
- **Fact edited**: topic, content and prose apply to every session at once; what's known stays known.
