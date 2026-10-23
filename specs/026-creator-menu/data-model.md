# Data Model: Creator Menu

## world_sessions (changed)

| Column | Type | Notes |
|--------|------|-------|
| `creator_mode_at` | timestamp, nullable | Set once by a correct unlock; never cleared. Null means every creator endpoint answers 403. |
| `frozen_resident_ids` | json, nullable | List of `world_residents.id` frozen in this session. Null and `[]` both mean none. Ids of residents no longer in the world are ignored. |

`WorldSession` casts `creator_mode_at` to datetime and `frozen_resident_ids` to array, and gains `isFrozen(WorldResident $resident): bool`.

The session payload of `GET /worlds/{world}/sessions` gains `creatorUnlocked` (bool) and `frozenResidentIds` (int[]).

## worlds (changed)

| Column | Type | Notes |
|--------|------|-------|
| `creator_password` | string, nullable, hashed | The world's creator password; hidden from every payload, which carries `creatorPasswordSet` instead. Saved with the world (`creatorPassword`, `clearCreatorPassword`). |

## users (changed)

`creator_password` is removed. The migration copies each user's hash onto the worlds they belong to before dropping it.

## Existing records the menu writes

| Record | Written by | Creator marking |
|--------|------------|-----------------|
| `world_sessions.region_id`, `position` | Travel (R3) | none; same as walking |
| `world_session_residents` | Summon, send (write), home (delete) | none |
| `sentiments.values` | Sentiments, through `Sentiment::adjust` | none; `ResidentSentimentsChanged` fires |
| `world_session_quests`, `quest_events` | Quest actions (R2) | `quest_events.by_creator = true` |
| `known_facts`, reveal attempts | `SetFactKnown` | `RevealSource::Creator` |
| `inventories`, `inventory_items`, `credit_transactions`, `item_transfers` | `TransferInventory` | `by_creator = true`; reasons "granted by the creator", "removed by the creator", "moved by the creator" |
| `world_session_objects.state.passable` | Passable (R9) and creator activity use | none |

## Validation

- `regionId`, `residentId`, `questId`, `factId` and `itemId` must belong to the session's world (items: `Item::availableIn($session)`).
- `zoneId` must be in the region's `layout.zones`, `passageId` in its `layout.passages`, `objectId` in its `layout.objects`, and `activityId` among that object's activities.
- `position` is `{x, y, z}` numbers; facing is a number in radians.
- Sentiment values are integers from -10 to 10, keyed by the world's sentiment names; unknown names are refused.
- Credit `amount` is a non-zero integer; item `quantity` is a positive integer.
- A beat id must be one of the quest's beats; a flag name matches `^[A-Za-z][A-Za-z0-9]*$`.

## State transitions

- **Unlock**: `creator_mode_at` null → now, on the first correct password. A later unlock leaves it unchanged.
- **Freeze**: id absent ↔ present in `frozen_resident_ids`.
- **Resident location**: any → (player's region, beside player) by summon; any → (zone's region, point in zone) by send; any → no row (session start state) by home.
- **Quest run**: as in feature 020; start creates a new run when the latest has ended, end requires Active, reset requires a run, beat requires Active, flag requires an open run.
