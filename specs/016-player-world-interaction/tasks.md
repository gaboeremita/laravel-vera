---

description: "Task list for Player World Interaction"
---

# Tasks: Player World Interaction

**Input**: Design documents from `/specs/016-player-world-interaction/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Tests**: Included. Constitution Principle VI requires Pest feature tests, backed by factories, for backend behavior; they use the helpers in `tests/Pest.php` (`worldStateScenario()`, `sentSystemPrompt()`, `fakeTurn()`, `toolCallResponse()`, `finalAnswerResponse()`) and `WorldFactory::withLayout()` (floors `ground`/`upper`; zones `studio`, `vocal-booth` inside `studio`, `pool-terrace` with zone activity `swim`, `gallery` upstairs; object `pool-lounger-1` with spot `pool-lounger-1-seat` offering `recline`). Pure client logic gets `node --test` unit tests in `tests/Unit/*.test.js`, importing modules from `resources/js/components/world/` as `tests/Unit/ResidentActions.test.js` does. Rendering, motion, themes and audio are verified manually with [quickstart.md](quickstart.md).

**Verification cadence**: Per CLAUDE.md, Pint, ESLint and the test suites run once, right before the pull request (T060). Tests are written with each story and run as part of that gate. A single-file `php -l` or `node --check` right after editing that same file is allowed to catch a malformed edit.

**Visual language**: Every HUD component uses the shared classes from T003, colours only from theme tokens, and an entrance and exit animation, with the reduced-motion fallback ([contracts/player-controls-and-hud.md](contracts/player-controls-and-hud.md), [research R11](research.md)).

**Organization**: One phase per user story, in spec priority order. Each story can ship on its own (see plan.md Delivery Slices).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on unfinished tasks)
- **[Story]**: User story the task belongs to (US1–US5)

---

## Phase 1: Setup (Shared Modules and Styles)

**Purpose**: Moves that give existing helpers their second caller, and the HUD style base every story's interface uses.

- [X] T001 [P] Move `themeColor(variable, fallback)` out of `resources/js/components/world/NameTags.jsx` into a new `resources/js/utils/themeColor.js` (named export), and import it back in `NameTags.jsx`
- [X] T002 [P] Export `SWIM_DEPTH = 1.1` and `LEAVE_WATER_DEPTH = 0.9` from `resources/js/components/world/collisionCheck.js`, remove the local constants of the same names from `resources/js/components/world/ResidentController.jsx`, and import them there
- [X] T003 Add the world HUD style base to `resources/css/app.css`, built only from theme tokens (`--accent`, `--bg-0`, `--fg-1..3`, `--border-1`, `--warning`, `--font-display`) with `color-mix(in oklab, …)` for glows: `.world-hud-panel` (translucent `bg-0`, `backdrop-filter: blur(10px)`, 1 px accent-tinted border, corner brackets via `::before`/`::after`, drifting scan-line overlay with `repeating-linear-gradient`), `.world-hud-glow` (2.4 s breathing `text-shadow`/`box-shadow` pulse), `.world-hud-label` (uppercase, wide tracking, small size, matching the existing world buttons); keyframes `hud-wipe-in` (clip-path wipe with a bright leading edge), `hud-dissolve-out` (fade, blur and rise), `hud-slide-in-right`, `hud-rise-in` (from slightly below), `hud-type-in`, `hud-breathe`, `hud-scan-drift`; and a `@media (prefers-reduced-motion: reduce)` block that turns every `hud-*` entrance and exit into a 150 ms fade and stops breathing and scan drift

---

## Phase 2: Foundational (Player State)

**Purpose**: One source of truth for where the user's feet are and what they are doing. Every story reads it.

**⚠️ CRITICAL**: Complete before any story; swimming and postures move the camera away from `feet + eye height`, and every consumer must stop assuming that.

- [X] T004 In `resources/js/components/world/FirstPersonController.jsx`, accept a `playerState` ref prop and keep it current every frame as `{ footPosition: {x, y, z}, movement: 'walking', posture: 'standing', spotId: null, activityId: null, approach: null }` (fields per [data-model.md](data-model.md)). `footPosition` is the existing `footPosition` ref's value. Keep reporting `onPositionChange` as the eye position computed as `footPosition + PLAYER_EYE_HEIGHT`, not the camera's position.
- [X] T005 In `resources/js/components/world/WorldScene.jsx`, accept and pass `playerState` to `FirstPersonController`, and change `PlayerViewTracker` to report `{ x, y, z }` from `playerState.current.footPosition` (yaw still from the camera) instead of `camera.position.y - PLAYER_EYE_HEIGHT`; pass `playerState` to `InteractionSystem` too, and change `resources/js/components/world/InteractionSystem.jsx` to measure resident distance and height difference from `playerState.current.footPosition` instead of the camera, so starting and ending conversations works while seated, lying or swimming (FR-029)
- [X] T006 In `resources/js/pages/WorldPage.jsx`, create `const playerState = useRef(null)`, pass it to `WorldScene`, and change `getPositions()` so `user` comes from `playerState.current.footPosition` (falling back to the current eye-based value only before the first frame). Add `getUserState()` returning `{ posture, spotId, activityId }` from `playerState.current`, or `null` before the first frame.

**Checkpoint**: Behaviour is unchanged; `getFollowTarget`, the conversation range and the server's floor lookup now read foot positions.

---

## Phase 3: User Story 1 - Know where I am (Priority: P1) 🎯 MVP

**Goal**: Zone title cards on crossings and on arrival, and an always-visible location readout.

**Independent Test**: In the penthouse, walk foyer → living room → sunken lounge → pool terrace; each crossing shows one card with the right name (the lounge card says *Living room*), stepping back and forth at the lounge steps does not replay it, and the readout always matches. The Index shows floor names; the Connection Node shows nothing.

### Tests for User Story 1

- [X] T007 [P] [US1] Write `tests/Unit/WorldLocation.test.js` against a layout literal mirroring `WorldFactory::withLayout()` (same ids, outlines, heights; copy the values from `database/factories/WorldFactory.php`): `floorAt` picks by height range and returns null without floors; `zoneAt` returns the innermost zone (`vocal-booth` over `studio`), respects zone height ranges and the point's floor, and returns null outside every outline; `zoneChain` returns outermost first; `createCrossingTracker({ debounceMs: 2000 })` reports a crossing into a new zone, ignores re-entering a zone left under 2 s earlier (fake timestamps passed in), reports `null` zones without announcing them, and announces the first zone on the first update

### Implementation for User Story 1

- [X] T008 [P] [US1] Create `resources/js/components/world/worldLocation.js` with `floorAt(layout, y)`, `zoneAt(layout, point)`, `zoneChain(layout, zone)` as line-for-line ports of `app/Actions/ResolveWorldState.php` (same even-odd outline test, same floor filter, deepest chain wins), and `createCrossingTracker({ debounceMs })` exposing `update(zoneId, now) → { changed: boolean, announce: boolean }` that remembers when each zone was last left
- [X] T009 [US1] Create `resources/js/components/world/LocationTracker.jsx` (renders `null`, inside the canvas): every 150 ms read `playerState.current.footPosition`, resolve floor, zone and chain with `worldLocation.js`, and call `onLocationChange({ floor, zone, zoneChain, announce })` only when the zone or floor changes. Do nothing when `layout.zones` is empty.
- [X] T010 [P] [US1] Create `resources/js/components/world/hud/ZoneTitleCard.jsx`: props `{ zone, contextLine, cardKey }`; renders the zone name in `--font-display` with `hud-wipe-in` and letter spacing easing from wide to normal, the context line (parent zone name, then floor name, joined with ` · `) fading in 150 ms later, a thin accent rule that draws outward beneath the name, and `hud-dissolve-out` after 3 s; a new `cardKey` restarts it (replacing any showing card). Positioned upper centre below the conversation warning, `pointer-events: none`.
- [X] T011 [P] [US1] Create `resources/js/components/world/hud/LocationReadout.jsx`: props `{ zoneName, floorName, worldName }`; a small `.world-hud-panel` with `.world-hud-glow` showing `ZONE · FLOOR` (floor only when the world has several floors), or the world's name outside any zone; text changes cross-fade. Positioned bottom right directly above the minimap, clear of the minimap's floor buttons.
- [X] T012 [US1] Mount `LocationTracker` in `resources/js/components/world/WorldScene.jsx` (props `layout`, `playerState`, `onLocationChange`), passing `onLocationChange` through from `WorldPage`
- [X] T013 [US1] In `resources/js/pages/WorldPage.jsx`, hold `location` state from `onLocationChange`; show `ZoneTitleCard` when `announce` is true (and for the first location after the world becomes ready), holding back while the full map is open and showing the latest crossing when it closes; render `LocationReadout` while exploring; render neither when `world.layout?.zones` is empty

**Checkpoint**: US1 works on its own in every marked world.

---

## Phase 4: User Story 2 - Run, crouch and swim (Priority: P2)

**Goal**: Shift runs, Q toggles crouch-walking, and deep water floats the view at the surface.

**Independent Test**: Run the faire's Lane (about half the walking time); crouch-walk around a table and stand with Q or Shift; walk down the penthouse pool steps, swim across and walk out, with the view never going under the surface.

### Tests for User Story 2

- [X] T014 [P] [US2] Write `tests/Unit/PlayerMotion.test.js`: `nextMovementMode` enters `swimming` above `SWIM_DEPTH` and leaves it below `LEAVE_WATER_DEPTH` (hysteresis between them keeps the current mode), crouching ends on entering swimming depth, Shift while crouched gives `running`, crouch cannot start while swimming; `movementSpeed` returns 3.5, 7, 1.75, 1.925 and 2.975 m/s for walking, running, crouching, swimming and swimming with Shift; `eyeHeightFor` returns 1.6 standing and 1.05 crouching; `swimEyeY(surfaceY, t, reducedMotion)` stays at `surface + 0.12 ± 0.02` and has no bob with reduced motion

### Implementation for User Story 2

- [X] T015 [P] [US2] Create `resources/js/components/world/playerMotion.js` exporting `WALK_SPEED`, `nextMovementMode({ current, runHeld, crouchToggled, waterDepth })`, `movementSpeed(mode, runHeld)`, `eyeHeightFor(mode)`, `swimEyeY(surfaceY, timeSeconds, reducedMotion)` and `targetFov(mode, reducedMotion)` (70°, 76° while running) per [research R2](research.md), importing `SWIM_DEPTH` and `LEAVE_WATER_DEPTH` from `collisionCheck.js`
- [X] T016 [US2] Extend `resources/js/components/world/FirstPersonController.jsx`: track `ShiftLeft`/`ShiftRight` held and a crouch toggle on `KeyQ` keydown (ignored while typing, while swimming or on a spot), read water depth each frame from `collisionWorld.waterSurfaceAbove(foot.x, foot.z, foot.y)`, derive the mode with `nextMovementMode`, move at `movementSpeed`, ease the eye height between modes over 0.25 s, place the camera at `swimEyeY` while swimming, ease the camera's FOV toward `targetFov` (calling `updateProjectionMatrix`), read `prefers-reduced-motion` via `matchMedia` with a change listener, write `movement` and `posture` (`crouching`/`swimming`/`standing`) into `playerState`, and call a new `onMovementChange(mode)` prop only when the mode changes. Add a comment above the swim branch explaining that the body stays on the pool floor so walls and steps keep colliding.
- [X] T017 [P] [US2] Create `resources/js/components/world/hud/SwimOverlay.jsx`: while `active`, an edge vignette in a water tint mixed from `--accent` and `--bg-0` with an animated caustic shimmer (two drifting radial-gradient layers, stopped under reduced motion), fading in and out over 400 ms; on each change of `active`, play a short splash synthesised with Web Audio (white-noise buffer through a band-pass filter with a quick gain envelope, lower pitch on leaving), created lazily on the first user gesture
- [X] T018 [P] [US2] Create `resources/js/components/world/hud/PostureHint.jsx`: props `{ hint }`; a bottom-centre `.world-hud-panel` pill showing the key hint (for example `Q — STAND UP`), with `hud-rise-in` and a fade out when `hint` becomes null
- [X] T019 [US2] In `resources/js/pages/WorldPage.jsx` and `resources/js/components/world/WorldScene.jsx`, pass `onMovementChange` through, hold `movement` state, render `SwimOverlay active={movement === 'swimming'}` and `PostureHint hint={movement === 'crouching' ? 'Q — STAND UP' : null}` (US4 extends the hint)

**Checkpoint**: US2 works in every world, marked or not.

---

## Phase 5: User Story 3 - See and read what the world holds (Priority: P3)

**Goal**: Nearby objects breathe, the focused object's spots glow, and E and G open cards with descriptions, activities and live availability, chosen with the arrow keys.

**Independent Test**: At the Rhodes, press E and read its card; at the kitchen island, move the highlight with ↓/↑ and see it wrap; while a resident reclines on a pool lounger, the loungers' card shows `TAKEN · <NAME>` for hers; G in the pool terrace shows *Look out at the city*.

### Tests for User Story 3

- [X] T020 [P] [US3] Write `tests/Unit/ObjectFocus.test.js` with a small layout (two objects 1.5 m apart, one with three spots spread 3 m wide, one object upstairs): `reachPoints` includes the object position and every spot's approach point; `pickFocus` returns null beyond 2.5 m, ignores objects on another floor, prefers the object within 30° of the view direction over a nearer one, falls back to the nearest when none is within 30°, and reaches the wide object from beside its far spot; `objectsWithin(layout, foot, floorId, 6)` lists only same-floor objects within 6 m; `spotAvailability(object, activityId, occupiedSpots, residentNames)` returns `{ free, total, takenBy }` with `'user'` shown as `YOU`

### Implementation for User Story 3

- [X] T021 [P] [US3] Create `resources/js/components/world/objectFocus.js` exporting `REACH = 2.5`, `GAZE_ANGLE = Math.PI / 6`, `reachPoints(object)`, `pickFocus({ layout, foot, forward, floorId })`, `objectsWithin(layout, foot, floorId, radius)` and `spotAvailability(...)` per [research R5](research.md) and the test in T020, using `floorAt` from `worldLocation.js`
- [X] T022 [US3] Create `resources/js/components/world/FocusTracker.jsx` (inside the canvas, renders null): each frame compute the focused object from `playerState.current.footPosition`, the camera's forward vector and the current floor; call `onFocusChange(object | null)` only on change; project the focused object's label anchor (object position + 1.4 m) to screen and write `transform` and `opacity` straight into `labelRef.current.style` (hidden when behind the camera), as `OffscreenIndicator.jsx` does; call `onNearbyChange(ids)` when the set of objects within 6 m changes
- [X] T023 [P] [US3] Create `resources/js/components/world/SpotBeacons.jsx` (inside the canvas) per [research R4](research.md): for each spot of the focused object, a flat ring mesh at the spot surface for resting activities, or on the floor at `spot.approach` for standing ones, with a `ShaderMaterial` (additive blending, `depthWrite: false`, uniforms for colour, time and fade) drawing a pulsing ring, a slowly rotating dashed inner arc and a few rising motes; a soft vertical light column (cylinder, vertical alpha gradient) at the object's position; a faint breathing dot sprite at every nearby object that is not focused; colours read with `themeColor('--accent')` and `themeColor('--warning')`, taken spots dimmed and warning-coloured by reading `occupiedSpots.current` each frame; beacons fade over 300 ms on focus change; time stops advancing under reduced motion; dispose geometries and materials on unmount
- [X] T024 [P] [US3] Create `resources/js/components/world/hud/FocusPrompt.jsx`: a `forwardRef` element positioned by `FocusTracker`; shows the object name in `--font-display` with `.world-hud-glow` and a key cap `E — INSPECT` below it; `hud-wipe-in` on each new object, fading out when focus is lost
- [X] T025 [P] [US3] Create `resources/js/components/world/hud/InspectCard.jsx`: props `{ kind: 'object' | 'zone', title, contextLine, description, rows, highlightedIndex, onHighlight, onChoose }` where each row is `{ id, name, posture, availability }`. Renders a right-side `.world-hud-panel` sliding in with `hud-slide-in-right`; title in `--font-display`; zone chain or floor as the context line; description; the activity list with rows staggered 40 ms on entry, each with a posture glyph (inline SVG for standing, sitting, lying, reclining), the name and, for object rows, availability (`FREE`, `2 OF 8 FREE`, `TAKEN · VERA`, `IN USE · YOU`; zone rows have none); the highlighted row carries an accent bar on its left edge, a soft glow and a slow shimmer, and the highlight slides between rows over 120 ms; mouse hover and click also highlight and choose, for when the pointer is unlocked; `NOTHING TO DO HERE` when there are no rows; a hint line `↑ ↓ — CHOOSE · ENTER — START · E — CLOSE` (G for zone cards; Esc also closes). Positioned clear of the conversation panel (left) and the minimap and readout (bottom right).
- [X] T026 [US3] Mount `FocusTracker` and `SpotBeacons` in `resources/js/components/world/WorldScene.jsx`, passing `layout`, `playerState`, `occupiedSpots`, the focus label ref and the focus and nearby callbacks from `WorldPage`
- [X] T027 [US3] In `resources/js/pages/WorldPage.jsx`: hold `focusedObject` and `card` (`{ kind, id }`) plus `highlightedIndex` state; `KeyE` toggles the focused object's card, `KeyG` toggles the current zone's card (only inside a zone), and opening one closes the other; `ArrowDown`/`ArrowUp` move the highlight with wrap-around and `preventDefault` while a card is open; `Escape` closes the open card; every key is ignored while `isTypingTarget(event.target)`; close the object card only when its own object leaves reach (checked with `pickFocus`'s reach test against that object, not by focus changes, so glancing at a neighbouring object keeps it open), and the zone card when the zone changes; build object rows from the object's spots grouped by activity with `spotAvailability`, and zone rows from the zone's activities with no availability, re-reading `occupiedSpots` every 500 ms while a card is open; map resident ids to names from `world.residents`; render `FocusPrompt` (ref from T022) and `InspectCard`, hiding the prompt while a card is open

**Checkpoint**: US3 works on its own: every object in a marked world can be found and read.

---

## Phase 6: User Story 4 - Sit, lie down, recline and do things like the residents (Priority: P4)

**Goal**: Starting activities from cards, resting postures with gliding views, standing activities with a progress ring, spots claimed against residents, residents knowing what the user does, action lines for the conversation partner who can see the user, and first-person observations logged by every other resident who can, which also reach their idle decisions.

**Independent Test**: Sit at the bar, recline on a lounger and lie on the bed, getting up in between; make coffee and cancel it once; ask a resident in chat what you are doing; ask her to use your lounger; sit, get up and sit again with one resident in plain view and one behind a wall, then open each one's chat: the first shows three `*I see the user …*` messages of hers and knows, the second shows nothing new.

### Tests for User Story 4

- [X] T028 [P] [US4] Write `tests/Unit/ActivityLines.test.js`: `actionLine({ activity, object })` gives `*sits down at the bar counter*` for "Sit down" at "Bar counter", `*has a drink at the pool bar*` for "Have a drink", `*watches TV at the sunken sofa*` for "Watch TV", `*washes the dishes at the kitchen sink*`, `*plays the Rhodes*` (object name already in the activity), `*looks out at the city*` for a zone activity with no object; `getUpLine(object)` gives `*gets up from the bar counter*`; `joinLines([...])` joins queued lines with a space; `observationLine({ activity, object })` gives `*I see the user sit down at the bar counter*`, `*I see the user make coffee at the back counter*`, `*I see the user play the Rhodes*` and `*I see the user look out at the city*` (activity name in its base form, first letter lowercased, same `at the <object>` rule); `observationGetUpLine(object)` gives `*I see the user get up from the bar counter*`
- [X] T029 [P] [US4] Write `tests/Unit/PlayerPostures.test.js`: `postureView({ spot, posture })` places the eye 0.72 m, 0.55 m and 0.28 m above the spot and 0.1, 0.45 and 0.7 m opposite its facing for sitting, reclining and lying; returns base pitch 0, 20° and 55° and the yaw and pitch limits from [research R6](research.md); `clampLook({ yaw, pitch }, view)` clamps around the spot's facing, including across the ±π wrap
- [X] T030 [P] [US4] Write `tests/Unit/PlayerActivities.test.js`: `nearestFreeSpot(object, activityId, occupiedSpots, foot)` returns the nearest spot offering the activity whose holder is absent or `'user'`, and null when all are taken; `activityKind(activity, fromZone)` returns `resting` for sitting, lying and reclining postures, `standing` otherwise and `zone` for zone activities
- [X] T031 [P] [US4] Write `tests/Unit/Onlookers.test.js` with a stubbed `hasLineOfSight`: `selectOnlookers` includes a resident 10 m away facing the user; excludes one 10 m away facing away; includes one 3 m away facing away; excludes one 16 m away, one on another floor and one whose line of sight is blocked; excludes nobody for being the conversation partner (the caller routes her line separately)
- [X] T032 [P] [US4] Extend `tests/Feature/Api/ResidentWorldStatePromptTest.php`: posting a world message with `userState` gives `sentSystemPrompt()` containing `reclining on the Pool lounger` for `{ posture: 'reclining', spotId: 'pool-lounger-1-seat', activityId: 'recline' }`, `doing "Swim"` for `{ posture: 'standing', activityId: 'swim' }`, `, swimming` and `, crouching` for those postures, and no suffix for plain standing; an unknown `spotId` and an `activityId` not offered by the spot each return 422 naming the field; `userState` on a world without zones is accepted and ignored
- [X] T033 [P] [US4] Extend `tests/Feature/Api/ResidentDecisionTest.php`: a decision request with `userState` reclining on `pool-lounger-1-seat` puts the reclining phrase in `sentSystemPrompt()`, and an unknown `spotId` returns 422; with eight messages in her session conversation (including an assistant message `*I see the user sit down at the bar counter*`), `sentSystemPrompt()` contains a `recent conversation` section listing only the last 6, oldest first, as `you: …` for assistant messages and `the user: …` for user messages, each cut to 300 characters; with no messages the section is absent
- [X] T034 [P] [US4] Extend `tests/Feature/Api/ResidentWorldToolsTest.php`: a world chat message with `occupiedSpots: ['pool-lounger-1-seat']` and `fakeTurn(toolCallResponse('call_1', 'use', ['spot' => 'pool-lounger-1-seat', 'activity' => 'recline']), finalAnswerResponse(...))` returns the `spot taken` tool error to her and no `action` in the response
- [X] T035 [P] [US4] Create `tests/Feature/Api/ResidentObservationTest.php` via `php artisan make:test --pest Api/ResidentObservationTest --no-interaction`: posting `{ line: '*I see the user sit down at the bar counter*' }` returns 201 with `messageId` and stores an `assistant` message with that content in the resident's conversation for that session, creating the conversation when missing and reusing it when present; posting two lines in a row stores two messages, in order; no model request is made (`Http::fake()` then `Http::assertNothingSent()`); another user's session returns 404; a resident of another world returns 404; a missing `line` and one over 500 characters return 422

### Backend for User Story 4

- [X] T036 [US4] Create `app/Actions/ResolveUserActivity.php` via `php artisan make:class Actions/ResolveUserActivity --no-interaction`: `handle(World $world, ?array $userState): ?array` returns null without zones or state, otherwise `{ posture, spot, object, activity }` resolved from `$world->layout`; throws `ValidationException::withMessages()` on `userState.spotId` when the spot is unknown and on `userState.activityId` when it belongs neither to the spot nor, without a spot, to any zone; add `rules(): array` returning the shared rules for `userState`, `userState.posture` (`in:standing,crouching,sitting,lying,reclining,swimming`), `userState.spotId` and `userState.activityId` (nullable string, max 100)
- [X] T037 [US4] In `app/Actions/BuildResidentWorldPrompt.php`, add a `?array $userActivity = null` parameter to `worldState()` and append its phrase to `the user is` per the table in [contracts/world-requests.md](contracts/world-requests.md) (`, reclining on the Pool loungers`, `, doing "Make coffee" at the Back counter`, `, doing "Look out at the city"`, `, swimming`, `, crouching`, nothing for standing); and add `recentConversation(Conversation $conversation): ?string` returning the last 6 messages of the conversation, oldest first, each as `you: <content>` (assistant) or `the user: <content>` (user), content cut to 300 characters, or null when there are none ([research R14](research.md))
- [X] T038 [US4] In `app/Actions/AppendWorldConversationContext.php`, accept `?array $userActivity = null` in `handle()` and pass it to `worldState()`
- [X] T039 [US4] In `app/Http/Controllers/Api/ConversationController.php` `sendMessage()`: merge `ResolveUserActivity::rules()` and `occupiedSpots` (`nullable|array`, `occupiedSpots.*` string max 100) into the validation; once `$world` is resolved, resolve `userState` with `ResolveUserActivity` and pass it to `AppendWorldConversationContext::handle()`; pass `$validated['occupiedSpots'] ?? []` as the `occupiedSpots` argument of `new WorldToolbox(...)`
- [X] T040 [US4] In `app/Http/Requests/StoreResidentDecisionRequest.php` add `ResolveUserActivity::rules()`, and in `app/Http/Controllers/Api/ResidentDecisionController.php` resolve `userState` and pass it into the world state it builds, and append `recentConversation($conversation)` to the prompt as a `recent conversation` section (after `recent activity`, before `next step`) when it is not null
- [X] T041 [US4] Create `app/Http/Requests/StoreResidentObservationRequest.php` (`php artisan make:request StoreResidentObservationRequest --no-interaction`; `line` required string max 500) and `app/Http/Controllers/Api/ResidentObservationController.php` (`php artisan make:controller Api/ResidentObservationController --no-interaction`) whose `store()` resolves world, session and resident exactly as `ResidentDecisionController` does, finds or creates the resident's conversation with `firstOrCreate(['world_session_id' => $session->id], ['title' => 'New conversation'])`, creates an `assistant` message with the line (a new message on every call) and returns `201 { messageId }`; register `POST /worlds/{world}/sessions/{session}/residents/{resident}/observations` named `worlds.sessions.residents.observations.store` in `routes/api.php` beside the decisions route

### Client for User Story 4

- [X] T042 [P] [US4] Create `resources/js/components/world/activityLines.js` with `actionLine({ activity, object })`, `getUpLine(object)`, `joinLines(lines)`, `observationLine({ activity, object })` and `observationGetUpLine(object)` per [research R8](research.md) and T028 (third-person conjugation of the first word: `have → has`, `-s/-sh/-ch/-x/-z/-o → +es`, consonant + `y → ies`, otherwise `+s`; lowercase object names; `at the <object>` added unless the object's name already appears in the activity name)
- [X] T043 [P] [US4] Create `resources/js/components/world/playerPostures.js` with `postureView({ spot, posture })` and `clampLook(look, view)` per [research R6](research.md) and T029
- [X] T044 [P] [US4] Create `resources/js/components/world/playerActivities.js` with `nearestFreeSpot(...)` and `activityKind(...)` per T030
- [X] T045 [P] [US4] Add `hasLineOfSight(from, to)` to `WorldCollision` in `resources/js/components/world/collisionCheck.js` (a `Ray` from `from` toward `to` against `this.octree` with `rayIntersect`, true when there is no hit or the hit is farther than the target), and create `resources/js/components/world/onlookers.js` exporting `selectOnlookers({ residents, userEye, userFloorId, floorOf, residentPose, hasLineOfSight })` per [research R13](research.md) and T031 (15 m, same floor, line of sight from 1.5 m above her feet to the user's eye, within 4 m or facing within 110°)
- [X] T046 [US4] Extend `resources/js/components/world/FirstPersonController.jsx` with a `playerCommands` ref prop exposing `settleOnSpot({ spot, posture }) → Promise`, `getUp() → Promise` and `faceToward(point) → Promise`: settling glides eye position, yaw and pitch to `postureView` over 0.8 s with ease-in-out, then clamps mouse look with `clampLook`; getting up glides back to `spot.approach` at standing eye height and restores free look; while on a spot, `KeyW/A/S/D`, `Space` and Shift call a new `onGetUpIntent()` prop instead of moving, and crouch and swimming are suspended; a movement key during a standing activity calls a new `onMoveIntent()` prop; keep `playerState.posture`, `spotId`, `activityId` and `approach` current, and make `footPosition` the approach point while on a spot
- [X] T047 [P] [US4] Create `resources/js/components/world/hud/ActivityProgress.jsx`: centre-screen SVG ring whose stroke fills over the given duration with a glowing head dot and a soft outer halo, the activity name beneath it in `.world-hud-label`, scaling in on start and bursting outward briefly when full; calls `onComplete` when full; unmounting cancels it
- [X] T048 [P] [US4] Create `resources/js/components/world/hud/ActionLine.jsx`: bottom-centre stack above the posture hint showing action lines in italics with `hud-type-in` over 400 ms, held 4 s and faded out, and notices (for example `NO FREE SEAT — ALL 8 ARE TAKEN`) in the warning colour held 3 s; several entries stack and leave independently
- [X] T049 [US4] In `resources/js/components/world/WorldChat.jsx`, accept an `actionSender` ref prop and set `actionSender.current = (line) => …`, which sends the line through the hook's `sendMessage` or, while `isLoading`, queues it and sends the queued lines joined with `joinLines` once the reply arrives; extend `extraParams` with `get userState() { return getUserState(); }` and `get occupiedSpots() { … }` (spot ids held by anyone other than this resident), with `getUserState` and `getOccupiedSpots` as new props
- [X] T050 [US4] In `resources/js/hooks/useResidentAgency.js`, accept `getUserState` and include `userState: getUserState()` in each decision request body
- [X] T051 [US4] In `resources/js/pages/WorldPage.jsx`, wire activities: `Enter` with a card open starts the highlighted row (ignored while typing); a resting activity picks `nearestFreeSpot`, shows a notice when none is free, claims it as `occupiedSpots.current.set(spot.id, 'user')`, calls `playerCommands.current.settleOnSpot`, and on `onGetUpIntent` calls `getUp()` and releases the spot; a standing activity claims the spot, calls `faceToward`, shows `ActivityProgress` for 3 s and on completion releases it and emits the line, while `onMoveIntent` cancels it with no line; a zone activity does the same without claiming or turning; closing the card on start; no private-zone check applies to the user, since privacy restricts residents only (FR-030); `PostureHint` shows `SPACE — GET UP` while on a spot; pass `getUserState` to `WorldChat` and `useResidentAgency`, and `getOccupiedSpots` to `WorldChat`
- [X] T052 [US4] In `resources/js/pages/WorldPage.jsx`, add `deliverActivity({ line, observation })`: show `line` with `ActionLine`, pick onlookers with `selectOnlookers` (floors via `floorAt`, resident facing from `residentCommands.current.get(id).state().rotation.y`, line of sight via the collision world exposed from `WorldScene`), send `line` to the open conversation's resident through `actionSender.current` only when she is among the onlookers (nothing when she cannot see the user), and `POST` `observation` to `worlds.sessions.residents.observations.store` for every other onlooker (only with a session), logging and toasting `Could not tell <name> what you did (<error>)` on failure; call it on settling (`actionLine` / `observationLine`), on getting up (`getUpLine` / `observationGetUpLine`) and on completing a standing or zone activity
- [X] T053 [US4] In `resources/js/pages/WorldPage.jsx`, make leaving safe: on exit and unmount, release any spot held by `'user'`; the saved session position is already the approach point plus eye height through T004 and T046

**Checkpoint**: US4 works with US3's cards; the user can do everything a resident can at a spot, and residents who saw it know.

---

## Phase 7: User Story 5 - Residents face the user while talking (Priority: P5)

**Goal**: During a conversation she turns toward the user whenever she is standing still or treading water, and keeps a held seat's direction.

**Independent Test**: Open a chat with a resident from behind, circle her while talking, ask for a pose, send her to the bar and watch her turn back on arrival; a seated resident keeps facing the counter.

- [X] T054 [P] [US5] Extend `tests/Unit/ResidentMotion.test.js` for a new `shouldFaceUser({ inConversation, routing, placing, restingOnSpot, wandering })` returning true only in conversation with none of the others active, and for `headingToward(from, to)` matching `facingAngleForMovement(to.x - from.x, to.z - from.z)`
- [X] T055 [P] [US5] Add `shouldFaceUser` and `headingToward` to `resources/js/components/world/residentMotion.js`
- [X] T056 [US5] In `resources/js/components/world/ResidentController.jsx`'s frame loop, when `shouldFaceUser(...)` holds (conversation open; no active route, placement, resting spot or wander; standing or treading water), turn `vrm.current.scene.rotation.y` toward `headingToward(her position, the player's foot position)` with `turnTowardsAngle` at `TURN_SPEED`, leaving pose playback untouched; take the player's foot position from `playerPosition` minus `PLAYER_EYE_HEIGHT` on y (only x and z are used)

**Checkpoint**: All stories work.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T057 [P] Update `docs/architecture/06-avatar-and-world.md` with the player's movement modes, postures, object discovery, action lines and onlookers, and the residents' conversation facing; add the observations route to the API table in `docs/architecture/02-conversation-runtime.md` if it lists world routes
- [X] T058 Review every new HUD component against [contracts/player-controls-and-hud.md](contracts/player-controls-and-hud.md): no overlap with the conversation panel, map or each other; every key shown where it applies; token-only colours; entrance and exit animations; reduced-motion fallbacks
- [X] T059 Walk through [quickstart.md](quickstart.md) in the penthouse, The Index, the faire and the Connection Node, under all four themes and with reduced motion on (manual, by the user)
- [X] T060 Run the gates once, right before the pull request: `vendor/bin/pint --dirty --format agent`, `npm run lint`, `php artisan test --compact`, and `node --test tests/Unit/*.test.js`; fix whatever surfaces in that pass

---

## Phase 9: User Story 2 addition - Jumping (Priority: P2)

**Goal**: Space jumps onto tables, benches and counters; the user can step off ledges up to 2 m ([research R15](research.md)).

**Independent Test**: In The Index reading hall, jump onto a table, walk along it and step off; jump under a low shelf; walk to the penthouse terrace railing and confirm the edge still stops you.

- [X] T061 [P] [US2] Extend `tests/Unit/PlayerMotion.test.js` for `jumpVelocity`, `canJump` and `landingDip`, and `tests/Unit/WorldCollision.test.js` for walking off a ledge with and without `canFall`, drops over the 2 m limit, landing on a table, falling to the floor, a head bump under a low ceiling, and a jump stopped at the edge of the ground
- [X] T062 [P] [US2] Add `move(…, { canFall })` returning `'grounded'` or `'falling'`, and `airStep(position, velocity, seconds)` to `WorldCollision` in `resources/js/components/world/collisionCheck.js`; add `JUMP_HEIGHT`, `GRAVITY`, `jumpVelocity`, `canJump` and `landingDip` to `resources/js/components/world/playerMotion.js`; move the splash into `resources/js/components/world/worldSounds.js` beside new jump and landing sounds
- [X] T063 [US2] In `resources/js/components/world/FirstPersonController.jsx`, jump on Space (ignored while typing, swimming or seated; cancels a standing activity), run the airborne state with gravity and `airStep`, start falling when walking off a ledge, dip the view on landing, and ground the stand-up spot when an activity is chosen mid-jump
- [X] T064 [US2] Add `SPACE — JUMP` to `resources/js/components/world/hud/ControlsLegend.jsx`

---

## Phase 10: User Story 5 change - Facing the user only on opening and posing (Priority: P5)

**Goal**: She turns to the user when a conversation opens and each time she strikes a pose in it, and otherwise keeps her direction.

**Independent Test**: Open a chat from behind her, circle her (she keeps her direction), then ask for a pose (she turns to you first).

- [X] T065 [US5] Give `shouldFaceUser` in `resources/js/components/world/residentMotion.js` a `requested` flag and update `tests/Unit/ResidentMotion.test.js`; in `resources/js/components/world/ResidentController.jsx`, set a one-shot turn request when the conversation opens and when a pose is triggered for her during it, clear it when she faces the user within 0.03 rad or the conversation ends, and turn only while it stands

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: none.
- **Foundational (Phase 2)**: after Setup; blocks every story.
- **US1 (Phase 3)**, **US2 (Phase 4)**, **US5 (Phase 7)**: after Foundational; independent of each other.
- **US3 (Phase 5)**: after Foundational; reuses `floorAt` from US1's `worldLocation.js` (T008).
- **US4 (Phase 6)**: after US3 (activities start from its cards) and US2 (crouch and swimming states feed `userState`).
- **Polish (Phase 8)**: after the stories being shipped.

### Within Each Story

- Tests and pure modules first ([P]), then the canvas components, then the `WorldScene`/`WorldPage` wiring, which touch shared files and run in order.
- Backend in US4: T036 before T037–T041; T037 before T038 and T040; T038 before T039.

### Parallel Opportunities

- T001 and T002.
- US1: T007, T008, T010, T011.
- US2: T014, T015, T017, T018.
- US3: T020, T021, T023, T024, T025.
- US4: tests T028–T035 together; then T042–T045, T047, T048 alongside the backend chain T036–T041.
- US5 (T054, T055) can run alongside any other story.

---

## Parallel Example: User Story 4

```bash
# Tests together:
Task: "Write tests/Unit/ActivityLines.test.js"
Task: "Write tests/Unit/PlayerPostures.test.js"
Task: "Write tests/Unit/Onlookers.test.js"
Task: "Create tests/Feature/Api/ResidentObservationTest.php"

# Pure modules and HUD pieces together:
Task: "Create resources/js/components/world/activityLines.js"
Task: "Create resources/js/components/world/playerPostures.js"
Task: "Create resources/js/components/world/hud/ActivityProgress.jsx"
Task: "Create resources/js/components/world/hud/ActionLine.jsx"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 and Phase 2.
2. Phase 3 (US1): title cards and readout, which also prove the HUD style base.
3. Stop and walk the penthouse and The Index.

### Incremental Delivery

1. US1 → location.
2. US2 → running, crouching, swimming.
3. US3 → discovery and cards.
4. US4 → doing activities, residents knowing, onlookers.
5. US5 → facing the user (small; can ship with any of the above).

---

## Notes

- `occupiedSpots` holders are resident ids or `'user'`; resident code already treats any other holder as taken.
- Every new key handler checks `isTypingTarget(event.target)` first.
- Per-frame values go into refs and element styles; React state changes only on focus, zone, movement mode, card or activity changes.
