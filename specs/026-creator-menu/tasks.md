---
description: "Task list for the Creator Menu"
---

# Tasks: Creator Menu

**Input**: Design documents from `/specs/026-creator-menu/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/api.md](contracts/api.md), [quickstart.md](quickstart.md)

**Tests**: Included. Constitution VI requires factory-backed Pest feature tests for touched behaviour. Per CLAUDE.md, tests are written with each story, but Pint, ESLint and the test suite run once, in the final phase.

**Organization**: Tasks are grouped by user story, numbered as in [spec.md](spec.md): US1 open and unlock, US2 move anywhere, US3 command residents, US4 sentiments, US5 quests, US6 credits and items, US7 world state.

**UI standard (applies to every UI task)**: the one in [feature 018's tasks.md](../018-items-inventory-credits/tasks.md). HUD classes and entrance animations from `resources/css/app.css` (`world-hud-panel`, `world-hud-label`, `world-hud-key`, `world-hud-glow`, `world-pause-backdrop`, `hud-enter-fade`, `hud-enter-rise`), modeled on `hud/InventoryPanel.jsx`, `hud/QuestLogPanel.jsx` and `hud/PassageConfirm.jsx`; theme tokens only; `common/Toggle.jsx` for toggles. Labels use the approved names: "Creator menu", "Summon", "Go to".

**Request standard (applies to every page task that calls the API)**: requests go through `api` from `resources/js/utils/api.js` with `route()`; a failed request toasts its `message` through `addToast`; after a change the menu refetches the overview.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: The user story the task belongs to

---

## Phase 1: Setup

**Purpose**: Create the files the later phases fill in.

- [ ] T001 Create the migration with `php artisan make:migration add_creator_columns_to_world_sessions_table --no-interaction` in `database/migrations/`
- [ ] T002 [P] Create the feature tests with `php artisan make:test --pest Api/CreatorUnlockTest --no-interaction` and the same for `Api/CreatorTravelTest`, `Api/CreatorResidentTest`, `Api/CreatorQuestTest`, `Api/CreatorInventoryTest` and `Api/CreatorWorldStateTest` in `tests/Feature/Api/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The stored state, the guard, the routes and the overview that every story uses.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T003 Implement the migration from T001 in `database/migrations/`: nullable `creator_mode_at` timestamp and nullable `frozen_resident_ids` json on `world_sessions`, with a `down()` dropping both ([data-model.md](data-model.md))
- [ ] T004 In `app/Models/WorldSession.php`, add both columns to the fillable list, cast `creator_mode_at` to `datetime` and `frozen_resident_ids` to `array`, and add `isFrozen(WorldResident $resident): bool`; add an `unlocked()` state setting `creator_mode_at` to now in `database/factories/WorldSessionFactory.php`
- [ ] T005 Create `app/Traits/ResolvesCreatorSession.php` using `ResolvesWorldSession`: `resolveCreatorSession(Request $request, int $world, int $session): WorldSession` aborts 403 with "Unlock the Creator menu first." while `creator_mode_at` is null (research R1)
- [ ] T006 Add a `creator` route group under `/worlds/{world}/sessions/{session}/creator` in `routes/api.php`, beside the other session routes, with every route and name of [contracts/api.md](contracts/api.md); each later task adds its controller as it is created
- [ ] T007 In `WorldSessionController::index` in `app/Http/Controllers/Api/WorldSessionController.php`, add `creatorUnlocked` and `frozenResidentIds` to each session's payload
- [ ] T008 Create `app/Http/Controllers/Api/CreatorOverviewController.php` with `show()`, returning the overview of [contracts/api.md](contracts/api.md):
  - regions ordered by id, with zones (`id`, `name`, `parentId`), passages and objects; for each object, `blocks`, `passable` from its `WorldSessionObject` state, and activities from `Region::objectActivities()` with each response summarised by `ActivityEffects` descriptions
  - the world spawn
  - residents with their region from `ResolveResidentRegion`, the zone holding their saved position (a PHP point-in-outline mirroring `worldLocation.js` `zoneAt`, added as `Region::zoneAt(array $point): ?array`), `frozen`, sentiments from `Sentiment::inSession(...)->scores()`, and their last five `ResidentActivity` records
  - `sentimentNames`
  - quests with their latest run's status, beats and flags
  - facts with `label()` and whether a `KnownFact` exists
  - `Item::availableIn($session)` items
  - the player's credits
