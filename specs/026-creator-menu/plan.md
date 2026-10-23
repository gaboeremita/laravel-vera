# Implementation Plan: Creator Menu and HUD Theming

**Branch**: `181-creator-menu` | **Date**: 2026-10-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/026-creator-menu/spec.md`

## Summary

T opens the Creator menu on the world page. Once unlocked with the creator password, it holds 23 powers over the session: moving the player, moving and controlling residents, sentiments, quests, credits and items, and object and fact state. The server side reuses the creator-mode tools' logic, moved into actions that the tools and the menu share, and the same `by_creator` logging. The page side adds the menu, a landing helper over the navigation grid, flying and faster walking, a zone overlay, and a check that keeps frozen residents out of resident agency.

- **Unlock**: `world_sessions.creator_mode_at`, set by password; a `ResolvesCreatorSession` trait guards every creator endpoint (research R1).
- **Shared logic**: quest and fact creator tools delegate to new `app/Actions/Creator/*` actions (R2).
- **Travel**: same-region jumps land by navigation grid and save through the existing position endpoint; other-region jumps go through `TravelAsCreator`, which mirrors passage travel with followers and quest announcements (R3).
- **Residents**: summon, send and home write or delete `world_session_residents` rows; freezing is `world_sessions.frozen_resident_ids` (R4, R5).
- **Everything else**: thin controllers over `Sentiment::adjust`, `TransferInventory`, `ResolveInventory`, `WorldSessionObject` and a cost-free `UseActivityAsCreator` (R6–R9).
- **Page**: `CreatorMenu` with five tabs, `zoneLanding.js`, flying and speed in `FirstPersonController`, `ZoneOverlay` (R10, R11).
- **HUD theming**: each theme file sets the HUD theme values; the `world-hud-*` classes and the new shared HUD classes read them, and HUD components drop their hard-coded glows, spacing and corners (R12, R13).

## Technical Context

**Language/Version**: PHP 8.4; JavaScript (React 19, JSX)

**Primary Dependencies**: Laravel 13, Pest 4, Ziggy 2, three.js through the existing world scene. No new dependencies.

**Storage**: PostgreSQL in tests, MySQL locally. Two new columns on `world_sessions` ([data-model.md](data-model.md)); no new tables.

**Testing**:
- **Pest feature tests** for unlocking and the guard, travel, residents, sentiments, quests, facts, credits, items, passable objects and creator activity use, with factories and the LLM faked. Existing creator-tool tests (`QuestCreatorModeTest`, `QuestWorldToolsTest`, `InventoryWorldToolsTest`, `FactWorldToolsTest`) must keep passing unchanged.
- **Node unit tests** (`node --test`) for `zoneLanding.js` and the flying step in `playerMotion.js`.
- **Manually**, following [quickstart.md](quickstart.md): the HUD in every theme, the menu, flying, the overlay and summoning in 3D.

**Target Platform**: Desktop browsers via the existing SPA.

**Project Type**: Web application (Laravel backend + React frontend, single repo).

**Performance Goals**:
- A same-region jump is instant once the menu closes; an other-region jump takes as long as passage travel (SC-001).
- A summoned resident appears on the next render after the request returns (SC-002).
- The overview is a handful of queries per world; it is fetched when the menu opens and after each change.

**Constraints**:
- Nothing the menu does changes the world's definition; every write is scoped to the session (spec Assumptions).
- Every creator endpoint answers 403 for a session that isn't unlocked (FR-005, SC-005).
- Data isolation: sessions resolve through the requesting user's `WorldUser`, and every resident, region, quest, fact and item id is looked up within that world (constitution IV).

**Scale/Scope**: One world with tens of regions, residents and quests; the overview is unpaginated.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status |
|-----------|--------|
| I. Lint-enforced style | Pint and ESLint run once, before the PR. |
| II. Append-only migrations | One new migration adds the two `world_sessions` columns. |
| III. Comments justify only non-obvious decisions | Docblocks on the new actions only where behaviour isn't plain from names. |
| IV. Data isolation | All lookups go through the session's world (R1, Constraints). |
| V. Errors fail loudly | Action `RuntimeException`s become 422 with their message; the page toasts every failed request. |
| VI. Feature-test-first, factory-backed | Pest feature tests per endpoint group with existing factories. |
| VII. No speculative abstraction | Extraction into actions happens because the menu is the second caller of each (R2); no generic "creator power" layer. |
| VIII. Render-time derivation | The menu derives tab data from the overview during render; fetching happens in effect-local closures. |

**Result**: Pass, before and after Phase 1.

## Project Structure

### Documentation (this feature)

```text
specs/026-creator-menu/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── api.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Actions/
│   ├── Creator/
│   │   ├── StartQuestAsCreator.php
│   │   ├── EndQuestAsCreator.php
│   │   ├── ResetQuestAsCreator.php
│   │   ├── SetQuestBeat.php
│   │   ├── SetQuestFlag.php
│   │   ├── SetFactKnown.php
│   │   ├── TravelAsCreator.php
│   │   └── UseActivityAsCreator.php
├── Http/Controllers/Api/
│   ├── CreatorUnlockController.php
│   ├── CreatorOverviewController.php
│   ├── CreatorTravelController.php
│   ├── CreatorResidentController.php
│   ├── CreatorQuestController.php
│   ├── CreatorFactController.php
│   ├── CreatorInventoryController.php
│   └── CreatorObjectController.php
├── Http/Controllers/Api/ResidentDecisionController.php   # refuses frozen residents
├── Http/Controllers/Api/WorldSessionController.php       # creatorUnlocked, frozenResidentIds
├── Models/WorldSession.php                               # casts, isFrozen()
├── Services/AgentLoop/Tools/World/{StartQuest,EndQuest,ResetQuest,SetBeat,SetQuestFlag,SetFactKnown}Tool.php  # delegate
└── Traits/ResolvesCreatorSession.php

database/migrations/xxxx_add_creator_columns_to_world_sessions_table.php
routes/api.php

resources/css/
├── app.css                           # HUD classes read the HUD theme values; new shared HUD classes and animations; reduced motion
└── themes/{terminal,default,grimoire,slate}.css   # HUD theme values

resources/js/
├── components/world/
│   ├── hud/CreatorMenu.jsx
│   ├── hud/HudControls.jsx           # shared button, switch, chip, meter, counter with their states
│   ├── hud/CreatorLog.jsx
│   ├── hud/CreatorArrivalFlash.jsx
│   ├── ArrivalRing.jsx
│   ├── hud/CreatorGoToTab.jsx
│   ├── hud/CreatorResidentsTab.jsx
│   ├── hud/CreatorQuestsTab.jsx
│   ├── hud/CreatorItemsTab.jsx
│   ├── hud/CreatorWorldTab.jsx
│   ├── ZoneOverlay.jsx
│   ├── zoneLanding.js
│   ├── FirstPersonController.jsx     # flying, speed multiplier
│   ├── playerMotion.js               # flying step
│   └── WorldScene.jsx                # overlay, landing hook-up
├── hooks/useCreatorMenu.js
├── hooks/useResidentAgency.js        # skips frozen residents
└── pages/WorldPage.jsx               # T key, pause, travel hand-off

tests/
├── Feature/Api/CreatorUnlockTest.php
├── Feature/Api/CreatorTravelTest.php
├── Feature/Api/CreatorResidentTest.php
├── Feature/Api/CreatorQuestTest.php
├── Feature/Api/CreatorInventoryTest.php
├── Feature/Api/CreatorWorldStateTest.php
├── Unit/ZoneLanding.test.js
└── Unit/PlayerMotion.test.js        # flying cases added
```

**Structure Decision**: Existing layout. Creator controllers sit flat in `Api/` with the `Creator` prefix, beside `CreatorPasswordController`; the new actions are grouped in `app/Actions/Creator/` next to the existing `Activities/` and `Quests/` groups; the menu's components sit in `hud/` with the other world panels.

## Visual Design

The approved mockup, built from the HUD's shared classes so each theme gives it that theme's HUD look and accent (research R12). Timings use the HUD's easing, `cubic-bezier(0.2, 0.8, 0.2, 1)`, unless noted.

### Layout

- **Locked**: centred `world-hud-panel` 30rem wide. "RESTRICTED LAYER" label, "CREATOR" title with glow and wide tracking, a drawn rule, one line of help, one cell per password character plus a blinking caret cell, "AUTHENTICATE" and "CANCEL" buttons, `T` hint.
- **Unlocked**: `world-hud-panel` up to 64rem × 88%.
  - **Header**: "CREATOR MENU · SESSION "<title>"", the "CREATOR" title, "WORLD PAUSED" and the `1–5`, `↑↓` and `T` hints, then a drawn rule.
  - **Body**: an 11rem rail on the left, the tab content on the right, scrolling on its own.
  - **Rail**: tabs numbered 1–5 (GO TO, RESIDENTS, QUESTS, ITEMS, WORLD), then the FLY, FAST WALK and SHOW ZONES switches at the bottom.
  - **Creator log**: under the content, the last three changes as "› <what happened>".
- **Go to**: region chips; a zone tree with resident counts and passages, ending with WORLD SPAWN; a map of zone outlines with the player as a pulsing dot and residents as small dots; "GO TO <ZONE>" button.
- **Residents**: list of rows with portrait, name, "REGION · ZONE", and FROZEN or NEAR chips. Detail: large portrait, glowing name, location and distance; THINKING box with the live thought and caret; FEELINGS TOWARD YOU meters; SUMMON (primary), SEND TO…, SEND HOME, FREEZE or UNFREEZE, INVENTORY; RECENT activities.
- **Quests**: list with status labels. Detail: glowing title, BEATS with diamonds, FLAGS chips with remove and "+ ADD FLAG", then COMPLETE (primary), FAIL (danger), REOPEN or START as the status allows.
- **Items**: YOUR CREDITS counter with −/+ steps and a custom amount; FROM holder picker and grid, ⇄, TO grid; item picker with GRAB and a quantity stepper.
- **World**: BLOCKERS with LOCKED or OPEN chips that toggle; SECRETS with HIDDEN or REVEALED chips that toggle; OBJECTS with each activity's responses and USE.

### HUD theme table

Values per theme (research R13), from the approved mockup. Labels and controls derive their own corners from `--hud-radius`.

| Value | Terminal | Default light | Default dark | Grimoire | Slate |
|-------|----------|---------------|--------------|----------|-------|
| `--hud-radius` | 0 | 16px | 16px | 18px | 8px |
| `--hud-chip-radius` | 0 | 999px | 999px | 999px | 4px |
| `--hud-corner-marks` | 1 | 0 | 0 | 0 | 0 |
| `--hud-scanlines` | 0.5 | 0 | 0 | 0 | 0 |
| `--hud-glow` | 1 | 0 | 0.4 | 0 | 0 |
| `--hud-shadow` | none | soft two-layer drop | deep drop | warm drop plus soft inner parchment glow | crisp drop |
| `--hud-border-style` | 1px solid | 1px solid | 1px solid | 3px double | 1px solid |
| `--hud-panel-opacity` | 80% | 88% | 82% | 94% | 96% |
| `--hud-label-transform` | uppercase | uppercase | uppercase | lowercase | uppercase |
| `--hud-label-tracking` | 0.16em | 0.08em | 0.08em | 0.1em | 0.06em |
| `--hud-label-variant` | normal | normal | normal | small-caps | normal |
| `--hud-label-size` | 0.62rem | 0.66rem | 0.66rem | 0.85rem | 0.64rem |
| `--hud-title-tracking` | 0.24em | -0.01em | -0.01em | 0.14em | 0 |
| `--hud-title-weight` | 600 | 700 | 700 | 600 | 600 |
| `--hud-primary-fill` | 0 | 1 | 0 | 1 | 1 |
| `--hud-avatar-radius` | 0 | 50% | 50% | 50% | 4px |
| `--hud-caret-width` | 0.45em | 2px | 2px | 1.5px | 2px |
| `--hud-caret-radius` | 0 | 1px | 1px | 1px | 0 |
| `--hud-caret-blink` | hard on/off, 1 s | fade, 1.1 s | fade, 1.1 s | fade, 1.6 s | fade, 1 s |

Rules in Grimoire draw as two thin lines; elsewhere as one gradient line.

### Motion

| Element | Trigger | Animation |
|---------|---------|-----------|
| Backdrop | open / close | fade in 300 ms / fade out 250 ms |
| Panel | open | scale 0.96→1 and fade, 350 ms; title wipes in; header rule draws |
| Panel | close | fade and scale to 0.98, 250 ms |
| Locked → unlocked | correct password | cells light up left to right (30 ms apart), then the lock panel wipes out and the menu scales in |
| Password cell | each character typed | fills with a pop (scale 1.15→1, 150 ms) and an inner glow |
| Lock panel | wrong password | horizontal shake (6 px, 400 ms), cells flash danger then clear, error line types in |
| Tab | hover | text brightens, border fades in, 150 ms |
| Tab | select | an indicator bar slides to it (250 ms); content cross-fades with rows rising 8 px, 40 ms apart |
| Row | hover / select | border tints and shifts 2 px right (150 ms); selected row gains the shimmer sweep |
| Button | hover | border and text to the accent, the theme's glow, 150 ms |
| Button | press | scale 0.97 with a brief inner flash, 100 ms |
| Button | busy | shimmer sweep across it and label dimmed, until the request settles |
| Button | success | a ring bursts outward from it (400 ms) and the creator log line types in |
| Button | failure | shake (4 px, 300 ms), border flashes danger, toast |
| Switch | toggle | knob slides 150 ms and its glow fades in; off fades the glow out |
| Chip | state change (FROZEN, LOCKED↔OPEN, HIDDEN↔REVEALED) | flips on its X axis (250 ms) with a color cross-fade; FROZEN adds a one-time frost pulse |
| Sentiment meter | hover a cell | cells up to it preview in a dim accent tint |
| Sentiment meter | set | cells fill or empty one by one from the centre (20 ms apart); the number counts to the new value |
| Beat diamond | finish | pops (scale 1.4→1) and pulses its glow once |
| Beat diamond | undo | it and the beats undone with it empty in sequence, 60 ms apart |
| Map zone | hover | fill and stroke brighten with a glow, mirrored in the tree, 150 ms |
| Map zone | click | a ring pulses out from its centroid, then the jump runs |
| Player dot | always | radius pulses, 1.6 s loop |
| Credits counter | change | counts to the new value over 400 ms; green flash up, danger flash down |
| Item card | moved between holders | flies from its old grid to its new one (FLIP, 300 ms) |
| Thought line | new thought | types in with a blinking caret |
| Creator log | new entry | types in at the top; older lines slide down and fade |
| World view | arrival after Go to | accent-tinted flash over the view, 300 ms |
| Resident | summoned or sent nearby | a glowing ring expands at their feet and fades, 600 ms |
| Zone overlay | Show zones on | outlines draw along their length (stroke dash, 600 ms); labels fade in |

With `prefers-reduced-motion`, every entry above becomes a 150 ms fade, and the loops (player dot, shimmer, caret, scanlines) stop.

## Complexity Tracking

None.
