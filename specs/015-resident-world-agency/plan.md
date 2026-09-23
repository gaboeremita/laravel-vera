# Implementation Plan: Resident World Agency

**Branch**: `015-resident-world-agency` | **Date**: 2026-09-23 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/015-resident-world-agency/spec.md`

## Summary

Residents gain knowledge of the world, purposeful movement, held poses at furniture, and self-chosen activities, and users gain walk-and-talk conversations, voice in the world, name tags and a per-floor map.

- **World knowledge**: environment files carry floor, zone, object and spot markers in glTF node `extras`. They are parsed on upload into a `layout` JSON column on `worlds`. The server resolves positions sent by the world page into floors and zones, and adds a world-state block and a recent-activity block to the resident's prompt.
- **Actions**: residents act through `[action: …]` tags next to the existing `[pose: …]` tags. The world page executes them with A* route-finding over a walkable grid built from the existing collision system, plays the assistant's existing walk and default poses, and holds poses at spots. Every action's outcome is recorded as a resident activity.
- **Self-chosen activities**: the world page drives them one step at a time: after a random 10–60 second wait it asks the server for one decision, executes it, and reports the outcome with the next request. The loop exists only while the world page is open and visible, which satisfies "nothing happens while the user is away" without server-side presence tracking.
- **Talking and finding residents**: conversations stop pausing the world, voice mode is reused from the web chat with positional playback, and name tags plus a top-down map rendered per floor make residents easy to find.

## Technical Context

**Language/Version**: PHP 8.4; JavaScript (React 19, JSX)

**Primary Dependencies**: Laravel 13, Sanctum, Pest 4; three.js 0.185, @react-three/fiber 9, @pixiv/three-vrm and three-vrm-animation, @ricky0123/vad-web (existing voice mode). No new dependencies.

**Storage**: PostgreSQL. New columns `worlds.layout` and `poses.posture` (with its unique index widened to include posture), two new tables (`world_session_residents`, `resident_activities`), one enum value.

**Testing**: Pest feature tests (backend, factory-backed); `node --test` unit tests for client navigation logic, following `tests/Unit/WorldCollision.test.js`. Rendering, animation, voice and map visuals are verified manually per [quickstart.md](quickstart.md).

**Target Platform**: Desktop browsers via the existing SPA; Laravel Herd locally.

**Project Type**: Web application (Laravel backend + React frontend, single repo).

**Performance Goals**: Walkable grid built during the existing loading screen in under 2 s for the penthouse; route queries under 20 ms; residents start moving within 2 s of agreeing (SC-003); 60 fps with five residents, name tags and minimap.

**Constraints**: Nothing runs while the world page is closed or hidden (FR-031). Idle decisions use the resident's own model at one call per 10–60 s per autonomous resident, with an 8-second server floor. Markers come only from environment files.

**Scale/Scope**: Worlds up to roughly 50 × 50 m per floor, a handful of floors, up to about ten residents. About 20 backend files (actions, controllers, requests, resources, migrations, factories, tests) and about 15 frontend modules.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Lint-Enforced Code Style**: Pint and ESLint run once at push or PR time per CLAUDE.md. PASS (not a plan-time gate).
- **II. Append-Only Migrations**: New migrations add `worlds.layout`, add `poses.posture` and replace the poses unique index, create `world_session_residents` and `resident_activities`. The behavior enum is a PHP enum over a string column, so adding `autonomous` needs no schema change. No existing migration is edited. PASS.
- **III. Comments Justify Only Non-Obvious Decisions**: Only non-obvious constraints get comments, for example why spots have separate approach points. PASS.
- **IV. Data Isolation by Ownership**: Every new route resolves `{world}` through the requester's `WorldUser` and `{session}` through that membership's sessions, then checks the resident belongs to the world. Activities and resident states are only reachable through that chain. The layout is returned only with a world the requester belongs to. PASS.
- **V. Errors Fail Loudly**: Invalid markers produce warnings in the upload response. Invalid or unknown action tags produce `failed` outcomes with reasons. Decision failures return error responses and log. No swallowed exceptions. PASS.
- **VI. Feature-Test-First, Factory-Backed**: Feature tests cover marker parsing through the upload endpoint, zone resolution and prompt content through the message endpoint, the decision and activity endpoints, rate limiting and cross-user isolation. New factories: `WorldSessionResidentFactory`, `ResidentActivityFactory`, and `WorldFactory::withLayout()`. Grid and A* logic has no server counterpart and gets node unit tests, the established exception for client-only logic. PASS.
- **VII. No Speculative Abstraction**: The layout is one JSON column rather than four tables because nothing queries it by row. Idle steps reuse the tag parser and the existing chat model path rather than introducing a tool framework. The client loop is one hook, not a general scheduler. PASS.

No violations; Complexity Tracking is not needed.

**Post-design re-check**: The design in [data-model.md](data-model.md) and [contracts/](contracts/) keeps every new table reachable only through `WorldUser → WorldSession` and adds no dependencies. PASS.

## Project Structure

### Documentation (this feature)

```text
specs/015-resident-world-agency/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── environment-markers.md
│   ├── action-tags.md
│   └── world-agency-api.md
└── tasks.md             # created by /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Actions/
│   ├── ParseEnvironmentLayout.php          # new: GLB JSON chunk → layout + warnings
│   ├── ResolveWorldState.php               # new: positions → floors, zones, distances
│   ├── BuildResidentWorldPrompt.php        # new: world-state + recent-activity + available-activities text
│   └── AppendWorldConversationContext.php  # changed: include world state when positions are given
├── Enums/
│   └── WorldResidentBehavior.php           # changed: Autonomous
├── Models/
│   ├── World.php                           # changed: layout cast
│   ├── WorldSessionResident.php            # new
│   └── ResidentActivity.php                # new
├── Services/
│   └── LlmResponseTagParser.php            # changed: parse [action: …]
└── Http/
    ├── Controllers/Api/
    │   ├── WorldController.php             # changed: parse markers on upload, return warnings
    │   ├── ConversationController.php      # changed: positions + action in sendMessage
    │   ├── ResidentDecisionController.php  # new: idle decisions
    │   ├── ResidentActivityController.php  # new: record requested actions, report outcomes
    │   ├── ResidentStateController.php     # new: save resident state
    │   └── WorldSessionController.php      # changed: residentStates in index
    ├── Requests/                           # new form requests for the above
    └── Resources/
        └── WorldResource.php               # changed: layout