- [ ] T009 [P] Write `tests/Feature/Api/CreatorUnlockTest.php` cases for the overview: 403 while locked, 404 for another user's session, and the shape of every section with a factory world of two regions, a resident with saved state, a quest run and a fact

**Checkpoint**: Sessions can be unlocked, the guard refuses locked sessions, and the overview describes the world for the menu.

---

## Phase 3: User Story 1 - Open and unlock the Creator menu (Priority: P1) 🎯 MVP

**Goal**: T opens a password prompt, then the menu with its five tabs, and the world pauses while it is open.

**Independent Test**: [quickstart.md](quickstart.md) scenario 1; spec US1's independent test.

- [ ] T010 [US1] Create `app/Http/Controllers/Api/CreatorUnlockController.php` with `store()` (research R1): resolve the session through `ResolvesWorldSession`, validate `password` as a required string, 422 "Set a creator password in Settings first." with no password, 422 "That password is wrong." on a failed `Hash::check`, otherwise set `creator_mode_at` when null and answer 204
- [ ] T011 [P] [US1] Add unlock cases to `tests/Feature/Api/CreatorUnlockTest.php`: correct password unlocks and is kept on a second unlock, wrong password and no password stay locked with their messages, unlocking leaves `conversations.creator_mode_at` of the session's conversations null, and a conversation in creator mode leaves the session locked
- [ ] T012 [US1] Create `resources/js/hooks/useCreatorMenu.js`: `open`, `toggle()`, `close()`, `unlocked` (seeded from the session's `creatorUnlocked`), `unlock(password)`, the overview fetched in an effect-local closure whenever the menu opens unlocked, and `refresh()`; follow the request standard
- [ ] T013 [US1] Create `resources/js/components/world/hud/CreatorMenu.jsx`: a full-height HUD panel titled "Creator menu" with a password field and error line while locked, and tabs Go to, Residents, Quests, Items, World once unlocked, each rendering its tab component (the tab components arrive in later stories; render nothing for a missing one); close button and the `T` key hint; follow the UI standard
- [ ] T014 [US1] In `resources/js/pages/WorldPage.jsx`: toggle the menu on `KeyT` in the capture-phase key listener, ignored when `isTypingTarget(event.target)` and while a passage confirmation is open; render `CreatorMenu`; add the open menu to the `explorationEnabled` conditions, and to `isPaused` for `useResidentAgency` and the world clock, so the world pauses while it is open

**Checkpoint**: The menu opens, unlocks once per session and pauses the world.

---

## Phase 4: User Story 2 - Move anywhere (Priority: P1)

**Goal**: Jump to any zone, passage or the spawn, with followers; fly and walk faster.

**Independent Test**: [quickstart.md](quickstart.md) scenarios 2 and 3; spec US2's independent test.

- [ ] T015 [P] [US2] Create `resources/js/components/world/zoneLanding.js` exporting `zoneLanding(layout, zoneId, grid): {x, y, z} | null`: the outline's centroid, then the grid node closest to it whose position is inside the zone's outline and between its `minY` and `maxY` (reuse the outline test from `worldLocation.js`, exporting it there as `insideOutline`); null when no node qualifies (research R3)
- [ ] T016 [P] [US2] Write `tests/Unit/ZoneLanding.test.js` with a stub grid: a convex zone lands nearest the centroid, an L-shaped zone whose centroid is outside lands inside it, a zone on an upper floor ignores nodes outside its height range, and a zone with no walkable node returns null; add it as `test:zone-landing` in `package.json` beside the other `node --test` scripts
- [ ] T017 [US2] Create `app/Actions/Creator/TravelAsCreator.php` mirroring `TravelThroughPassage`: `handle(WorldSession $session, Region $region, ?array $zone, ?array $passage, Collection $followers): array` sets the session's region and position (zone centroid at `minY`, or the passage arrival and facing), places followers beside the point with the spacing of `TravelThroughPassage::besideArrival`, dispatches `PlayerEnteredRegion` when the region changed, and runs `AnnounceZonesEntered` from the old position, or from null when the region changed; returns the shape of [contracts/api.md](contracts/api.md) with `landInZone`
- [ ] T018 [US2] Create `app/Http/Controllers/Api/CreatorTravelController.php` with `store()`: validate `regionId` with exactly one of `zoneId`, `passageId`, or `spawn`; check the ids against the region's layout (404 otherwise); check followers are in the session's current region as passage travel does; call `TravelAsCreator`
- [ ] T019 [P] [US2] Write `tests/Feature/Api/CreatorTravelTest.php`: zone in another region moves the session and its followers and dispatches `PlayerEnteredRegion`; a beat with `enterZone` for that zone finishes; passage and spawn arrive at their arrival points; unknown zone and passage 404; a follower in another region 422; a locked session 403
- [ ] T020 [P] [US2] Add flying to `resources/js/components/world/playerMotion.js`: `FAST_WALK_MULTIPLIER = 4`, `movementSpeed(mode, fast = false)`, and `flyingStep(position, direction, rising, descending, step)` moving freely in three dimensions; add cases to `tests/Unit/PlayerMotion.test.js`
- [ ] T021 [US2] In `resources/js/components/world/FirstPersonController.jsx`, add `setFlying(on)` and `setFastWalk(on)` to `playerCommands`: while flying, skip gravity, `collisionWorld.move` and blockers and use `flyingStep` with Space held to rise and Q held to descend (Q's crouch toggle and the jump are skipped while flying); turning flying off sets `airborne` so the player falls to the ground; fast walking multiplies the speed; add `teleport(position, facing)` that places the player, clears `airborne` and seat state, and reports the position
- [ ] T022 [US2] In `resources/js/pages/WorldPage.jsx`, add `creatorGoTo(target)`:
  - same region with a zone: `zoneLanding` over `navigation.current`, toast "This zone has nowhere to stand" on null, otherwise `playerCommands.current.teleport()` and save through `worlds.sessions.position.update`
  - same region with a passage, or the spawn in the current region: teleport to its arrival point and facing, then save
  - another region: post to `worlds.sessions.creator.travel` with the following residents' ids, then load the region exactly as `travel` does
  - after the new region's navigation grid is ready, when `landInZone` is set: land with `zoneLanding` and save, or toast "This zone has nowhere to stand"
- [ ] T023 [US2] Create `resources/js/components/world/hud/CreatorGoToTab.jsx`: regions as sections, each with its zones as a tree by `parentId` and its passages, every entry a "Go to" button calling `creatorGoTo`; a "Go to spawn" button; toggles for flying and faster walking calling the T021 commands (state kept in `WorldPage.jsx`, reset on reload); follow the UI standard

**Checkpoint**: The player reaches any place and moves freely.

---

## Phase 5: User Story 3 - Command residents (Priority: P1)

**Goal**: Summon, send, freeze, send home and read residents.

**Independent Test**: [quickstart.md](quickstart.md) scenario 4; spec US3's independent test.

- [ ] T024 [US3] Create `app/Http/Controllers/Api/CreatorResidentController.php` (research R4, R5):
  - `summon()`: validate `position` and `facing`; `updateOrCreate` the `WorldSessionResident` row in the session's region, standing, rotation `{y: facing}`, no spot, activity or exit position
  - `send()`: validate `regionId`, `zoneId` and optional `position`; write the row in that region at the position, or at the zone's centroid at its `minY`
  - `destroyState()`: delete the row
  - `updateFrozen()`: add or remove the id in `frozen_resident_ids`
  - for all of them: look the resident up in the session's world (404 otherwise); when the resident is in an open resident-to-resident conversation, pause it through the same code as `ResidentConversationController::pause`
- [ ] T025 [US3] In `ResidentDecisionController::store` in `app/Http/Controllers/Api/ResidentDecisionController.php`, answer 409 "This resident is frozen." when `isFrozen()`
- [ ] T026 [P] [US3] Write `tests/Feature/Api/CreatorResidentTest.php`: summon from another region writes the row in the player's region, standing, with no spot or activity; send to a zone in another region writes its centroid, also for a resident whose zone access keeps them out of it; home deletes the row and the resident resolves to their own region; freeze and unfreeze update `frozen_resident_ids` and the session payload; a frozen resident's decision gets 409; a resident of another world 404; a locked session 403
- [ ] T027 [US3] In `resources/js/hooks/useResidentAgency.js`, skip a resident whose id is in the session's `frozenResidentIds` the way a busy one is skipped, and stop their running activity when they become frozen
- [ ] T028 [US3] In `resources/js/pages/WorldPage.jsx`, add the resident commands:
  - `creatorSummon(resident)`: the grid's `standingPointNear` a point 1 m to the player's right, posted to `summon`, then the session's `residentStates` updated locally so the resident appears
  - `creatorSend(resident, regionId, zoneId)`: in the current region the `zoneLanding` point goes in the request; otherwise no position
  - `creatorSendHome(resident)`
  - `creatorFreeze(resident, frozen)`
  - each closes the chat when it moves the resident the player is chatting with, and updates `activeSession` the way `persistResidentStates` does
- [ ] T029 [US3] Create `resources/js/components/world/hud/CreatorResidentsTab.jsx`:
  - one row per resident with their region and zone, and buttons Summon, Send to… (a region and zone picker), Send home, and a Freeze toggle
  - an expandable detail with the live thought from the page's `thoughts` map when they are in the player's region, and their recent activities from the overview
  - follow the UI standard

**Checkpoint**: Residents can be placed and held anywhere.

---

## Phase 6: User Story 4 - Change how residents feel (Priority: P2)

**Goal**: Set each sentiment of a resident toward the player.

**Independent Test**: [quickstart.md](quickstart.md) scenario 5; spec US4's independent test.

- [ ] T030 [US4] Add `updateSentiments()` to `app/Http/Controllers/Api/CreatorResidentController.php` (research R7): validate `values` as an object whose keys are the world's `sentimentNames()` and whose values are integers from -10 to 10; call `Sentiment::inSession($session, $resident)->adjust()` with each target minus its current score; return `scores()`
- [ ] T031 [P] [US4] Add cases to `tests/Feature/Api/CreatorResidentTest.php`: setting trust to 5 stores 5; a beat requiring trust ≥ 5 finishes with the change as its cause; 11, -11, 2.5 and an unknown name are refused with 422
- [ ] T032 [US4] Add a sentiments section to the resident detail in `resources/js/components/world/hud/CreatorResidentsTab.jsx`: one slider from -10 to 10 per sentiment name with its value, saved on release, and "This world has no sentiments" when there are none; follow the UI standard

**Checkpoint**: Feelings can be set directly.

---

## Phase 7: User Story 5 - Control quests (Priority: P2)

**Goal**: Start, end, reopen, beat and flag any quest, logged as the creator's.

**Independent Test**: [quickstart.md](quickstart.md) scenario 6; spec US5's independent test.

- [ ] T033 [US5] Create `app/Actions/Creator/StartQuestAsCreator.php`, `EndQuestAsCreator.php`, `ResetQuestAsCreator.php`, `SetQuestBeat.php` and `SetQuestFlag.php`, each with `handle(WorldSession $session, Quest $quest, ...)` holding the body of the matching tool's `handle()` (`StartQuestTool`, `EndQuestTool`, `ResetQuestTool`, `SetBeatTool` with `withDependents`, `SetQuestFlagTool`), the latest-run lookup and the broadcast from `CreatorQuestTool`, and the same `RuntimeException` messages (research R2)
- [ ] T034 [US5] Make the five tools in `app/Services/AgentLoop/Tools/World/` resolve the quest by title as today and call their action, returning the same arrays; `CreatorQuestTool` keeps `questParameter()`, `quests()` and `quest()`, and loses `latestRun()` and `broadcast()`, which now live in the actions
- [ ] T035 [US5] Create `app/Http/Controllers/Api/CreatorQuestController.php` with `start()`, `end()`, `reset()`, `updateBeat()` and `updateFlag()` per [contracts/api.md](contracts/api.md): the quest looked up in the session's world (404 otherwise), validation per [data-model.md](data-model.md), each calling its action and turning a `RuntimeException` into 422
- [ ] T036 [P] [US5] Write `tests/Feature/Api/CreatorQuestTest.php`: start an unstarted quest whose start condition doesn't hold; end as failed writes the ending; reset keeps earlier events; undoing a beat undoes the beats requiring it; set and clear a flag; every event has `by_creator` true; starting an active quest and ending an inactive one answer 422; a quest of another world 404
- [ ] T037 [US5] Create `resources/js/components/world/hud/CreatorQuestsTab.jsx`:
  - one row per quest with its status, and buttons Start, Complete, Fail and Reopen, shown as the status allows
  - an expandable list of beats with a finished checkbox each
  - flags with a remove button, and a field to add a flag
  - follow the UI standard

**Checkpoint**: Any quest can be put in any state.

---

## Phase 8: User Story 6 - Credits and items (Priority: P2)

**Goal**: Change credits, grab items, and move items between any inventories.

**Independent Test**: [quickstart.md](quickstart.md) scenario 7; spec US6's independent test.

- [ ] T038 [US6] Create `app/Http/Controllers/Api/CreatorInventoryController.php` (research R8):
  - a private `holder(array $holder, WorldSession $session): Inventory` resolving player, resident (in the session's world) and object (region of the world, object in its layout) through `ResolveInventory`, 404 otherwise
  - `storeCredits()`: `TransferInventory` from nothing for a positive amount ("granted by the creator"), and to nothing for a negative one, limited to the balance ("removed by the creator")
  - `show()`: credits and every item with `takeable`
  - `grant()`: `itemId` from `Item::availableIn()` and `quantity`, from nothing to the holder ("granted by the creator")
  - `move()`: from one holder to another ("moved by the creator"), turning `InsufficientInventory` into 422
  - every transfer passes `byCreator: true`; the responses carry the player's `summary()` and `Inventory::changesBetween()` as `ActivityUseController` does
- [ ] T039 [P] [US6] Write `tests/Feature/Api/CreatorInventoryTest.php`: add 100 credits; take more than the balance leaves 0; grant 3 of an item made in the session; move from an object in another region to the player creates the object's inventory when missing; give a resident an item from nothing; moving more than held answers 422; every `credit_transactions` and `item_transfers` row has `by_creator` true; a `has` beat finishes after a grant
- [ ] T040 [US6] Create `resources/js/components/world/hud/CreatorItemsTab.jsx`:
  - a credits field with Add and Take buttons
  - an item picker with thumbnails and a quantity, with Grab
  - a holder picker (any resident, or any object of any region) that opens its inventory with take buttons per item, plus give-from-nothing and give-from-mine controls
  - apply returned `inventory` and `changes` with the page's existing inventory update
  - follow the UI standard

**Checkpoint**: Anything can be held by anyone.

---

## Phase 9: User Story 7 - World state (Priority: P3)

**Goal**: Unlock and lock blockers, reveal and forget facts, use activities from anywhere, and see zones.

**Independent Test**: [quickstart.md](quickstart.md) scenario 8; spec US7's independent test.

- [ ] T041 [US7] Create `app/Actions/Creator/SetFactKnown.php` with `handle(WorldSession $session, Fact $fact, bool $known): ?KnownFact` holding `SetFactKnownTool::handle()`'s body; make the tool resolve the fact by label and call it, collecting the learned fact as today
- [ ] T042 [US7] Create `app/Http/Controllers/Api/CreatorFactController.php` with `update()`: the fact looked up among the world's facts (404 otherwise), `known` validated as boolean, calling `SetFactKnown` and returning `learnedFact` as `toPayload()` or null
- [ ] T043 [US7] Create `app/Actions/Creator/UseActivityAsCreator.php` (research R9): build an `ActivityUse` as `UseActivity` does, apply the chosen response's effects except those of type `takeCredits` and `takeItems` in a transaction, skip conditions and the narrator, and return the `allowed`, `use` pair; an activity without responses returns `allowed: true` with no use
- [ ] T044 [US7] Create `app/Http/Controllers/Api/CreatorObjectController.php`:
  - `updatePassable()`: set `state.passable` on the `WorldSessionObject` for the region and object, creating it when missing
  - `storeActivityUse()`: check the activity and the response index against the region's activity terms (404 otherwise), call `UseActivityAsCreator`, dispatch `PlayerUsedActivity`, and answer in `ActivityUseController::store`'s shape with a null `narration`
- [ ] T045 [P] [US7] Write `tests/Feature/Api/CreatorWorldStateTest.php`: passable set and cleared on an object in another region; reveal records `RevealSource::Creator` and a `KnownFact`, forget deletes it; a paid activity's `giveItems` applies while `takeCredits` doesn't, its response condition is ignored, and a `use` beat finishes; an unknown object 404
- [ ] T046 [P] [US7] Create `resources/js/components/world/ZoneOverlay.jsx`: for each zone of the current layout, a `LineLoop` along its outline at `minY + 0.05` in the accent color, and its name as a billboard label at the centroid, styled like `NameTags.jsx`; render it from `resources/js/components/world/WorldScene.jsx` when a new `showZones` prop is true
- [ ] T047 [US7] Create `resources/js/components/world/hud/CreatorWorldTab.jsx`:
  - a "Show zones" toggle (state in `WorldPage.jsx`, passed to `WorldScene` as `showZones`)
  - objects by region with a passable toggle for blocking objects
  - each activity with its responses and a Use button
  - facts with Reveal and Forget
  - apply returned object states with `applyObjectState`, learned facts and inventory changes with the page's existing handlers
  - follow the UI standard

**Checkpoint**: All 23 powers are in the menu.

---

## Phase 10: Polish & Cross-Cutting Concerns

- [ ] T048 Run `vendor/bin/pint --format agent` and fix what it reports
- [ ] T049 Run `npm run lint` and fix every error
- [ ] T050 Run `php artisan test --compact` and the `node --test` scripts in `package.json`, and fix every failure, including failures in code this feature didn't touch
- [ ] T051 Walk through [quickstart.md](quickstart.md) scenarios 1–8 in the browser

---

## Dependencies & Execution Order

- **Setup (Phase 1)** → **Foundational (Phase 2)** → user stories.
- **US1** comes before every other story's UI, since their tabs render inside `CreatorMenu`. Their server tasks only need Phase 2.
- **US2 – US7** are independent of each other. US4 extends the controller and tab from US3, so it follows US3.
- **US5** and **US7** change existing creator tools; run `QuestCreatorModeTest`, `QuestWorldToolsTest` and `FactWorldToolsTest` with their story's tests.
- **Polish** last.

## Parallel Opportunities

- T002 alongside T001.
- Each story's test task [P] alongside its controller task.
- T015/T016 and T020 alongside T017–T019.
- Server work of US5, US6 and US7 can proceed in parallel after Phase 2; their tabs follow US1.

## Implementation Strategy

1. **MVP**: Phases 1–3. The menu unlocks and opens, with the overview loaded.
2. **P1 increment**: US2 and US3, which cover the most frequent creator needs: getting places and placing residents.
3. **P2 increment**: US4, US5 and US6.
4. **P3 increment**: US7, then Polish.
