# Implementation Plan: Creator Menu

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

## Technical Context

**Language/Version**: PHP 8.4; JavaScript (React 19, JSX)

**Primary Dependencies**: Laravel 13, Pest 4, Ziggy 2, three.js through the existing world scene. No new dependencies.

**Storage**: PostgreSQL in tests, MySQL locally. Two new columns on `world_sessions` ([data-model.md](data-model.md)); no new tables.

**Testing**:
- **Pest feature tests** for unlocking and the guard, travel, residents, sentiments, quests, facts, credits, items, passable objects and creator activity use, with factories and the LLM faked. Existing creator-tool tests (`QuestCreatorModeTest`, `QuestWorldToolsTest`, `InventoryWorldToolsTest`, `FactWorldToolsTest`) must keep passing unchanged.
- **Node unit tests** (`node --test`) for `zoneLanding.js` and the flying step in `playerMotion.js`.
- **Manually**, following [quickstart.md](quickstart.md): the menu, flying, the overlay and summoning in 3D.

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

resources/js/
├── components/world/
│   ├── hud/CreatorMenu.jsx
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

## Complexity Tracking

None.
