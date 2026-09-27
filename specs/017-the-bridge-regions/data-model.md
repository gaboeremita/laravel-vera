# Data Model: The Bridge — One World Made of Connected Regions

Columns stay snake_case; API keys are camelCase.

## worlds (new table, model `World`)

| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| name | string | |
| slug | string | |
| description | text | |
| assistant_context_prompt | text | World Prompt for companion assistants |
| npc_context_prompt | text | World Prompt for NPCs |
| spawn_region_id | FK regions, nullable | `nullOnDelete` |
| spawn_passage_id | string, nullable | passage `id` inside the spawn region's layout |
| timestamps | | |

Relations: `regions()` HasMany, `residents()` HasMany `WorldResident`, `users()` BelongsToMany via `world_user`, `worldUsers()` HasMany, `cardImage()` / `portraitImage()` MorphOne `Image`, `spawnRegion()` BelongsTo `Region`.

Derived: `spawnPassage(): ?array`, the spawn region's layout passage with `spawn_passage_id`, or null when either is missing.

Validation: `spawnRegionId` must belong to this world and `spawnPassageId` must exist in its layout; both are set or both null.

## regions (renamed from `worlds`, model `Region`)

All existing columns (`name`, `slug`, `description`, `environment_*`, `assistant_context_prompt`, `npc_context_prompt`, `settings` incl. theme, `layout`) plus:

| Column | Type | Notes |
|---|---|---|
| world_id | FK worlds | `cascadeOnDelete` |

Relations: `world()` BelongsTo, `residents()` HasMany `WorldResident` via `region_id`, `cardImage()`, `portraitImage()`, `track()`, `passageLinks()` HasMany.

`layout.passages` (new key, alongside `floors`, `zones`, `objects`):

```text
{ id, name, position {x,y,z}, facing (radians), radius (m, default 1.0), arrival {x,y,z}, zoneId|null }
```

Rules: passage ids unique within a region; `arrival` is 1.0 m in front of `position` along `facing`.

Deleting a region: its residents, passage links from or to it, and session resident states in it are deleted by foreign keys; sessions in it get `region_id = null`; the world's spawn is cleared if it pointed here.

## passage_links (new table, model `PassageLink`)

| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| region_id | FK regions | `cascadeOnDelete` |
| passage_id | string | |
| target_region_id | FK regions | `cascadeOnDelete` |
| target_passage_id | string | |
| timestamps | | |

Unique `(region_id, passage_id)`. Every link is two rows, one per direction, always written and removed together by `LinkPassages`. Both regions belong to the same world. A passage may link to another passage in its own region, never to itself.

## world_residents (existing, model `WorldResident`)

| Column | Change |
|---|---|
| world_id | now references the new `worlds` table |
| region_id | new, FK regions, `cascadeOnDelete`; the region the resident belongs to |

Unique `(world_id, assistant_id)` is kept and now means "once per world". Placement (`position`, `rotation`, `posture`, `behavior`, `behavior_settings`, `zone_access`) refers to that region's layout.

## world_user (existing pivot)

`world_id` now references the new `worlds` table. Unchanged otherwise.

## world_sessions (existing, model `WorldSession`)

| Column | Change |
|---|---|
| region_id | new, FK regions, nullable, `nullOnDelete`; the player's current region |

`position` is the player's position in `region_id`. `region_id = null` means the region was deleted; entering resumes at the spawn arrival.

## world_session_residents (existing, model `WorldSessionResident`)

| Column | Change |
|---|---|
| region_id | new, FK regions, `cascadeOnDelete`; the resident's current region in this session |

No row means the resident is in their region at their configured placement.

## State transitions

- **Start session**: requires a valid spawn → session `region_id` = spawn region, `position` = spawn passage `arrival`.
- **Travel through passage P in region R**: requires a link from `(R, P)` → session moves to the target region at the target passage's `arrival`; each follower's state row moves to the target region next to the arrival point.
- **Enter session**: `region_id` set → load that region at `position`; `region_id` null → spawn arrival (422 while no valid spawn).
- **Moving a resident to another region**: resident `region_id` → target region, placement reset to defaults; their session state rows are deleted so they start at the new placement.
- **Environment re-upload**: links and spawn pointing at passage ids missing from the new layout are removed.
