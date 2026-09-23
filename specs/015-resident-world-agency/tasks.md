---

description: "Task list for Resident World Agency"
---

# Tasks: Resident World Agency

**Input**: Design documents from `/specs/015-resident-world-agency/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Tests**: Included. Constitution Principle VI requires Pest feature tests, backed by factories, for every backend behavior. Client-only logic (navigation, map projection, pose resolution, conversation range) gets `node --test` unit tests alongside `tests/Unit/WorldCollision.test.js`. Rendering, animation and audio are verified manually with [quickstart.md](quickstart.md).

**Verification cadence**: Per CLAUDE.md, Pint, ESLint and the full test suite run once, right before each pull request (T085). Individual tests are written with each story, then run as part of that gate.

**Organization**: One phase per user story, in spec priority order. Each story ships as its own pull request (see plan.md Delivery Slices).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on unfinished tasks)
- **[Story]**: User story the task belongs to (US1–US6)

---

## Phase 1: Setup (Shared Schema)

**Purpose**: Schema and model changes that more than one story reads.

- [X] T001 Create migration adding nullable json `layout` column to `worlds` via `php artisan make:migration add_layout_to_worlds_table --no-interaction`, in `database/migrations/`
- [X] T002 [P] Add `layout` to the fillable attributes and an `array` cast in `app/Models/World.php`
- [X] T003 [P] Add a `withLayout()` state to `database/factories/WorldFactory.php` producing a valid layout in the shape of [data-model.md](data-model.md): two floors (`ground` 0–4, `upper` 4–8), three zones (`studio` and `pool-terrace` on ground, `vocal-booth` nested in `studio`, `gallery` on upper), and one object `pool-lounger-1` in `pool-terrace` with spot `pool-lounger-1-seat` offering activity `recline` (posture `reclining`), each zone with an `entry`
- [X] T004 Add a `buildTestGlb(array $nodes): string` helper to `tests/Pest.php` that returns a minimal GLB (12-byte header, JSON chunk with `asset`, `scene`, `scenes`, `nodes`, no BIN chunk) from glTF node arrays, for marker import tests

**Checkpoint**: Schema and test helpers ready.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The layout import and world-state resolution every later story depends on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T005 Write feature tests in `tests/Feature/Api/WorldLayoutImportTest.php`: uploading an environment through `worlds.store` stores floors, zones (world-space outlines from node transforms including nested parents and rotation), entries, objects (with `zoneId` resolved by containment) and spots (position, facing yaw from local +Z, approach 0.6 m along facing); replacing the environment through `worlds.update` replaces the layout; a file with no markers stores an empty layout; invalid markers (missing id, duplicate id, zone without entry, unknown floor, overlapping floor ranges, spot outside an object, spot without activities, outline under three points) are skipped and listed in `layoutWarnings` while valid markers import. Build fixtures with `buildTestGlb()` (T004)
- [X] T006 Create `app/Actions/ParseEnvironmentLayout.php` (`php artisan make:class Actions/ParseEnvironmentLayout --no-interaction`): read the GLB header and JSON chunk, walk the node tree composing translation, rotation (quaternion) and scale into world matrices, collect nodes whose `extras.vera.type` is `floor`, `zone`, `entry`, `object` or `spot`, validate them per [contracts/environment-markers.md](contracts/environment-markers.md), and return `['layout' => [...], 'warnings' => [['node' => name, 'reason' => text], ...]]`
- [X] T007 Call `ParseEnvironmentLayout` in `store()` and `update()` of `app/Http/Controllers/Api/WorldController.php` whenever an environment file is uploaded, persist `layout`, and return `layoutWarnings` in the response
- [X] T008 [P] Include `layout` in `app/Http/Resources/WorldResource.php`, and extend `tests/Feature/Api/WorldResourceTest.php` to assert it is present for a member and that another user's world stays unreachable
- [X] T009 Create `app/Actions/ResolveWorldState.php`: given a `World` and positions (`user`, `residents` keyed by resident id), return for each point its floor (by height range including stairs, FR-001b, or the single implicit floor), innermost containing zone (point-in-polygon on the outline, within the zone's height range, deepest by parent chain), and pairwise user–resident distance. Covered by the tests in T011
- [X] T010 [P] Show `layoutWarnings` from the save response as a toast listing each node and reason, via `resources/js/utils/layoutWarnings.js`, from `resources/js/pages/CreateWorldPage.jsx` and `resources/js/pages/EditWorldPage.jsx`

**Checkpoint**: Worlds carry a validated layout, and positions can be resolved to floors and zones.

---

## Phase 3: User Story 1 - The resident knows the world she is in (Priority: P1) 🎯 MVP

**Goal**: The resident knows her floor and zone, the user's floor and zone, their distance, the objects around her and the other zones (FR-001–FR-010).

**Independent Test**: With the resident in the studio and the user on the pool terrace, she answers "where are you, where am I, is there a pool" correctly (quickstart scenario 1).

### Tests for User Story 1

- [X] T011 [P] [US1] Write feature tests in `tests/Feature/Api/ResidentWorldStatePromptTest.php` (fake the LLM with `Http::fake` as in `tests/Feature/AgentLoopSingleToolCallTest.php` and assert on the captured request body): sending a message with `worldId`, `worldSessionId` and `positions` adds a world-state section naming her zone and floor, the user's zone and floor, the rounded distance, the objects and activities of her zone, and other zones by name and floor only; a nested zone is described as "vocal booth, inside music studio"; a user on another floor is described as upstairs or downstairs; a world with an empty layout sends the same prompt as today (FR-010); for a layout with 40 zones the world-state section stays under 2,000 characters (FR-009); a `worldSessionId` from another user's world returns 404

### Implementation for User Story 1

- [X] T012 [US1] Create `app/Actions/BuildResidentWorldPrompt.php` with `worldState(World $world, WorldResident $resident, array $resolvedState): string` producing the section from T011: her zone in detail (description, objects, activities) and other zones by name and floor only, within 2,000 characters (FR-009)
- [X] T013 [US1] Extend `app/Actions/AppendWorldConversationContext.php` to accept optional positions, run `ResolveWorldState` and add the `BuildResidentWorldPrompt::worldState()` text under a `world_state` prompt key when the world has a non-empty layout
- [X] T014 [US1] Accept `worldSessionId` and `positions` (`positions.user.{x,y,z}`, `positions.residents.*.{x,y,z}`, numeric) in `sendMessage()` of `app/Http/Controllers/Api/ConversationController.php`, resolve the session through the requester's `WorldUser` as `store()` does, and pass positions to `AppendWorldConversationContext`
- [X] T015 [US1] Expose the latest player position and resident positions from `resources/js/components/world/WorldScene.jsx` to `resources/js/pages/WorldPage.jsx` (a ref updated each frame), and send them as `positions` with every message from `resources/js/components/world/WorldChat.jsx` through `extraParams` of `useConversationChat`
- [X] T016 [US1] Regenerate the penthouse environment with markers (outside the repository, in the penthouse generator): one floor; zones for every room of the plan (foyer, living room, sunken lounge, dining, bar, kitchen, pantry, music studio, vocal booth, powder room, master bedroom, walk-in closet, master bath, toilet room as private, pool terrace, pool, sun ledge, fire pit, pool bar, garden, pergola, garden deck) each with an entry; objects and spots for every stool, chair, sofa seat, bench, bed side, lounger, daybed and the bar counter, with postures per [contracts/environment-markers.md](contracts/environment-markers.md); upload it to the Creator's Penthouse world and confirm no `layoutWarnings`

**Checkpoint**: Story 1 is complete and demonstrable on its own.

---

## Phase 4: User Story 2 - Talk while moving, by text or voice (Priority: P2)

**Goal**: Conversations never pause the world; focus rules for typing; one conversation at a time; distance limit; hands-free voice with positional replies (FR-038–FR-049).

**Independent Test**: Quickstart scenario 2.

### Tests for User Story 2

- [X] T017 [P] [US2] Write `tests/Unit/ConversationRange.test.js` for `resources/js/components/world/conversationRange.js`: `conversationRangeState(distance)` returns `ok` below the warning distance (8 m), `warning` between warning and end (12 m), and `ended` beyond

### Implementation for User Story 2

- [X] T018 [US2] Create `resources/js/components/world/conversationRange.js` exporting the warning and end distances and `conversationRangeState(distance)`
- [X] T019 [US2] In `resources/js/pages/WorldPage.jsx`, stop disabling exploration while a conversation is open (`explorationEnabled` depends only on `status === 'ready'`), render `WorldChat` as an overlay panel on top of the scene instead of a side column, and stop pausing residents because of an open chat
- [X] T020 [US2] In `resources/js/components/world/FirstPersonController.jsx`, ignore movement keys while a text input or textarea has focus, and keep pointer lock behavior unchanged otherwise
- [X] T021 [US2] In `resources/js/components/world/WorldChat.jsx`, focus the message box on `Enter` when it is not focused, blur it after sending and on `Esc`, and show a close button (FR-040, FR-049)
- [X] T022 [US2] In `resources/js/components/world/InteractionSystem.jsx` and `resources/js/pages/WorldPage.jsx`, make `C` start a conversation only when none is open and close the open one otherwise, and move the `Esc`-closes-chat handler to only blur the input (FR-045, FR-049)
- [X] T023 [US2] In `resources/js/pages/WorldPage.jsx`, compute the distance between the player and the conversation resident every frame via `conversationRangeState`, show a warning banner in the `warning` state, and close the conversation in the `ended` state (FR-048); keep the per-frame distance in a ref and derive the banner state during render, per constitution Principle VIII
- [X] T024 [US2] Add voice mode to `resources/js/components/world/WorldChat.jsx` reusing `resources/js/hooks/useVoiceMode.js`, the `voice.transcribe` route and the `voice.synthesize` route as `resources/js/pages/ChatPage.jsx` does, sending messages with `voice_mode: true`, with a toggle and a listening/processing/speaking indicator (FR-041, FR-042)
- [X] T025 [US2] Add an `AudioListener` to the camera in `resources/js/components/world/WorldScene.jsx` and a `playVoice(audioArrayBuffer)` method per resident in `resources/js/components/world/ResidentController.jsx` that decodes the audio and plays it through a `PositionalAudio` attached to her VRM scene; route voice replies from `WorldChat` to the conversation resident through `WorldPage` (FR-043)
- [X] T026 [US2] Stop voice listening when the conversation closes, voice mode is turned off, the distance limit ends it, or the world page unmounts, in `resources/js/components/world/WorldChat.jsx` (FR-044)
- [X] T027 [US2] Mark the conversation resident in the conversation panel header (name and avatar) in `resources/js/components/world/WorldChat.jsx` (FR-047; the in-world highlight is T031)

**Checkpoint**: Story 2 works on its own on top of Story 1.

---

## Phase 5: User Story 3 - Find residents with name tags and a map (Priority: P3)

**Goal**: Name tags through walls, a highlighted conversation resident, an off-screen pointer, and a per-floor minimap and full map (FR-050–FR-059).

**Independent Test**: Quickstart scenario 3, including the connection node's two floors.

### Tests for User Story 3

- [X] T028 [P] [US3] Write `tests/Unit/WorldMapProjection.test.js` for `resources/js/components/world/worldMapProjection.js`: world-to-map projection for a floor's bounds, floor selection for a height (matching the layout rules), and marker label spreading so overlapping labels are offset (FR-056)

### Implementation for User Story 3

- [X] T029 [US3] Create `resources/js/components/world/worldMapProjection.js` with `projectToMap(point, floorBounds, mapSize)`, `floorForHeight(layout, y)` and `spreadLabels(markers, minSpacing)`
- [X] T030 [US3] Create `resources/js/components/world/NameTags.jsx`: a sprite per resident above her head with her name, `depthTest` off so it shows through walls, scaled by distance to stay readable, and offset vertically when tags overlap on screen so each stays readable (FR-050, FR-056)
- [X] T031 [US3] Highlight the conversation resident's tag in `NameTags.jsx` (distinct color and glow) using the open conversation's resident id passed from `WorldPage.jsx` (FR-051)
- [X] T032 [P] [US3] Create `resources/js/components/world/OffscreenIndicator.jsx`: an HTML arrow on the screen edge pointing toward the conversation resident when she is outside the camera frustum (FR-052)
- [X] T033 [US3] Create `resources/js/components/world/floorMaps.js`: after the environment loads, render one top-down image per floor with an orthographic camera fitted to the floor's bounds and a clipping plane at the floor's `maxY`, returning data URLs and bounds (FR-057); worlds without floors get one image
- [X] T034 [US3] Create `resources/js/components/world/WorldMap.jsx`: a corner minimap and a full-screen map toggled with `M`, drawing the current floor image, zone names, the player's position and facing, every resident's position and name, the conversation resident highlighted, and residents on other floors dimmed with their floor name (FR-053–FR-059)
- [X] T035 [US3] Switch the map floor automatically when the player's floor changes, with manual floor buttons, in `resources/js/components/world/WorldMap.jsx` (FR-058); derive the shown floor during render from the player's floor and the manual choice, per Principle VIII
- [X] T036 [US3] Mount `NameTags`, `OffscreenIndicator` and `WorldMap` from `resources/js/components/world/WorldScene.jsx` and `resources/js/pages/WorldPage.jsx`, feeding them the positions ref from T015

**Checkpoint**: Story 3 works on its own; residents are easy to find in the penthouse and the connection node.

---

## Phase 6: User Story 4 - The resident goes where she means to go (Priority: P4)

**Goal**: Action tags, route-finding, `go_to`, `follow`, `stop`, direct controls, and recorded outcomes (FR-011–FR-015, FR-020–FR-022, FR-036, FR-037).

**Independent Test**: Quickstart scenario 4.

### Tests for User Story 4

- [X] T037 [P] [US4] Write `tests/Unit/WorldNavigation.test.js` for `resources/js/components/world/worldNavigation.js`, building worlds with `WorldCollision` as `tests/Unit/WorldCollision.test.js` does: a route goes around a wall through a doorway; a 0.2 m staircase is climbed and descended; a 1.4 m drop is never taken; a passable water surface is not walkable but the floor beneath it is; an unreachable target returns `null`; smoothing removes waypoints with clear straight lines
- [X] T038 [P] [US4] Write feature tests in `tests/Feature/Api/ResidentActionTagTest.php`: a reply containing `[action: go_to bar]` returns `action` `{verb: go_to, target: bar}` and the tag is stripped from `content`; an unknown id returns `{verb: invalid, reason}`; a malformed action tag returns `invalid`; only the first action tag is honoured
- [X] T039 [P] [US4] Write feature tests in `tests/Feature/Api/ResidentActivityTest.php`: `POST …/residents/{resident}/activities` creates a `requested` activity with the resident's current zone; `PATCH …/activities/{activity}` sets outcome, reason and `finished_at`; a second PATCH on a finished activity returns 422 and leaves the outcome unchanged; a resident of another world, another user's session, and another user's world each return 404
- [X] T040 [P] [US4] Extend `tests/Feature/Api/ResidentWorldStatePromptTest.php`: the prompt includes a recent-activity section with her last 8 activities (verb, target, zone, outcome and reason, minutes ago), newest first

### Implementation for User Story 4

- [X] T041 [US4] Create migration `create_resident_activities_table` with the columns and index in [data-model.md](data-model.md), in `database/migrations/`
- [X] T042 [P] [US4] Create `app/Models/ResidentActivity.php` (`php artisan make:model ResidentActivity --factory --no-interaction`) with relationships to `WorldSession` and `WorldResident`, and `database/factories/ResidentActivityFactory.php` with `finished()` and `failed()` states
- [X] T043 [US4] Parse `[action: …]` in `app/Services/LlmResponseTagParser.php` per [contracts/action-tags.md](contracts/action-tags.md), validating ids against the world layout, returning `action` alongside `pose`
- [X] T044 [US4] Return `action` from `sendMessage()` in `app/Http/Controllers/Api/ConversationController.php` and in `resources/js/hooks/useConversationChat.js` pass it to an `onAction` callback
- [X] T045 [US4] Create `app/Http/Controllers/Api/ResidentActivityController.php` with `store` and `update` (inline validation, following `WorldSessionController`), and routes `worlds.sessions.residents.activities.store` / `.update` under `worlds/{world}/sessions/{session}/residents/{resident}` in `routes/api.php`, scoped through `WorldUser` as in `WorldSessionController`
- [X] T046 [US4] Add `recentActivity(WorldSession $session, WorldResident $resident): string` to `app/Actions/BuildResidentWorldPrompt.php` and include it in `AppendWorldConversationContext` when a session is given
- [X] T047 [US4] Create `resources/js/components/world/worldNavigation.js`: build a walkable grid over the `WorldCollision` octree (0.25 m cells, every ground level per column within floor ranges or environment bounds, neighbours linked when the step, drop and body-clearance checks pass), A* search, and line-of-sight smoothing
- [X] T048 [US4] Build the navigation grid after loading in a `NavigationBuilder` in `resources/js/components/world/WorldScene.jsx` (4 ms per frame) and pass it to residents
- [X] T049 [US4] In `resources/js/components/world/ResidentController.jsx`, add route following to the existing locomotion cycle: a `routeTo(target)` command sets the heading toward the next waypoint instead of a random heading, uses Walk Start, Walk and Walk Stop as today, and resolves with `completed`, `failed` (no route or stuck) or `interrupted`
- [X] T050 [US4] Create `resources/js/components/world/residentActions.js` exporting `executeAction(action, context)` for `go_to` (zone entry or the object's nearest free spot approach), `follow` (repath toward a point 1 m behind the player every 0.5 s, FR-015), `stop`, and `invalid` (immediate `failed` with the reason); fail with a reason when the target zone, object or spot is missing from the current layout (FR-021a); refuse private zones unless the action came from the user (FR-005)
- [X] T051 [US4] In `resources/js/pages/WorldPage.jsx`, execute actions from `onAction` for the conversation resident, record them with `POST …/activities`, and report outcomes with `PATCH`
- [X] T052 [US4] Add direct controls to `resources/js/pages/WorldPage.jsx`: `F` makes the conversation resident follow and `X` makes her stop, with on-screen buttons; record them as `requested` activities with reason "direct control" so she is told (FR-037)

### Revision for User Story 4: tool calling

- [X] T086 [US4] Write feature tests in `tests/Feature/Api/ResidentWorldToolsTest.php` (faked LLM with `toolCallResponse`): world conversations send the world tools, with `go_to` limited to the layout's ids; a `go_to` call returns `action` in the response; an unknown id is returned to her as a tool error within the turn and yields no action; a second action tool in one turn is rejected and the first action stands; `where_can_i`, `what_is_in` and `describe` return layout data; an assistant whose model lacks tool calling gets 422 in a world conversation
- [X] T087 [US4] Create the world toolbox and tools in `app/Services/AgentLoop/Tools/World/` per [contracts/world-tools.md](contracts/world-tools.md)
- [X] T088 [US4] Run world conversations through `AgentLoopRunner` with the world tools (plus the agent-mode tools for agent-mode assistants) in `sendMessage()` of `app/Http/Controllers/Api/ConversationController.php`, return the chosen action, require a tool-capable model for assistants, and give NPCs the tools on the default model
- [X] T089 [US4] Remove `[action: …]` parsing from `app/Services/LlmResponseTagParser.php`, the action tags prompt section, and `tests/Feature/Api/ResidentActionTagTest.php` (replaced by T086)
- [X] T090 [US4] Reject assistants without a tool-capable model as world residents in `app/Http/Controllers/Api/WorldResidentController.php`, with a test in `tests/Feature/Api/WorldResidentControllerTest.php`
- [X] T091 [US4] Add a world awareness section to the resident prompt in `app/Actions/BuildResidentWorldPrompt.php` (added by `app/Actions/AppendWorldConversationContext.php` in marked worlds) reminding her she can use her world tools on her own initiative, with a test in `tests/Feature/Api/ResidentWorldStatePromptTest.php` (FR-021d)

**Checkpoint**: Story 4 works; she goes places when asked and knows what happened.

---

## Phase 7: User Story 5 - The resident uses things in the world (Priority: P5)

**Goal**: Spots, postures, posture-tagged poses and occupancy (FR-016–FR-019f).

**Independent Test**: Quickstart scenario 5, plus a seated laugh with a sitting version and a seated dance with only a standing version.

### Tests for User Story 5

- [ ] T053 [P] [US5] Extend `tests/Feature/Api/AssistantPoseTest.php`: poses accept `posture` (`standing` default, `sitting`, `lying`, `reclining`); the same name is allowed once per posture and rejected twice in the same posture
- [ ] T054 [P] [US5] Write feature tests in `tests/Feature/Api/ResidentPosturePromptTest.php`: with `residentPosture: sitting` in the message request, the pose list marks sitting poses as available and standing-only poses as "requires standing up" (FR-019f); without it, standing is assumed
- [ ] T055 [P] [US5] Write `tests/Unit/PosturePoses.test.js` for `resources/js/components/world/worldMotionPoses.js`: `resolvePose(poses, name, posture)` returns the posture's version, or the standing version with `standUp: true` when none exists; `defaultPoseFor(poses, posture)` falls back to the standing `default`

### Implementation for User Story 5

- [ ] T056 [US5] Create migration adding string `posture` (default `standing`) to `poses` and replacing the unique index (`assistant_id`, `name`) with (`assistant_id`, `name`, `posture`), in `database/migrations/`
- [ ] T057 [P] [US5] Add `posture` to `app/Models/Pose.php` and a `posture()` state to `database/factories/PoseFactory.php`
- [ ] T058 [US5] Accept and validate `posture` in `app/Http/Controllers/Api/AssistantPoseController.php` (with a unique rule scoped to assistant and posture) and return it wherever poses are serialized, including `app/Http/Resources/WorldResidentResource.php`
- [ ] T059 [US5] Make `promptPoseNames()` in `app/Models/Assistant.php` return names with postures, and have `appendExpressionTags()` in `app/Http/Controllers/Api/ConversationController.php` list poses by the request's `residentPosture` per T054
- [ ] T060 [US5] Group poses into standing, sitting, lying and reclining sections, each with its own `default`, in `resources/js/components/PoseEditor.jsx`, `resources/js/components/DefaultPoseEditor.jsx` and `resources/js/pages/EditAssistantPage.jsx` / `CreateAssistantPage.jsx`
- [ ] T061 [US5] Add `resolvePose(poses, name, posture)` and `defaultPoseFor(poses, posture)` to `resources/js/components/world/worldMotionPoses.js`
- [ ] T062 [US5] In `resources/js/components/world/ResidentController.jsx`, track `posture`, hold the posture's default pose as the idle clip, blend between defaults when posture changes (existing return blend), and on a triggered pose use `resolvePose` so a standing-only pose blends her back to standing, plays, and leaves her standing (FR-019c, FR-019d); keep posture in refs read by `useFrame`, not state set from effects (Principle VIII)
- [ ] T063 [US5] Add `use` and `zone` to `resources/js/components/world/residentActions.js`: walk to the spot's `approach`, blend over 0.4 s to the spot position and facing, enter the activity's posture, play its pose once, and hold until she leaves; `zone` performs a zone activity where she stands
- [ ] T064 [US5] Track spot occupancy for all residents in `resources/js/pages/WorldPage.jsx`, fail `use` on a taken spot with reason "spot taken", and free the spot when she leaves (FR-017)
- [ ] T065 [US5] Send the conversation resident's current posture as `residentPosture` with every message from `resources/js/components/world/WorldChat.jsx`

**Checkpoint**: Story 5 works; she sits, lies and reclines on marked furniture and reacts in the right posture.

---

## Phase 8: User Story 6 - The resident chooses what to do when left alone (Priority: P6)

**Goal**: Autonomous residents decide activities with their own model while the user is present, with `(reason) *action*` lines, thought bubbles and restored state (FR-023–FR-032).

**Independent Test**: Quickstart scenario 6.

### Tests for User Story 6

- [ ] T066 [P] [US6] Write feature tests in `tests/Feature/Api/ResidentDecisionTest.php` (faked LLM): a decision for an `autonomous` resident returns `line`, `action` or `pose`, `activityId` and `messageId`, stores the line as an assistant message in the resident's session conversation (creating it when missing) and creates an `idle` activity with the stated reason; `previous` records that activity's outcome; a request within 8 seconds of the last returns 429; a non-autonomous resident returns 422; an invalid tag in the reply yields `action.verb = invalid`
- [ ] T067 [P] [US6] Extend `tests/Feature/Api/ResidentDecisionTest.php`: the decision prompt includes her persona, the world state, recent activity, available zone activities, spots with free/taken status from `occupiedSpots`, every pose in her library marked by posture, and the one-line output instruction including the guidance to vary activities; it uses the resident's own conversation model
- [ ] T068 [P] [US6] Write feature tests in `tests/Feature/Api/ResidentStateTest.php`: `PUT …/state` stores position, rotation, spot, activity and posture; `worlds.sessions.index` returns `residentStates` keyed by resident id; other users' sessions and residents of other worlds return 404
- [ ] T069 [P] [US6] Extend `tests/Feature/Api/WorldResidentControllerTest.php`: `behavior: autonomous` is accepted and returned

### Implementation for User Story 6

- [ ] T070 [US6] Add `case Autonomous = 'autonomous';` to `app/Enums/WorldResidentBehavior.php`, allow it in `app/Http/Requests/UpsertWorldResidentRequest.php`, and offer it in `resources/js/components/WorldResidentsEditor.jsx`
- [ ] T071 [US6] Create migration `create_world_session_residents_table` per [data-model.md](data-model.md), `app/Models/WorldSessionResident.php` and `database/factories/WorldSessionResidentFactory.php`
- [ ] T072 [US6] Create `app/Http/Controllers/Api/ResidentStateController.php` with `update` and route `worlds.sessions.residents.state.update`, and include `residentStates` in `index()` of `app/Http/Controllers/Api/WorldSessionController.php`
- [ ] T073 [US6] Add `availableActivities(World $world, WorldResident $resident, array $resolvedState, array $occupiedSpots, string $posture): string` and `idleInstruction(): string` to `app/Actions/BuildResidentWorldPrompt.php`, following [contracts/action-tags.md](contracts/action-tags.md); the instruction asks her to vary activities and to give a reason when repeating her previous one (SC-005)
- [ ] T074 [US6] Create `app/Http/Controllers/Api/ResidentDecisionController.php` with `store`, form request `app/Http/Requests/StoreResidentDecisionRequest.php`, and route `worlds.sessions.residents.decisions.store`: enforce autonomy and the 8-second floor, record `previous`, build the prompt from the resident's own prompt plus world context, world state, recent activity, available activities and the idle instruction, run her turn through `AgentLoopRunner` with the world tools the same way `sendMessage()` does, take the chosen action from the toolbox, store the visible line as an assistant message and an `idle` activity, and return the response in [contracts/world-agency-api.md](contracts/world-agency-api.md)
- [ ] T075 [US6] Create `resources/js/hooks/useResidentAgency.js`: for each autonomous resident, wait a random 10–60 s after her previous step finishes, request a decision with positions, occupied spots and the previous outcome, execute it through `residentActions.js` (or play the pose through `resolvePose`), report the outcome, and repeat; skip while a conversation with her is open (FR-030b); pause everything and mark running steps `interrupted` when the page unmounts or `document.visibilityState` becomes `hidden` (FR-031, FR-032); timers and async work stay as closures local to the effect, per Principle VIII
- [ ] T076 [US6] Interrupt her current step when the user opens a conversation with her, reporting `interrupted`, in `resources/js/hooks/useResidentAgency.js` and `resources/js/pages/WorldPage.jsx` (FR-027)
- [ ] T077 [US6] Create `resources/js/components/world/ThoughtBubble.jsx`: the latest `(reason) *action*` line above the resident while that step runs (FR-030a), mounted from `WorldScene.jsx`
- [ ] T078 [US6] Append idle lines to the open or cached conversation view in `resources/js/components/world/WorldChat.jsx` so they appear in history (FR-030)
- [ ] T079 [US6] Save resident state every 10 s and on exit with `PUT …/state` from `resources/js/pages/WorldPage.jsx` (same cadence as `persistPosition`), and on load place each resident at her saved position, spot and posture in `resources/js/components/world/ResidentController.jsx` (FR-032)
- [ ] T080 [US6] Trigger an immediate first decision for autonomous residents when the user enters the world, with "the Creator just arrived" in the decision request context, in `resources/js/hooks/useResidentAgency.js` and `app/Actions/BuildResidentWorldPrompt.php` (FR-028)
- [ ] T081 [US6] Stagger initial waits so several residents never request decisions in the same second (FR-029), in `resources/js/hooks/useResidentAgency.js`

**Checkpoint**: All six stories work together.

---

## Phase 9: Polish & Cross-Cutting Concerns

- [ ] T082 [P] Export markers for the connection node environment (floors for both levels, zones per area, entries) and upload it; confirm no `layoutWarnings`
- [ ] T083 [P] Log every action outcome of `failed` with its reason via `console.error` in `resources/js/components/world/residentActions.js`, and every decision request failure in `useResidentAgency.js` (Principle V)
- [ ] T084 Run every scenario in [quickstart.md](quickstart.md) in the penthouse and the connection node, and fix what fails
- [ ] T085 Before each pull request, run the gates once per CLAUDE.md: `vendor/bin/pint`, `npm run lint`, `php artisan test`, and `node --test tests/Unit/`; fix what surfaces

---

## Dependencies & Execution Order

### Phase dependencies

- **Setup (Phase 1)**: none.
- **Foundational (Phase 2)**: depends on Setup; blocks every story.
- **US1 (P1)**: depends on Foundational.
- **US2 (P2)**: depends on Foundational; uses the positions ref from T015 for the distance limit.
- **US3 (P3)**: depends on Foundational and T015; the conversation highlight uses US2's single-conversation state but degrades to no highlight without it.
- **US4 (P4)**: depends on US1 (prompt world state) for the resident to act on knowledge; the navigation grid is independent.
- **US5 (P5)**: depends on US4 (`residentActions.js`, route following).
- **US6 (P6)**: depends on US4 and US5 (execution of every verb).
- **Polish**: after the stories it touches.

### Within each story

- Tests first, then migrations and models, then actions and controllers, then frontend.

### Parallel opportunities

- T002 and T003; T008 and T010 alongside T006–T007.
- Test tasks marked [P] within each story.
- US2 and US3 can proceed in parallel once T015 lands.
- The navigation work (T037, T047, T048) can start alongside US1–US3.

## Parallel Example: User Story 4

```text
Task: "T037 Write tests/Unit/WorldNavigation.test.js"
Task: "T038 Write tests/Feature/Api/ResidentActionTagTest.php"
Task: "T039 Write tests/Feature/Api/ResidentActivityTest.php"
Task: "T040 Extend tests/Feature/Api/ResidentWorldStatePromptTest.php"
```

## Implementation Strategy

### MVP first

1. Phases 1 and 2.
2. Phase 3 (US1) with the penthouse markers (T016).
3. Validate quickstart scenario 1, then open the first pull request.

### Incremental delivery

Each subsequent story is its own pull request in priority order (US2 → US6), each validated with its quickstart scenario before merging.
