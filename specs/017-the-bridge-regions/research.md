# Research: The Bridge — One World Made of Connected Regions

## R1. Where regions live in the schema

**Decision**: Rename the `worlds` table to `regions` (model `Region`) and create a new `worlds` table (model `World`) for the container. A region keeps every column a world has today and gains `world_id`.

**Rationale**: Everything the current `World` holds (environment, layout, settings/theme, prompts, images, track) is exactly what a region holds, so the model, controllers, layout code and tests carry over as `Region` with a type rename. The new `World` only holds what is shared across regions.

**Alternatives considered**: Keeping the `worlds` table for regions and naming the container something else (e.g. `universes`) avoids a rename but leaves the code saying "world" for what the product calls a region, in every controller, tool and prompt.

## R2. Residents: one per world, with a home region

**Decision**: Keep `world_residents` and `WorldResident`. Its `world_id` points at the new `worlds` table, and a new `region_id` column holds the home region (`cascadeOnDelete`). The existing unique index on `(world_id, assistant_id)` enforces "at most once per world" in the database.

**Rationale**: The existing unique index already expresses FR-021 once `world_id` means the container. Deleting a region deletes the residents whose home it is, which matches the region deletion rule.

**Alternatives considered**: Uniqueness checked only in the request layer; rejected because a database constraint cannot be bypassed by a second code path.

## R3. Moving a resident to another region ("Bring here")

**Decision**: A dedicated endpoint moves an existing resident's `region_id` to the target region and resets placement fields to the defaults used by `WorldResidentController::upsert` today. Upserting a resident who lives in another region of the same world returns 409.

**Rationale**: Moving is a distinct, confirmed action with a destructive side effect (placement reset), so it gets its own endpoint instead of an implicit flag on upsert.

## R4. Passage markers

**Decision**: `ParseEnvironmentLayout` accepts a sixth marker type, `passage`, with required `id` and `name` and an optional numeric `radius`. Each passage is stored in `layout.passages` with `position`, `facing`, `radius` (default 1.0), `arrival` (1.0 m in front along `facing`, same as spot `approach`) and `zoneId` (via `ResolveWorldState::zoneAt`, same as objects). Duplicate ids and missing fields produce layout warnings.

**Rationale**: Spots already compute `facing` from the marker's +Z axis and an `approach` point in front of it; passages reuse the same transform, so authoring follows the existing convention. Precomputing `arrival` keeps server and client agreeing on where the player lands.

## R5. Passage links

**Decision**: A `passage_links` table stores one row per direction: `(region_id, passage_id) → (target_region_id, target_passage_id)`, unique on `(region_id, passage_id)`. A `LinkPassages` action writes or removes both rows in one transaction, and removes any existing link of either endpoint first. Both region foreign keys cascade on delete.

**Rationale**: The unique index guarantees at most one partner per passage (FR-010) at the database level, lookups during travel are a single indexed read, and region deletion removes both directions automatically. Two-way consistency lives in one action class.

**Alternatives considered**: One row per link with two endpoint pairs; uniqueness of an endpoint across both column pairs cannot be expressed with a plain unique index, and every lookup needs an `OR`.

## R6. Keeping links valid after a new environment upload

**Decision**: After parsing a replacement environment, a `ReconcilePassages` action deletes links (both directions) whose passage id no longer exists in the region's layout, and clears the world's spawn if it points at a removed passage. It runs in the same transaction as the region update.

**Rationale**: FR-011 and FR-012. Ids are the join key, so kept ids keep their links with no extra work.

## R7. Spawn passage and starting a session

**Decision**: `worlds.spawn_region_id` (nullable, `nullOnDelete`) and `worlds.spawn_passage_id` (nullable string). The spawn is valid only when both are set and the passage exists in that region's layout. Creating a session without a valid spawn returns 422. A new session stores `region_id` and `position` from the spawn passage's `arrival`. Deleting the spawn's region clears both columns in `RegionController::destroy`.

**Rationale**: FR-017/FR-018. The frontend reads a `hasSpawn` flag on the world to show the warning and disable starting a session.

## R8. Session state across visits

**Decision**: `world_sessions` gains `region_id` (nullable, `nullOnDelete`); `position` stays. `world_session_residents` gains `region_id` (required, `cascadeOnDelete`). A resident without a state row is in their home region at their configured placement.

**Rationale**: A session already persists player and resident state between visits; recording the region makes that state complete. A session whose region was deleted has `region_id = null` and resumes at the spawn arrival (FR-020). Deleting a region deletes the state rows of residents who were in it, so they reappear at their home placement (resident edge case), with no special-case code.

## R9. Travel

**Decision**: `POST /worlds/{world}/sessions/{session}/travel` with the passage the player walked into and the ids of residents following the player. The server resolves the link, updates the session's `region_id` and `position` to the target passage's `arrival`, moves each follower's state row to the target region at a spot beside the arrival point, and returns the arrival and follower placements. The client unloads the scene and mounts the destination region.

**Rationale**: The server stays the single source of truth for links and session state, so a reload mid-travel resumes in the right region. The client detects passage entry with an armed-on-exit rule: a passage only triggers after the player has been outside its radius once since arriving, which satisfies FR-015 without timers.

**Alternatives considered**: Resolving the link on the client and saving the new region through the position endpoint; rejected because followers and session region must change together, and the client would need every link of every region.

## R10. Residents outside the player's region

**Decision**: Only residents in the loaded region are simulated, as residents of a single world are today. Residents elsewhere keep their stored state until the player's region is theirs again.

**Rationale**: The client simulates what it renders; nothing in the spec requires off-screen life.

## R11. AI context

**Decision**: `BuildResidentWorldPrompt`, `WorldToolbox`, `AppendWorldConversationContext` and the world tools take the resident's current `Region` for layout, zones and objects. The context prompt becomes the world's prompt for the resident's kind followed by the region's, and the location line names the region.

**Rationale**: FR-024/FR-025. The layout-reading code is unchanged apart from receiving a `Region`; only prompt assembly learns about two levels.


## R12. Routes

**Decision**: Keep `/worlds/...` for the container and its sessions, and nest region endpoints under `/worlds/{world}/regions/{region}` (CRUD, images, track, residents, passage links). Session-scoped resident endpoints keep their paths and resolve the resident's region from session state. `resources/js/ziggy.js` is regenerated.

**Rationale**: Session URLs stay stable, and nesting enforces through route scoping that a region belongs to the world in the URL.