database/
├── factories/ (WorldSessionResidentFactory, ResidentActivityFactory, WorldFactory::withLayout)
└── migrations/ (add layout to worlds, add posture to poses and widen its unique index, create world_session_residents, create resident_activities)

routes/api.php                              # decisions, activities, state routes under worlds/{world}/sessions/{session}/residents/{resident}

resources/js/
├── pages/WorldPage.jsx                     # changed: world never paused by chat; hosts overlays and the agency loop
├── components/WorldForm.jsx                # changed: shows layout warnings after upload
├── components/ (assistant pose editor)      # changed: one section per posture, each with its default pose
├── components/world/
│   ├── WorldScene.jsx                      # changed: navigation grid, name tags, positional audio listener
│   ├── ResidentController.jsx              # changed: existing locomotion cycle follows routes; held poses; follow
│   ├── WorldChat.jsx                       # changed: overlay, focus rules, voice mode, distance limit, close control
│   ├── worldNavigation.js                  # new: walkable grid + A* + smoothing
│   ├── residentActions.js                  # new: executes go_to, use, zone, follow, stop
│   ├── conversationRange.js                # new: conversation warning and end distances
│   ├── worldMapProjection.js               # new: map projection, floor lookup, label spreading
│   ├── WorldEnvironment.jsx                # changed: builds the navigation grid while loading
│   ├── FirstPersonController.jsx           # changed: ignores movement keys while typing
│   ├── InteractionSystem.jsx               # changed: C starts or closes the single conversation
│   ├── worldMotionPoses.js                 # changed: posture-aware pose and default lookup
│   ├── NameTags.jsx                        # new
│   ├── OffscreenIndicator.jsx              # new
│   ├── WorldMap.jsx                        # new: minimap + full map, per-floor images, markers
│   ├── floorMaps.js                        # new: per-floor top-down renders
│   └── ThoughtBubble.jsx                   # new
└── hooks/
    ├── useResidentAgency.js                # new: idle waits, decision requests, execution, outcomes, presence pause
    └── useVoiceMode.js                     # reused; playback routed to positional audio in the world

tests/
├── Feature/Api/
│   ├── WorldLayoutImportTest.php
│   ├── ResidentWorldStatePromptTest.php
│   ├── ResidentAgencyDecisionTest.php
│   ├── ResidentAgencyActivityTest.php
│   └── ResidentAgencyIsolationTest.php
└── Unit/
    └── WorldNavigation.test.js
```

Outside the repository: the penthouse generator is updated to emit markers for every floor, room, seat and lounger, and the connection node environment is re-exported with floor and zone markers.

**Structure Decision**: The existing single-repo layout (`app/`, `database/`, `routes/`, `resources/js/`, `tests/`), adding files only to existing directories.

## Delivery Slices

Each story is independently shippable. Suggested pull requests, in order:

1. **Markers and world awareness (P1)**: marker parser, `layout`, zone resolution, prompt world state, penthouse markers.
2. **Walk and talk (P2)**: chat overlay without pausing, focus rules, one conversation at a time, distance limit, voice mode with positional playback.
3. **Finding residents (P3)**: name tags, off-screen indicator, per-floor map and minimap.
4. **Going places (P4)**: walkable grid, A*, route following through the existing locomotion cycle, `go_to`, `follow`, `stop`, direct controls, activities and outcomes.
5. **Using things (P5)**: spots, approach and placement, held and one-shot poses, postures (posture-tagged poses, per-posture defaults, editor sections, standing up for standing-only poses), occupancy.
6. **Self-chosen activities (P6)**: `autonomous` behavior, decision endpoint, client loop, thought bubbles, presence pause, resident state save and restore.

## Complexity Tracking

Not applicable; no constitution violations.
