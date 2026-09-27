---
description: "Task list for The Bridge — One World Made of Connected Regions"
---

# Tasks: The Bridge — One World Made of Connected Regions

**Input**: Design documents from `/specs/017-the-bridge-regions/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Tests**: Included. Constitution VI requires factory-backed Pest feature tests for touched behaviour; pure client logic gets a `node --test` unit test. Per CLAUDE.md, tests are written with each story but Pint, ESLint and the test suite run once, in the final phase.

**Organization**: Tasks are grouped by user story. Story numbers follow [spec.md](spec.md): US1 passages and travel, US2 world and region configuration, US3 residents, US4 regional location, music and prompts.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: The user story the task belongs to

---

## Phase 1: Setup

**Purpose**: Create the files the later phases fill in.

- [ ] T001 Create the schema migrations with `php artisan make:migration --no-interaction` in `database/migrations/`: `rename_worlds_to_regions_table`, `create_worlds_table`, `add_world_id_to_regions_table`, `repoint_world_user_to_worlds_table`, `add_region_id_to_world_residents_table`, `add_region_id_to_world_sessions_tables`, `create_passage_links_table`, in that order
- [ ] T002 [P] Create `app/Models/PassageLink.php` and `database/factories/PassageLinkFactory.php` with `php artisan make:model PassageLink --factory --no-interaction`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Worlds contain regions end to end: schema, models, API, AI code retyped to `Region`, and the configuration page split into World and Regions tabs. Every story builds on this.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

### Schema and models

- [ ] T003 Implement `rename_worlds_to_regions_table` (`Schema::rename('worlds', 'regions')`, with `down()`) in `database/migrations/`
- [ ] T004 Implement `create_worlds_table` per [data-model.md](data-model.md) (`name`, `slug`, `description`, `assistant_context_prompt`, `npc_context_prompt`, `spawn_region_id` nullable FK `nullOnDelete`, `spawn_passage_id` nullable string) in `database/migrations/`
- [ ] T005 Implement `add_world_id_to_regions_table` (`world_id` FK to `worlds`, `cascadeOnDelete`) in `database/migrations/`
- [ ] T006 Implement `repoint_world_user_to_worlds_table` (drop the `world_id` foreign key that now targets `regions`, add it against `worlds`) in `database/migrations/`
- [ ] T007 Implement `add_region_id_to_world_residents_table` (`region_id` FK `cascadeOnDelete`; `world_id` foreign key repointed to `worlds`; keep unique `(world_id, assistant_id)`) in `database/migrations/`
- [ ] T008 Implement `add_region_id_to_world_sessions_tables` (`world_sessions.region_id` nullable FK `nullOnDelete`; `world_session_residents.region_id` FK `cascadeOnDelete`) in `database/migrations/`
- [ ] T009 Implement `create_passage_links_table` per [data-model.md](data-model.md) (both region FKs `cascadeOnDelete`, unique `(region_id, passage_id)`) in `database/migrations/`
- [ ] T010 Rename `app/Models/World.php` to `app/Models/Region.php` (class `Region`, add `world()` BelongsTo, `passageLinks()` HasMany; `residents()` by `region_id`) and update every `use App\Models\World` reference under `app/`, `database/` and `tests/` to `Region` where it means the map
- [ ] T011 Create the new `app/Models/World.php` (fillable per [data-model.md](data-model.md); `regions()`, `residents()`, `users()` via `world_user` using `WorldUser`, `worldUsers()`, `cardImage()`, `portraitImage()`, `spawnRegion()`, `contextPromptFor(AssistantKind)`, `spawnPassage(): ?array`)
- [ ] T012 [P] Add `region()` BelongsTo to `app/Models/WorldResident.php`, `app/Models/WorldSession.php` and `app/Models/WorldSessionResident.php`, and `region_id` to their fillable
- [ ] T013 [P] Fill `app/Models/PassageLink.php` (fillable, `region()`, `targetRegion()`)
- [ ] T014 Rename `database/factories/WorldFactory.php` to `database/factories/RegionFactory.php` (keeps `withLayout()`, gains `world_id` from a new world), create the new `database/factories/WorldFactory.php` with `forUser()`, and update `database/factories/WorldResidentFactory.php` and `database/factories/WorldUserFactory.php` for `region_id` and the new world

### API

- [ ] T015 Create `app/Http/Controllers/Api/RegionController.php` (store/show/update/destroy) from the environment and layout logic of today's `WorldController`, scoped to `{world}`; move request rules into `app/Http/Requests/StoreRegionRequest.php` and `app/Http/Requests/UpdateRegionRequest.php`
- [ ] T016 Create `app/Http/Resources/RegionResource.php` from today's `app/Http/Resources/WorldResource.php` shape
- [ ] T017 Rewrite `app/Http/Controllers/Api/WorldController.php`, `app/Http/Requests/StoreWorldRequest.php`, `app/Http/Requests/UpdateWorldRequest.php` and `app/Http/Resources/WorldResource.php` for the container per [contracts/api.md](contracts/api.md) (no environment; `regions[]` and `residents[]` with `regionId` on show)
- [ ] T018 Split image handling: keep world card/portrait routes in `app/Http/Controllers/Api/WorldImageController.php` and add `app/Http/Controllers/Api/RegionImageController.php`, sharing the store/destroy logic between them
- [ ] T019 Rename `app/Http/Controllers/Api/WorldTrackController.php` to `app/Http/Controllers/Api/RegionTrackController.php`, bound to `{world}/regions/{region}`
- [ ] T020 Nest `app/Http/Controllers/Api/WorldResidentController.php` under `{world}/regions/{region}` and set `region_id` on upsert
- [ ] T021 Update `routes/api.php` with the region, region image, region track and nested resident routes from [contracts/api.md](contracts/api.md), using scoped bindings so a region outside the world returns 404; regenerate `resources/js/ziggy.js` with `php artisan ziggy:generate resources/js/ziggy.js`
- [ ] T022 Authorize region routes through the world in `app/Policies/WorldPolicy.php` usage (controllers call `Gate::authorize(..., $world)`)

### AI code on regions

- [ ] T023 Create `app/Actions/ResolveResidentRegion.php` returning a resident's current region in a session (state row `region_id`, else the resident's `region_id`)
- [ ] T024 Retype layout-reading code from `World` to `Region` in `app/Actions/BuildResidentWorldPrompt.php`, `app/Actions/AppendWorldConversationContext.php`, `app/Actions/ResolveWorldState.php`, `app/Actions/ResolveUserActivity.php`, `app/Actions/ResolveSpotStacking.php`, `app/Actions/ApplyResidentZoneAccess.php`, `app/Actions/GenerateResidentConversationTurn.php` and `app/Services/AgentLoop/Tools/World/*.php`
- [ ] T025 Resolve the region in `app/Http/Controllers/Api/ConversationController.php` (`regionId` required with `worldId`, must belong to the world), `ResidentDecisionController.php`, `ResidentActivityController.php`, `ResidentStateController.php`, `ResidentConversationController.php` and `ResidentObservationController.php` via `ResolveResidentRegion`; 422 when the resident is not in the session's current region

### Configuration UI skeleton

- [ ] T026 Move today's `resources/js/components/WorldForm.jsx` sections (details, environment, context, theme) into a new `resources/js/components/RegionForm.jsx`; reduce `WorldForm.jsx` to name, slug, description and the two World Prompts
- [ ] T027 Create `resources/js/components/RegionsTab.jsx`: list of the world's regions, add region (using `RegionForm`), and the selected region's editor with `WorldImagesEditor`, `WorldTrackEditor` and `WorldResidentsEditor`
- [ ] T028 Rework `resources/js/pages/EditWorldPage.jsx` into World and Regions tabs, and `resources/js/pages/CreateWorldPage.jsx` to create a world with `WorldForm` only
- [ ] T029 [P] Pass route names and params as props in `resources/js/components/WorldImagesEditor.jsx`; switch `resources/js/components/WorldTrackEditor.jsx` and `resources/js/components/WorldResidentsEditor.jsx` to the region routes
- [ ] T030 Load the session's region on `resources/js/pages/WorldPage.jsx` (`worlds.show` for world and residents, `worlds.regions.show` for environment, layout and track, in an effect-local async closure) and send `regionId` from `resources/js/components/world/WorldChat.jsx`
- [ ] T031 Update existing feature tests for the renamed factories and routes in `tests/Feature/Api/` (`WorldControllerTest`, `WorldImageControllerTest`, `WorldTrackControllerTest`, `WorldResidentControllerTest`, `WorldResourceTest`, `WorldSessionControllerTest`, `WorldLayoutImportTest`, `WorldConversationContextTest`, `ConversationWorldSessionScopingTest`, `Resident*Test`)
- [ ] T032 [P] Create `tests/Feature/Api/RegionControllerTest.php` covering region CRUD, 404 for a region of another world and for another user's world

**Checkpoint**: A world with regions can be created, edited and entered in its region.

---

## Phase 3: User Story 1 - Link passages and travel between regions (Priority: P1) 🎯 MVP

**Goal**: Passages parsed from environments, linked two-way from the region configuration, and travelled through in play.

**Independent Test**: Configure two regions with one passage each, link them, start a session and walk through in both directions ([quickstart.md](quickstart.md) scenarios 1–3).

### Tests for User Story 1

- [ ] T033 [P] [US1] Extend `tests/Feature/Api/WorldLayoutImportTest.php` with passage markers: parsed fields, default radius, facing, arrival point, zone, and warnings for missing fields, duplicate ids and a bad radius per [contracts/passage-marker.md](contracts/passage-marker.md)
- [ ] T034 [P] [US1] Create `tests/Feature/Api/PassageLinkControllerTest.php`: two-way link, relink removes the previous partner of both passages, link within one region, same passage rejected, other world rejected, unlink removes both rows, region delete removes links
- [ ] T035 [P] [US1] Extend `tests/Feature/Api/WorldSessionControllerTest.php` with travel: moves the session to the target region in front of the linked passage, 422 for an unlinked passage and for a region that is not the session's current one
- [ ] T036 [P] [US1] Extend `tests/Feature/Api/RegionControllerTest.php`: re-uploading an environment without a linked passage removes both link rows and clears the spawn point; keeping the id keeps the link
- [ ] T037 [P] [US1] Create `tests/Unit/PassageTrigger.test.js`: no trigger while inside the radius on arrival, trigger after leaving and re-entering, unlinked passages never trigger

### Implementation for User Story 1

- [ ] T038 [US1] Parse `passage` markers into `layout.passages` in `app/Actions/ParseEnvironmentLayout.php` (reuse the spot `facing` transform and `APPROACH_DISTANCE` style arrival point; zone via `ResolveWorldState::zoneAt`), and add passages to `RegionFactory::withLayout()`
- [ ] T039 [US1] Create `app/Actions/LinkPassages.php` (link and unlink, both directions, one transaction, validates both passages exist and belong to the same world)
- [ ] T040 [US1] Create `app/Actions/ReconcilePassages.php` and call it from `RegionController::update` inside the update transaction; return `removedLinks` in the response
- [ ] T041 [US1] Create `app/Http/Requests/UpdatePassageLinkRequest.php` and `app/Http/Controllers/Api/PassageLinkController.php` (update/destroy), add `links[]` to `app/Http/Resources/RegionResource.php` and `regions[].warnings` to `WorldResource`
- [ ] T042 [US1] Create `app/Actions/TravelThroughPassage.php` and `app/Http/Requests/TravelRequest.php`; add `travel()` to `app/Http/Controllers/Api/WorldSessionController.php`
- [ ] T043 [US1] Add the passage link and travel routes to `routes/api.php` and regenerate `resources/js/ziggy.js`
- [ ] T044 [P] [US1] Create `resources/js/components/PassageSelect.jsx`: dropdown grouped by region name, passages by name, already-linked passages shown with their partner
- [ ] T045 [US1] Create `resources/js/components/RegionPassagesEditor.jsx` (one row per passage, `PassageSelect`, confirmation before replacing an existing link) and mount it in `resources/js/components/RegionsTab.jsx`
- [ ] T046 [P] [US1] Create `resources/js/components/world/passageTrigger.js` (armed after the player has been outside the radius once; ignores unlinked passages)
- [ ] T047 [US1] Create `resources/js/components/world/PassageTracker.jsx` running `passageTrigger` in the frame loop and reporting the passage walked into
- [ ] T048 [US1] Handle travel in `resources/js/pages/WorldPage.jsx`: close an open chat, call `worlds.sessions.travel`, remount the scene keyed by region id at the returned position and facing, toast on failure

**Checkpoint**: Two linked regions can be travelled between in both directions.

---

## Phase 4: User Story 2 - Configure the world and its regions (Priority: P1)

**Goal**: The World tab holds the spawn point; regions show warnings and the spawn marker; sessions require a spawn point; region deletion follows the spec.

**Independent Test**: Create a world with two regions, pick the spawn point, reload and see it kept; start a session and appear in front of it; delete a region and see its residents, links and the spawn point removed ([quickstart.md](quickstart.md) scenarios 1, 7).

### Tests for User Story 2

- [ ] T049 [P] [US2] Extend `tests/Feature/Api/WorldControllerTest.php`: create a world without an environment, set and clear the spawn point, 422 when the passage is not in that region or the region is in another world, `hasSpawn` and region warnings in the response
- [ ] T050 [P] [US2] Extend `tests/Feature/Api/WorldSessionControllerTest.php`: 422 on store without a spawn point, store starts in front of it, resume moves a session with a deleted region in front of the spawn point, resume 422 without a spawn point
- [ ] T051 [P] [US2] Extend `tests/Feature/Api/RegionControllerTest.php`: deleting a region removes its residents, links and session resident states, nulls sessions' region and clears the spawn point

### Implementation for User Story 2

- [ ] T052 [US2] Validate and save `spawnRegionId` / `spawnPassageId` in `app/Http/Requests/UpdateWorldRequest.php` and `app/Http/Controllers/Api/WorldController.php`; expose `hasSpawn`
- [ ] T053 [US2] Require a valid spawn point in `WorldSessionController::store` and start the session in front of it; add `resume()` and its route in `routes/api.php`; regenerate `resources/js/ziggy.js`
- [ ] T054 [US2] Clear the world's spawn point in `RegionController::destroy` when it points at the deleted region
- [ ] T055 [US2] Add the spawn point `PassageSelect` to `resources/js/components/WorldForm.jsx` with a warning while none is chosen
- [ ] T056 [US2] Show the spawn mark and warnings (unlinked passages, no passages) on the region list in `resources/js/components/RegionsTab.jsx`
- [ ] T057 [P] [US2] Show region count and the spawn warning on `resources/js/components/WorldCard.jsx`; disable starting a session without a spawn point in `resources/js/components/WorldSessionList.jsx` and `resources/js/pages/WorldSessionsPage.jsx`
- [ ] T058 [US2] Call `worlds.sessions.resume` from `resources/js/pages/WorldPage.jsx` when the session's `regionId` is null

**Checkpoint**: A world is fully configurable and sessions start at its spawn point.

---

## Phase 5: User Story 3 - Residents belong to one region of the world (Priority: P2)

**Goal**: One region per resident per world, grayed-out rows with the region name, moving a resident from the residents list, followers travelling with the player.

**Independent Test**: [quickstart.md](quickstart.md) scenarios 4–5.

### Tests for User Story 3

- [ ] T059 [P] [US3] Extend `tests/Feature/Api/WorldResidentControllerTest.php`: 409 with the region when upserting a resident of another region of the same world; the same assistant allowed in another world; move sets the new region with default placement; existing sessions keep the resident where they were (including sessions without a state row)
- [ ] T060 [P] [US3] Extend `tests/Feature/Api/WorldSessionControllerTest.php`: travel moves followers' state rows to the target region beside the player; 422 for a follower not in the region; non-followers stay; `residentStates` include `regionId`

### Implementation for User Story 3

- [ ] T061 [US3] Return 409 from `WorldResidentController::upsert` for a resident of another region, and add `move()` per [contracts/api.md](contracts/api.md) (creates state rows holding the previous region and placement for sessions without one, then moves the resident); add the route in `routes/api.php` and regenerate `resources/js/ziggy.js`
- [ ] T062 [US3] Move followers in `app/Actions/TravelThroughPassage.php` and include `regionId` in session `residentStates` in `WorldSessionController::index`
- [ ] T063 [US3] Show residents of other regions as unselectable rows reading "resident of \<region\>" with the option to move them here (with confirmation) in `resources/js/components/WorldResidentsEditor.jsx`
- [ ] T064 [US3] Render and simulate only residents whose current region is the loaded region in `resources/js/pages/WorldPage.jsx` and `resources/js/hooks/useResidentAgency.js`; send the ids of residents following the player with travel

**Checkpoint**: Residents are unique per world and follow the player between regions.

---

## Phase 6: User Story 4 - Regional location, music and layered prompts (Priority: P2)

**Goal**: The location readout names the region, each region plays its own music, and AI context combines World Prompts with region prompts and the region name.

**Independent Test**: [quickstart.md](quickstart.md) scenario 6.

### Tests for User Story 4

- [ ] T065 [P] [US4] Extend `tests/Feature/Api/WorldConversationContextTest.php`: context contains the world's prompt for the resident's kind, the region's prompt and the region name

### Implementation for User Story 4

- [ ] T066 [US4] Combine the world and region prompts and add the region name to the location line in `app/Actions/BuildResidentWorldPrompt.php` and `app/Actions/AppendWorldConversationContext.php`
- [ ] T067 [P] [US4] Show the region name before the zone in `resources/js/components/world/hud/LocationReadout.jsx`
- [ ] T068 [US4] Pass the loaded region's `trackUrl` to `WorldTrackPlayer` in `resources/js/pages/WorldPage.jsx` so the music switches on travel

**Checkpoint**: All stories work together.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [ ] T069 Remove leftover references to world-level environment, layout, track or region images from `resources/js/` and `app/`
- [ ] T070 Run the gates once: `vendor/bin/pint`, `npm run lint`, `php artisan test`, `node --test tests/Unit/PassageTrigger.test.js`; fix everything that surfaces
- [ ] T071 Walk through [quickstart.md](quickstart.md) manual scenarios

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: no dependencies.
- **Foundational (Phase 2)**: depends on Setup; blocks every story. T003–T009 run in order; T010–T014 follow; API (T015–T022) and AI code (T023–T025) follow the models; UI (T026–T030) follows the API.
- **US1 (Phase 3)**: depends on Foundational.
- **US2 (Phase 4)**: depends on Foundational; its spawn point needs passages from US1 (T038).
- **US3 (Phase 5)**: depends on Foundational; followers need travel from US1 (T042, T048).
- **US4 (Phase 6)**: depends on Foundational; music switching needs travel from US1 (T048).
- **Polish (Phase 7)**: after every story.

### Within Each Story

- Tests are written first; they run in Phase 7.
- Actions before controllers, controllers before routes, routes before UI.

### Parallel Opportunities

- T002 alongside T001.
- T012 and T013 alongside each other after T010–T011.
- In each story, all test tasks marked [P].
- US1: T044 and T046 alongside the backend tasks.
- US2: T057 alongside T055–T056.
- US4: T067 alongside T066.

---

## Parallel Example: User Story 1

```bash
Task: "Extend tests/Feature/Api/WorldLayoutImportTest.php with passage markers"
Task: "Create tests/Feature/Api/PassageLinkControllerTest.php"
Task: "Create tests/Unit/PassageTrigger.test.js"
Task: "Create resources/js/components/PassageSelect.jsx"
Task: "Create resources/js/components/world/passageTrigger.js"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 and Phase 2.
2. Phase 3 (US1): passages, links and travel.
3. Stop and validate with quickstart scenarios 1–3.

### Incremental Delivery

1. Foundation → worlds with regions.
2. US1 → travel between regions.
3. US2 → spawn point, warnings and deletion rules.
4. US3 → residents per world and followers.
5. US4 → region location, music and prompts.
6. Polish → gates once, quickstart walkthrough.
