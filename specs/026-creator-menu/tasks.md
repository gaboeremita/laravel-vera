---
description: "Task list for the Creator Menu"
---

# Tasks: Creator Menu and HUD Theming

**Input**: Design documents from `/specs/026-creator-menu/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/api.md](contracts/api.md), [quickstart.md](quickstart.md)

**Tests**: Included. Constitution VI requires factory-backed Pest feature tests for touched behaviour. Per CLAUDE.md, tests are written with each story, but Pint, ESLint and the test suite run once, in the final phase.

**Organization**: Tasks are grouped by user story, numbered as in [spec.md](spec.md): US1 open and unlock, US2 move anywhere, US3 command residents, US4 sentiments, US5 quests, US6 credits and items, US7 world state, US8 the HUD follows the theme. US8 is P1 and comes first among the stories, since every HUD piece and the Creator menu sit on its classes.

**UI standard (applies to every UI task)**:
- **Look**: the approved mockup as written in [plan.md § Visual Design](plan.md#visual-design), with the HUD's shared classes and theme tokens only, so each theme gives it its HUD look and accent (research R12). List and detail columns follow `hud/QuestLogPanel.jsx`.
- **Motion**: every element plays its rows of the [motion table](plan.md#motion). Interactive elements use the shared controls from `hud/HudControls.jsx`, so hover, pressed, busy, success and failure states are the same everywhere.
- **Feedback**: a control enters its busy state on click, before the request returns (SC-006).
- **Labels**: the uppercase labels listed in plan.md, plus the approved names "Creator menu", "Summon" and "Go to".

**Request standard (applies to every page task that calls the API)**:
- Requests go through `api` from `resources/js/utils/api.js` with `route()`.
- A failed request plays the control's failure state and toasts its `message` through `addToast`.
- A successful one plays the success state and adds a creator log line.
- After a change, the menu refetches the overview.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: The user story the task belongs to

---

## Phase 1: Setup

**Purpose**: Create the files the later phases fill in.

- [X] T001 Create the migration with `php artisan make:migration add_creator_columns_to_world_sessions_table --no-interaction` in `database/migrations/`
- [X] T002 [P] Create the feature tests with `php artisan make:test --pest Api/CreatorUnlockTest --no-interaction` and the same for `Api/CreatorTravelTest`, `Api/CreatorResidentTest`, `Api/CreatorQuestTest`, `Api/CreatorInventoryTest` and `Api/CreatorWorldStateTest` in `tests/Feature/Api/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The stored state, the guard, the routes, the overview, and the creator look and controls that every story uses.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T003 Implement the migration from T001 in `database/migrations/`: nullable `creator_mode_at` timestamp and nullable `frozen_resident_ids` json on `world_sessions`, with a `down()` dropping both ([data-model.md](data-model.md))
- [X] T004 In `app/Models/WorldSession.php`, add both columns to the fillable list, cast `creator_mode_at` to `datetime` and `frozen_resident_ids` to `array`, and add `isFrozen(WorldResident $resident): bool`; add an `unlocked()` state setting `creator_mode_at` to now in `database/factories/WorldSessionFactory.php`
- [X] T005 Create `app/Traits/ResolvesCreatorSession.php` using `ResolvesWorldSession`: `resolveCreatorSession(Request $request, int $world, int $session): WorldSession` aborts 403 with "Unlock the Creator menu first." while `creator_mode_at` is null (research R1)
- [X] T006 Add a `creator` route group under `/worlds/{world}/sessions/{session}/creator` in `routes/api.php`, beside the other session routes, with every route and name of [contracts/api.md](contracts/api.md); each later task adds its controller as it is created
- [X] T007 In `WorldSessionController::index` in `app/Http/Controllers/Api/WorldSessionController.php`, add `creatorUnlocked` and `frozenResidentIds` to each session's payload
- [X] T008 Create `app/Http/Controllers/Api/CreatorOverviewController.php` with `show()`, returning the overview of [contracts/api.md](contracts/api.md):
  - regions ordered by id, with zones (`id`, `name`, `parentId`, `outline`), passages and objects; for each object, `blocks`, `passable` from its `WorldSessionObject` state, and activities from `Region::objectActivities()` with each response summarised by `ActivityEffects` descriptions
  - the world spawn
  - residents with their region from `ResolveResidentRegion`, their saved position, and the zone holding it (`ResolveWorldState::locate`)
  - for each resident: `frozen`, `portraitUrl`, sentiments from `Sentiment::inSession(...)->scores()`, and their last five `ResidentActivity` records
  - `sentimentNames`
  - quests with their latest run's status, beats and flags
  - facts with `label()` and whether a `KnownFact` exists
  - `Item::availableIn($session)` items with card images
  - the player's credits
