# Implementation Plan: Player World Interaction

**Branch**: `016-player-world-interaction` | **Date**: 2026-09-24 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/016-player-world-interaction/spec.md`

## Summary

The user gains in the world what residents already have: knowing where they are, moving freely, discovering what things are, and using them. Residents also turn to face the user while they talk.

- **Location**: a client port of the server's zone lookup tracks the user's zone. Crossings show an animated title card, and a readout above the minimap always shows the current zone.
- **Movement**: Shift runs. In deep water the body keeps moving along the pool floor through the existing collision world, and only the view floats at the surface. The thresholds are the residents' own.
- **Discovery**: objects are marker points with no mesh of their own, so interactivity is drawn at the points: a faint dot at nearby objects, and on focus a glowing ring at each spot with a light column and a label. E opens a card with the object's description and activities, including which spots are free or taken by whom. G does the same for the zone.
- **Using things**: choosing a resting activity glides the view onto the spot at that posture's eye position, with limited look-around. Standing and zone activities run a 3-second progress ring and end with an action line. The user claims spots in the same occupancy map residents use.
- **Residents**: chat messages and idle decisions carry the user's state and the occupancy list, so she knows what the user is doing and cannot take their spot. While a conversation is open, the user's activity changes are sent as `*action lines*` she replies to, and she turns to face the user whenever she is standing still or treading water.
- **Interface**: DOM overlays share one animated, theme-token HUD language, with a reduced-motion fallback.

## Technical Context

**Language/Version**: PHP 8.4; JavaScript (React 19, JSX)

**Primary Dependencies**: Laravel 13, Pest 4; three.js, @react-three/fiber, @pixiv/three-vrm (existing). No new dependencies; the splash sound is synthesised with Web Audio.

**Storage**: No schema changes. User state is held on the world page and sent with requests ([data-model.md](data-model.md)).

**Testing**: Pest feature tests for the request fields and prompt text. `node --test` unit tests for the pure client logic (zone lookup, movement mode, focus, action lines, posture views), following `tests/Unit/*.test.js`. Visuals, motion and themes are verified manually per [quickstart.md](quickstart.md).

**Target Platform**: Desktop browsers via the existing SPA.

**Project Type**: Web application (Laravel backend + React frontend, single repo).

**Performance Goals**: Title card within 0.5 s of a crossing (SC-001). 60 fps kept with beacons, overlays and five residents. Per-frame work (focus, label placement, location every 150 ms) avoids React renders, which happen only when focus, zone or activity changes.

**Constraints**: Works for every marked world with no changes to environment files or the marker contract. All keys are inert while typing. Every element is themed by tokens only and honours reduced motion.

**Scale/Scope**: Up to about 64 objects and 30 zones per world (The Index). About 4 backend files and tests changed; about 10 new and 8 changed frontend modules.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Lint-Enforced Code Style**: Pint and ESLint run once at push or PR time per CLAUDE.md. PASS.
- **II. Append-Only Migrations**: No migrations. PASS.
- **III. Comments Justify Only Non-Obvious Decisions**: Comments only where the reason is hidden, for example why the swimming body stays on the pool floor, or why spots are drawn instead of meshes. PASS.
- **IV. Data Isolation by Ownership**: `userState` and `occupiedSpots` are validated against the layout of a world already resolved through `$request->user()->worlds()` and the session through `WorldUser`. No new data is stored or exposed. PASS.
- **V. Errors Fail Loudly**: Unknown spot or activity ids return 422. Failed action-line sends surface through the chat's existing error toast. A spot that disappears while held releases it with a notice. No swallowed errors. PASS.
- **VI. Feature-Test-First, Factory-Backed**: Feature tests extend `ResidentWorldStatePromptTest`, `ResidentDecisionTest` and `ResidentWorldToolsTest` using `WorldFactory::withLayout()`. Client-only logic uses node unit tests, the established exception. PASS.
- **VII. No Speculative Abstraction**: The user reuses the residents' occupancy map, swim thresholds, turning helpers and chat send path. `themeColor()` and the swim constants move to shared modules because each now has a second caller. The user's state is not persisted, since nothing needs it. PASS.
- **VIII. State Derivation During Render**: Zone, focus and activity changes arrive as callbacks from the canvas and set state there. Derived values (card contents, availability, readout text) are computed during render. The title card timer effect only schedules its own dismissal. PASS.

No violations; Complexity Tracking is not needed.

**Post-design re-check**: The contracts add two validated request fields and no storage, and the HUD uses only theme tokens. PASS.

## Project Structure

### Documentation (this feature)

```text
specs/016-player-world-interaction/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── world-requests.md
│   └── player-controls-and-hud.md
└── tasks.md             # created by /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Actions/
│   ├── BuildResidentWorldPrompt.php        # changed: user activity phrase in "the user is"
│   └── AppendWorldConversationContext.php  # changed: passes userState through
└── Http/
    ├── Controllers/Api/
    │   ├── ConversationController.php      # changed: validates userState and occupiedSpots; passes occupiedSpots to WorldToolbox
    │   └── ResidentDecisionController.php  # changed: passes userState to the world state
    └── Requests/
        └── StoreResidentDecisionRequest.php # changed: userState rules

resources/css/app.css                       # changed: world HUD styles and keyframes, reduced-motion overrides

resources/js/
├── pages/WorldPage.jsx                     # changed: player state, keys (E, G, 1–9), HUD mounting, action line queue
├── hooks/useResidentAgency.js              # changed: sends userState with decisions
├── utils/themeColor.js                     # new: moved from NameTags.jsx (second caller)
└── components/world/
    ├── worldLocation.js                    # new: floorAt, zoneAt, zoneChain (port of ResolveWorldState), crossing debounce
    ├── playerMotion.js                     # new: movement mode, speeds, swim view height and bob
    ├── playerPostures.js                   # new: eye position, facing, pitch and look limits per posture
    ├── objectFocus.js                      # new: reach and gaze selection
    ├── activityLines.js                    # new: action line wording
    ├── collisionCheck.js                   # changed: exports SWIM_DEPTH, LEAVE_WATER_DEPTH
    ├── FirstPersonController.jsx           # changed: run, swim view, spot glide, look limits, get up, foot position
    ├── WorldScene.jsx                      # changed: mounts LocationTracker, FocusTracker, SpotBeacons; foot-based PlayerViewTracker
    ├── LocationTracker.jsx                 # new: reports zone and floor changes
    ├── FocusTracker.jsx                    # new: reports the focused object; positions the focus label
    ├── SpotBeacons.jsx                     # new: rings, light column, nearby dots (shader, additive)
    ├── ResidentController.jsx              # changed: faces the user during conversation; shared swim constants
    ├── NameTags.jsx                        # changed: imports themeColor
    ├── WorldChat.jsx                       # changed: exposes sendAction; sends userState and occupiedSpots
    └── hud/
        ├── ZoneTitleCard.jsx               # new
        ├── LocationReadout.jsx             # new
        ├── FocusPrompt.jsx                 # new
        ├── InspectCard.jsx                 # new: object and zone cards
        ├── ActivityProgress.jsx            # new
        ├── ActionLine.jsx                  # new: action lines and notices
        ├── PostureHint.jsx                 # new
        └── SwimOverlay.jsx                 # new: caustics, tint, splash

tests/
├── Feature/Api/
│   ├── ResidentWorldStatePromptTest.php    # extended: userState phrasing, 422 on unknown ids
│   ├── ResidentDecisionTest.php            # extended: userState in decisions
│   └── ResidentWorldToolsTest.php          # extended: occupiedSpots from chat rejects a taken spot
└── Unit/
    ├── WorldLocation.test.js
    ├── PlayerMotion.test.js
    ├── PlayerPostures.test.js
    ├── ObjectFocus.test.js
    └── ActivityLines.test.js
```

**Structure Decision**: The existing single-repo layout. The HUD components get a `hud/` folder under `components/world/`, since there are eight of them and they share one visual language. No new base folders.

## Delivery Slices

Each story ships on its own, in order:

1. **Know where I am (P1)**: `worldLocation.js`, `LocationTracker`, the title card, the readout, and the HUD styles every later slice reuses.
2. **Run and swim (P2)**: `playerMotion.js`, controller changes, shared swim constants, the swim overlay, and foot-based position reporting.
3. **See what the world holds (P3)**: `objectFocus.js`, `FocusTracker`, `SpotBeacons`, the focus prompt, and object and zone cards with live availability.
4. **Do what residents do (P4)**: `playerPostures.js`, spot glide and get up, standing activities with the progress ring, `activityLines.js`, user occupancy, `userState` and `occupiedSpots` on the server, and action lines in conversation.
5. **Face the user (P5)**: the `ResidentController` heading rule.

## Complexity Tracking

Not applicable; no constitution violations.