- [X] T009 [P] Write `tests/Feature/Api/CreatorUnlockTest.php` cases for the overview: 403 while locked, 404 for another user's session, and the shape of every section with a factory world of two regions, a resident with saved state, a quest run and a fact
- [X] T010 [P] Set the HUD theme values of [plan.md § HUD theme table](plan.md#hud-theme-table) in each theme file in `resources/css/themes/`: `terminal.css`, `default.css` (light, and again under `[data-mode="dark"]`), `grimoire.css` and `slate.css` (research R13)
- [X] T011 In `resources/css/app.css`, make `world-hud-panel`, `world-hud-glow`, `world-hud-glow-box`, `world-hud-label`, `world-hud-key`, `world-hud-shimmer`, `world-pause-backdrop` and `world-pause-title` read the HUD theme values in place of their fixed ones:
  - corners from `--hud-radius`, corner marks from `--hud-corner-marks`, scanlines from `--hud-scanlines`
  - every glow's size and mix scaled by `--hud-glow`, and the panel shadow from `--hud-shadow`
  - border from `--hud-border-style`, background from `--hud-panel-opacity`
  - label case, spacing, variant and size from the `--hud-label-*` values
  - add `hud-glow-sm`, `hud-glow-md`, `hud-glow-lg` (accent glows of 8, 10 and 12 px scaled by `--hud-glow`), `hud-text`, `hud-rule` (one gradient line, or two thin lines in Grimoire), `hud-title` (display font with `--hud-title-*`), `hud-primary` (filled or outlined by `--hud-primary-fill`) and `hud-avatar` (`--hud-avatar-radius`)
  - with Terminal's values, every rule computes to what it is today (SC-007)
- [X] T012 [P] In `resources/css/app.css`, beside the `world-hud-*` classes, add the shared HUD component classes `hud-meter`, `hud-chip`, `hud-switch`, `hud-cell` and `hud-caret` (research R12), built only on theme tokens (`--accent`, `--danger`, `--bg-*`, `--fg-*`, `--line-1`, fonts) and the HUD theme values (radius, glow, label case and spacing, caret shape and blink)
- [X] T013 In `resources/css/app.css`, add the shared control state classes `hud-busy` (sweep), `hud-success` (burst ring) and `hud-fail` (shake and danger border), and hover and pressed rules for HUD buttons, on the same tokens and theme values
- [X] T014 In `resources/css/app.css`, add the `hud-*` keyframes for every row of the [motion table](plan.md#motion) that the existing `hud-*` animations don't cover:
  - shake, pop, press flash, chip flip, frost pulse, cell fill, diamond pop, zone pulse ring, dot pulse, arrival flash, overlay stroke draw
  - extend the `prefers-reduced-motion` block so each of them becomes a 150 ms fade and the loops stop
- [X] T015 Create the shared controls and the log:
  - `resources/js/components/world/hud/HudControls.jsx`, exporting `HudButton` (`primary` and `danger` variants; `onClick` may return a promise that drives busy, success and failure), `HudSwitch`, `HudChip` (flips on a value change), `HudMeter` (20 cells centred on 0, hover preview, click to set, staggered fill, counting number) and `HudCounter` (counts to a new value with up and down flashes), each with the hover, pressed, busy, success and failure states of the motion table
  - `resources/js/components/world/hud/CreatorLog.jsx`, where the newest line types in and older lines slide and fade

**Checkpoint**: Sessions can be unlocked, the guard refuses locked sessions, the overview describes the world, and the new shared HUD classes and controls exist.

---

## Phase 3: User Story 8 - The HUD follows the theme (Priority: P1)

**Goal**: Every HUD piece takes the active theme's look.

**Independent Test**: [quickstart.md](quickstart.md) scenario 9; spec US8's independent test.

- [X] T016 [P] [US8] In `resources/js/components/world/hud/` (`ActionLine`, `ActivityProgress`, `BeatNotice`, `CreditsReadout`, `EndingCard`, `FocusPrompt`, `HandoverRequestConfirm`, `InspectCard`, `LearnedFactsPanel`, `LocationReadout`, `MadeItemCard`, `NarrationCard`), replace each `shadow-[0_0_Npx_var(--accent)]` with `hud-glow-sm|md|lg`, each `tracking-[…]` on a label with `hud-text` and a spacing scaled by `--hud-label-tracking`, inline accent rule gradients with `hud-rule`, and any fixed corner radius with the theme's (FR-042)
- [X] T017 [P] [US8] Do the same in `resources/js/components/world/hud/` `PassageConfirm`, `PauseOverlay`, `QuestLogPanel` (including its `borderRadius: 0` rows), `QuestOfferCard`, `QuestTracker`, `ZoneTitleCard` and `InventoryPanel`, and use `hud-title` for their display titles and `hud-primary` for their main action buttons
- [X] T018 [P] [US8] Do the same in `resources/js/components/world/` `WorldChat.jsx`, `GoodsStrip.jsx`, `ConversationObserverPanel.jsx`, `WorldMap.jsx`, `OffscreenIndicator.jsx` and `WorldTrackPlayer.jsx`
- [X] T019 [US8] Add `hud-caret` to every text caret drawn in the HUD (`WorldChat.jsx` input caret, typing lines in `NarrationCard.jsx` and `ActionLine.jsx`, where present), so each theme's caret shape and blink applies

**Checkpoint**: The HUD matches each theme's row of the HUD theme table, and Terminal is unchanged.

---

## Phase 4: User Story 1 - Open and unlock the Creator menu (Priority: P1) 🎯 MVP

**Goal**: T opens the lock screen, then the menu with its rail, tabs and log, and the world pauses while it is open.

**Independent Test**: [quickstart.md](quickstart.md) scenario 1; spec US1's independent test.

- [X] T020 [US1] Create `app/Http/Controllers/Api/CreatorUnlockController.php` with `store()` (research R1): resolve the session through `ResolvesWorldSession`, validate `password` as a required string, 422 "Set a creator password in Settings first." with no password, 422 "That password is wrong." on a failed `Hash::check`, otherwise set `creator_mode_at` when null and answer 204
- [X] T021 [P] [US1] Add unlock cases to `tests/Feature/Api/CreatorUnlockTest.php`: correct password unlocks and is kept on a second unlock, wrong password and no password stay locked with their messages, unlocking leaves `conversations.creator_mode_at` of the session's conversations null, and a conversation in creator mode leaves the session locked
- [X] T022 [US1] Create `resources/js/hooks/useCreatorMenu.js`:
  - state: `open`, `toggle()`, `close()`, `unlocked` (seeded from the session's `creatorUnlocked`), `unlock(password)`, and `refresh()`
  - the overview, fetched in an effect-local closure whenever the menu opens unlocked
  - the creator log entries (latest first, kept for the page's life)
  - `run(label, request)`, which wraps a request with busy, success and failure states, a log line and a refresh
  - follow the request standard
- [X] T023 [US1] Create `resources/js/components/world/hud/CreatorMenu.jsx` per [plan.md § Visual Design](plan.md#visual-design):
  - **Lock panel**: password cells with pop and caret, "AUTHENTICATE" and "CANCEL"; shake and danger flash on a wrong password; cells lighting in sequence and a wipe into the menu on success
  - **Unlocked panel**: header, numbered tab rail with a sliding indicator, the three switches at the rail's foot, the tab content with a cross-fade and staggered rows on tab change, and `CreatorLog` underneath
  - **Keys**: 1–5 switch tabs; T and Escape close, played with the panel's close animation
  - render each tab component as it arrives in later stories, and nothing for a missing one
  - follow the UI standard
- [X] T024 [US1] In `resources/js/pages/WorldPage.jsx`:
  - toggle the menu on `KeyT` in the capture-phase key listener, ignored when `isTypingTarget(event.target)` and while a passage confirmation is open
  - render `CreatorMenu`
  - add the open menu to the `explorationEnabled` conditions, and to `isPaused` for `useResidentAgency` and the world clock, so the world pauses while it is open

**Checkpoint**: The menu opens, unlocks once per session and pauses the world, with its full look and motion.

---

## Phase 5: User Story 2 - Move anywhere (Priority: P1)

**Goal**: Jump to any zone, passage or the spawn, with followers; fly and walk faster.

**Independent Test**: [quickstart.md](quickstart.md) scenarios 2 and 3; spec US2's independent test.

- [X] T025 [P] [US2] Create `resources/js/components/world/zoneLanding.js` exporting `zoneLanding(layout, zoneId, grid): {x, y, z} | null` (research R3):
  - start from the outline's centroid, then pick the grid node closest to it whose position is inside the zone's outline and between its `minY` and `maxY`
  - reuse the outline test from `worldLocation.js`, exporting it there as `insideOutline`
  - return null when no node qualifies
- [X] T026 [P] [US2] Write `tests/Unit/ZoneLanding.test.js` with a stub grid: a convex zone lands nearest the centroid, an L-shaped zone whose centroid is outside lands inside it, a zone on an upper floor ignores nodes outside its height range, and a zone with no walkable node returns null; add it as `test:zone-landing` in `package.json` beside the other `node --test` scripts
- [X] T027 [US2] Create `app/Actions/Creator/TravelAsCreator.php` mirroring `TravelThroughPassage`, with `handle(WorldSession $session, Region $region, ?array $zone, ?array $passage, Collection $followers): array`:
  - set the session's region and position: the zone's centroid at its `minY`, or the passage's arrival and facing
  - place the followers beside that point with the spacing of `TravelThroughPassage::besideArrival`
  - dispatch `PlayerEnteredRegion` when the region changed
  - run `AnnounceZonesEntered` from the old position, or from null when the region changed
  - return the shape of [contracts/api.md](contracts/api.md), with `landInZone`
- [X] T028 [US2] Create `app/Http/Controllers/Api/CreatorTravelController.php` with `store()`: validate `regionId` with exactly one of `zoneId`, `passageId`, or `spawn`; check the ids against the region's layout (404 otherwise); check followers are in the session's current region as passage travel does; call `TravelAsCreator`
- [X] T029 [P] [US2] Write `tests/Feature/Api/CreatorTravelTest.php`: zone in another region moves the session and its followers and dispatches `PlayerEnteredRegion`; a beat with `enterZone` for that zone finishes; passage and spawn arrive at their arrival points; unknown zone and passage 404; a follower in another region 422; a locked session 403
- [X] T030 [P] [US2] Add flying to `resources/js/components/world/playerMotion.js`: `FAST_WALK_MULTIPLIER = 4`, `movementSpeed(mode, fast = false)`, and `flyingStep(position, direction, rising, descending, step)` moving freely in three dimensions; add cases to `tests/Unit/PlayerMotion.test.js`
- [X] T031 [US2] In `resources/js/components/world/FirstPersonController.jsx`, add these to `playerCommands`:
  - `setFlying(on)`: while flying, skip gravity, `collisionWorld.move` and blockers, and use `flyingStep`, with Space held to rise and Q held to descend; Q's crouch toggle and the jump are skipped while flying. Turning flying off sets `airborne`, so the player falls to the ground.
  - `setFastWalk(on)`: multiplies the speed
  - `teleport(position, facing)`: places the player, clears `airborne` and seat state, and reports the position
- [X] T032 [US2] Create `resources/js/components/world/hud/CreatorArrivalFlash.jsx`: a full-view creator-tinted flash (motion table row "World view"), keyed so each arrival replays it; render it from `resources/js/pages/WorldPage.jsx`
- [X] T033 [US2] In `resources/js/pages/WorldPage.jsx`, add `creatorGoTo(target)`. It plays the arrival flash after every successful landing.
  - **Same region, a zone**: land with `zoneLanding` over `navigation.current` and save through `worlds.sessions.position.update`; toast "This zone has nowhere to stand" when it returns null
  - **Same region, a passage or the spawn**: teleport to its arrival point and facing, then save
  - **Another region**: post to `worlds.sessions.creator.travel` with the following residents' ids, then load the region exactly as `travel` does
  - **After that region's navigation grid is ready**, when `landInZone` is set: land with `zoneLanding` and save, or toast "This zone has nowhere to stand"
- [X] T034 [US2] Create `resources/js/components/world/hud/CreatorGoToTab.jsx` per [plan.md § Visual Design](plan.md#visual-design):
  - region chips
  - a zone tree by `parentId` with resident counts, then the passages and WORLD SPAWN
  - an SVG map of the chosen region's zone outlines projected with `worldMapProjection.js`, with the player as a pulsing dot and residents as dots; hovering a zone highlights it in both the map and the tree, and clicking a zone pulses a ring and calls `creatorGoTo`
  - a "GO TO <ZONE>" button for the highlighted zone
  - the rail's FLY and FAST WALK switches calling the T031 commands (state in `WorldPage.jsx`, reset on reload)
  - follow the UI standard

**Checkpoint**: The player reaches any place and moves freely.

---

## Phase 6: User Story 3 - Command residents (Priority: P1)

**Goal**: Summon, send, freeze, send home and read residents.

**Independent Test**: [quickstart.md](quickstart.md) scenario 4; spec US3's independent test.

- [X] T035 [US3] Create `app/Http/Controllers/Api/CreatorResidentController.php` (research R4, R5):
  - `summon()`: validate `position` and `facing`; `updateOrCreate` the `WorldSessionResident` row in the session's region, standing, rotation `{y: facing}`, no spot, activity or exit position
  - `send()`: validate `regionId`, `zoneId` and optional `position`; write the row in that region at the position, or at the zone's centroid at its `minY`
  - `destroyState()`: delete the row
  - `updateFrozen()`: add or remove the id in `frozen_resident_ids`
  - for all of them: look the resident up in the session's world (404 otherwise); when the resident is in an open resident-to-resident conversation, pause it through the same code as `ResidentConversationController::pause`
- [X] T036 [US3] In `ResidentDecisionController::store` in `app/Http/Controllers/Api/ResidentDecisionController.php`, answer 409 "This resident is frozen." when `isFrozen()`
- [X] T037 [P] [US3] Write `tests/Feature/Api/CreatorResidentTest.php`: summon from another region writes the row in the player's region, standing, with no spot or activity; send to a zone in another region writes its centroid, also for a resident whose zone access keeps them out of it; home deletes the row and the resident resolves to their own region; freeze and unfreeze update `frozen_resident_ids` and the session payload; a frozen resident's decision gets 409; a resident of another world 404; a locked session 403
- [X] T038 [US3] In `resources/js/hooks/useResidentAgency.js`, skip a resident whose id is in the session's `frozenResidentIds` the way a busy one is skipped, and stop their running activity when they become frozen
- [X] T039 [P] [US3] Create `resources/js/components/world/ArrivalRing.jsx`: a flat ring at a resident's feet in the accent color that expands and fades over 600 ms (motion table row "Resident"), then unmounts; render it from `resources/js/components/world/WorldScene.jsx` for ids in a new `arrivals` prop
- [X] T040 [US3] In `resources/js/pages/WorldPage.jsx`, add the resident commands:
  - `creatorSummon(resident)`: the grid's `standingPointNear` a point 1 m to the player's right, posted to `summon`, then the session's `residentStates` updated locally so the resident appears with an arrival ring
  - `creatorSend(resident, regionId, zoneId)`: in the current region the `zoneLanding` point goes in the request and the resident gets an arrival ring; otherwise no position
  - `creatorSendHome(resident)`
  - `creatorFreeze(resident, frozen)`
  - each closes the chat when it moves the resident the player is chatting with, and updates `activeSession` the way `persistResidentStates` does
- [X] T041 [US3] Create `resources/js/components/world/hud/CreatorResidentsTab.jsx` per [plan.md § Visual Design](plan.md#visual-design):
  - rows with `Portrait`, name, "REGION · ZONE", and FROZEN or NEAR chips
  - a detail with a large portrait, the glowing name, location and distance
  - a THINKING box typing in the live thought from the page's `thoughts` map with a caret, or "No thought right now" when the resident is elsewhere or idle
  - actions SUMMON (primary), SEND TO… (a region and zone picker), SEND HOME, FREEZE or UNFREEZE, and INVENTORY (opens the Items tab with them as the holder)
  - RECENT activities
  - follow the UI standard

**Checkpoint**: Residents can be placed and held anywhere.

---

## Phase 7: User Story 4 - Change how residents feel (Priority: P2)

**Goal**: Set each sentiment of a resident toward the player.

**Independent Test**: [quickstart.md](quickstart.md) scenario 5; spec US4's independent test.

- [X] T042 [US4] Add `updateSentiments()` to `app/Http/Controllers/Api/CreatorResidentController.php` (research R7): validate `values` as an object whose keys are the world's `sentimentNames()` and whose values are integers from -10 to 10; call `Sentiment::inSession($session, $resident)->adjust()` with each target minus its current score; return `scores()`
- [X] T043 [P] [US4] Add cases to `tests/Feature/Api/CreatorResidentTest.php`: setting trust to 5 stores 5; a beat requiring trust ≥ 5 finishes with the change as its cause; 11, -11, 2.5 and an unknown name are refused with 422
- [X] T044 [US4] Add FEELINGS TOWARD YOU to the resident detail in `resources/js/components/world/hud/CreatorResidentsTab.jsx`: one `HudMeter` per sentiment name, saved on click, positive cells in the accent and negative ones in danger; "This world has no sentiments" when there are none; follow the UI standard

**Checkpoint**: Feelings can be set directly.

---

## Phase 8: User Story 5 - Control quests (Priority: P2)

**Goal**: Start, end, reopen, beat and flag any quest, logged as the creator's.

**Independent Test**: [quickstart.md](quickstart.md) scenario 6; spec US5's independent test.

- [X] T045 [US5] Create five actions in `app/Actions/Creator/`: `StartQuestAsCreator.php`, `EndQuestAsCreator.php`, `ResetQuestAsCreator.php`, `SetQuestBeat.php` and `SetQuestFlag.php` (research R2):
  - each has `handle(WorldSession $session, Quest $quest, ...)` holding the body of the matching tool's `handle()`: `StartQuestTool`, `EndQuestTool`, `ResetQuestTool`, `SetBeatTool` (with `withDependents`) and `SetQuestFlagTool`
  - each takes the latest-run lookup and the broadcast from `CreatorQuestTool`
  - each keeps the same `RuntimeException` messages
- [X] T046 [US5] Make the five tools in `app/Services/AgentLoop/Tools/World/` resolve the quest by title as today and call their action, returning the same arrays; `CreatorQuestTool` keeps its lookups and `broadcast()` for `AssessQuestTool` and `EditQuestTool`, and its `latestRun()` reads the new `WorldSession::latestRunOf()` that the actions also use
- [X] T047 [US5] Create `app/Http/Controllers/Api/CreatorQuestController.php` with `start()`, `end()`, `reset()`, `updateBeat()` and `updateFlag()` per [contracts/api.md](contracts/api.md): the quest looked up in the session's world (404 otherwise), validation per [data-model.md](data-model.md), each calling its action and turning a `RuntimeException` into 422
- [X] T048 [P] [US5] Write `tests/Feature/Api/CreatorQuestTest.php`: start an unstarted quest whose start condition doesn't hold; end as failed writes the ending; reset keeps earlier events; undoing a beat undoes the beats requiring it; set and clear a flag; every event has `by_creator` true; starting an active quest and ending an inactive one answer 422; a quest of another world 404
- [X] T049 [US5] Create `resources/js/components/world/hud/CreatorQuestsTab.jsx` per [plan.md § Visual Design](plan.md#visual-design):
  - rows with status labels
  - a detail with the glowing title and BEATS as the quest log's diamonds, clicked to finish or undo, with the pop and the sequential undo from the motion table
  - FLAGS as removable chips, and "+ ADD FLAG" opening an inline field
  - COMPLETE (primary), FAIL (danger), and REOPEN or START as the status allows
  - follow the UI standard

**Checkpoint**: Any quest can be put in any state.

---

## Phase 9: User Story 6 - Credits and items (Priority: P2)

**Goal**: Change credits, grab items, and move items between any inventories.

**Independent Test**: [quickstart.md](quickstart.md) scenario 7; spec US6's independent test.

- [X] T050 [US6] Create `app/Http/Controllers/Api/CreatorInventoryController.php` (research R8):
  - a private `holder(array $holder, WorldSession $session): Inventory` that resolves player, resident (in the session's world) and object (region of the world, object in its layout) through `ResolveInventory`, 404 otherwise
  - `storeCredits()`: `TransferInventory` from nothing for a positive amount ("granted by the creator"), and to nothing for a negative one, limited to the balance ("removed by the creator")
  - `show()`: credits and every item with `takeable`
  - `grant()`: `itemId` from `Item::availableIn()` and `quantity`, from nothing to the holder ("granted by the creator")
  - `move()`: from one holder to another ("moved by the creator"), turning `InsufficientInventory` into 422
  - every transfer passes `byCreator: true`, and the responses carry the player's `summary()` and `Inventory::changesBetween()` as `ActivityUseController` does
- [X] T051 [P] [US6] Write `tests/Feature/Api/CreatorInventoryTest.php`: add 100 credits; take more than the balance leaves 0; grant 3 of an item made in the session; move from an object in another region to the player creates the object's inventory when missing; give a resident an item from nothing; moving more than held answers 422; every `credit_transactions` and `item_transfers` row has `by_creator` true; a `has` beat finishes after a grant
- [X] T052 [US6] Create `resources/js/components/world/hud/CreatorItemsTab.jsx` per [plan.md § Visual Design](plan.md#visual-design):
  - YOUR CREDITS as a `HudCounter` with −100, +100 and a custom amount
  - a FROM holder picker (any resident, or any object of any region) and its item grid with `ItemThumb`, then ⇄, then the TO grid (the player by default)
  - clicking an item moves one, and the card flies between the grids (FLIP)
  - an item picker with a quantity stepper and GRAB
  - returned `inventory` and `changes` applied through the page's existing inventory update
  - follow the UI standard

**Checkpoint**: Anything can be held by anyone.

---

## Phase 10: User Story 7 - World state (Priority: P3)

**Goal**: Unlock and lock blockers, reveal and forget facts, use activities from anywhere, and see zones.

**Independent Test**: [quickstart.md](quickstart.md) scenario 8; spec US7's independent test.

- [X] T053 [US7] Create `app/Actions/Creator/SetFactKnown.php` with `handle(WorldSession $session, Fact $fact, bool $known): ?KnownFact` holding `SetFactKnownTool::handle()`'s body; make the tool resolve the fact by label and call it, collecting the learned fact as today
- [X] T054 [US7] Create `app/Http/Controllers/Api/CreatorFactController.php` with `update()`: the fact looked up among the world's facts (404 otherwise), `known` validated as boolean, calling `SetFactKnown` and returning `learnedFact` as `toPayload()` or null
- [X] T055 [US7] Create `app/Actions/Creator/UseActivityAsCreator.php` (research R9): build an `ActivityUse` as `UseActivity` does, apply the chosen response's effects except those of type `takeCredits` and `takeItems` in a transaction, skip conditions and the narrator, and return the `allowed`, `use` pair; an activity without responses returns `allowed: true` with no use
- [X] T056 [US7] Create `app/Http/Controllers/Api/CreatorObjectController.php`:
  - `updatePassable()`: set `state.passable` on the `WorldSessionObject` for the region and object, creating it when missing
  - `storeActivityUse()`: check the activity and the response index against the region's activity terms (404 otherwise), call `UseActivityAsCreator`, dispatch `PlayerUsedActivity`, and answer in `ActivityUseController::store`'s shape with a null `narration`
- [X] T057 [P] [US7] Write `tests/Feature/Api/CreatorWorldStateTest.php`: passable set and cleared on an object in another region; reveal records `RevealSource::Creator` and a `KnownFact`, forget deletes it; a paid activity's `giveItems` applies while `takeCredits` doesn't, its response condition is ignored, and a `use` beat finishes; an unknown object 404
- [X] T058 [P] [US7] Create `resources/js/components/world/ZoneOverlay.jsx`:
  - for each zone of the current layout, a `LineLoop` along its outline at `minY + 0.05` in the accent color, drawn along its length when shown
  - each zone's name as a billboard label at its centroid, fading in, styled like `NameTags.jsx`
  - rendered from `resources/js/components/world/WorldScene.jsx` while a new `showZones` prop is true
- [X] T059 [US7] Create `resources/js/components/world/hud/CreatorWorldTab.jsx` per [plan.md § Visual Design](plan.md#visual-design):
  - BLOCKERS by region with LOCKED or OPEN chips that flip when toggled
  - SECRETS with HIDDEN or REVEALED chips that flip when toggled
  - OBJECTS with each activity's responses and USE
  - the rail's SHOW ZONES switch (state in `WorldPage.jsx`, passed to `WorldScene` as `showZones`)
  - returned object states applied with `applyObjectState`, and learned facts and inventory changes with the page's existing handlers
  - follow the UI standard

**Checkpoint**: All 23 powers are in the menu.

---

## Phase 11: Polish & Cross-Cutting Concerns

- [X] T060 Check every row of the [motion table](plan.md#motion) against the built menu and add any that is missing, in `resources/css/app.css` and the components that own the element
- [ ] T061 Run `vendor/bin/pint --format agent` and fix what it reports
- [ ] T062 Run `npm run lint` and fix every error
- [ ] T063 Run `php artisan test --compact` and the `node --test` scripts in `package.json`, and fix every failure, including failures in code this feature didn't touch
- [ ] T064 Walk through [quickstart.md](quickstart.md) scenarios 1–9 in the browser: scenario 9 in all four themes and Default's dark mode, the rest in Terminal and one other theme, and once with reduced motion on

---

## Dependencies & Execution Order

- **Setup (Phase 1)** → **Foundational (Phase 2)** → user stories.
- **US8** comes first among the stories: it needs T010–T011 (theme values and themed HUD classes) and makes every existing HUD piece use them, so the Creator menu is built on themed classes from the start.
- **US1** comes before every other story's UI, since their tabs render inside `CreatorMenu`. Their server tasks only need Phase 2.
- **US2 – US7** are independent of each other. US4 extends the controller and tab from US3, so it follows US3.
- **US5** and **US7** change existing creator tools; run `QuestCreatorModeTest`, `QuestWorldToolsTest` and `FactWorldToolsTest` with their story's tests.
- **Polish** last.

## Parallel Opportunities

- T002 alongside T001.
- T010–T015 (theme values, HUD classes and controls) alongside T003–T009 (server).
- T016–T018 (component sweeps) in parallel with each other.
- Each story's test task [P] alongside its controller task.
- T025/T026 and T030 alongside T027–T029; T039 alongside T035–T038.
- Server work of US5, US6 and US7 can proceed in parallel after Phase 2; their tabs follow US1.

## Implementation Strategy

1. **MVP**: Phases 1–4. The HUD follows each theme, and the menu unlocks and opens with its full look and motion and the overview loaded.
2. **P1 increment**: US2 and US3, which cover the most frequent creator needs: getting places and placing residents.
3. **P2 increment**: US4, US5 and US6.
4. **P3 increment**: US7, then Polish.
