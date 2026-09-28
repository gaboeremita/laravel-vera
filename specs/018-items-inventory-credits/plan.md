# Implementation Plan: Items, Inventory and Credits

**Branch**: `142-items-inventory-credits` | **Date**: 2026-09-28 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/018-items-inventory-credits/spec.md`

## Summary

Worlds get items, and the player, residents and region objects get inventories of items and credits, configured per world and copied into each session.

- **Schema**: `items`, `starting_inventories` and their items for configuration; `inventories` and their items per session; `activity_terms`, `handover_requests`, `credit_transactions`; `worlds.narrator_model_id` ([data-model.md](data-model.md)).
- **Transfers**: one `TransferInventory` action moves items and credits between any two inventories under row locks and records credit transactions. Only player endpoints can take from the player's inventory (research R4, R5).
- **Residents**: `give` and `ask_for` world tools; their own inventory in their prompt; `use` respects activity terms (R6, R8, R13).
- **Player**: give from the conversation panel, answer handover requests, see vendors' goods, take from objects, use activities with terms, examine and use items, credit history. The server writes the line the character hears about each handover (R7, R9, R11, R12, R15).
- **Narrator**: one forced-tool LLM call judges plain-language requirements and narrates outcomes for activities and items, on the world's narrator model (R10).
- **Configuration UI**: items editor and player starting inventory on the World tab; starting inventory, credits and for-sale marks in the residents editor; object inventories and activity terms in the region form.

## Technical Context

**Language/Version**: PHP 8.4; JavaScript (React 19, JSX)

**Primary Dependencies**: Laravel 13, Pest 4, Ziggy 2; the existing agent loop and LLM providers. No new dependencies.

**Storage**: PostgreSQL in tests, MySQL locally. Eight new tables and one new column ([data-model.md](data-model.md)).

**Testing**:
- **Pest feature tests** for configuration endpoints, play endpoints, transfers, tools and the narrator, with factories and a faked LLM.
- **`node --test`** for the pure client logic that turns two inventories into change toasts.
- **Manually**, following [quickstart.md](quickstart.md): the HUD, conversation panel and configuration UI.

**Target Platform**: Desktop browsers via the existing SPA.

**Project Type**: Web application (Laravel backend + React frontend, single repo).

**Performance Goals**: Inventory changes show within 1 s (SC-003); transfers are single transactions. Narrated uses take one LLM call.

**Constraints**:
- Nothing leaves the player's inventory except through the three player paths (FR-010).
- Existing sessions and worlds keep working with empty inventories.
- Activity and object ids come from the environment layout and are never edited here.

**Scale/Scope**: Tens of items per world, a few dozen inventories per session.
- **Backend**: about 35 new files (migrations, models, factories, enums, actions, controllers, requests, tools) and about 10 changed.
- **Frontend**: about 10 new components and hooks, about 8 changed.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Lint-Enforced Code Style**: Pint and ESLint run once at push or PR time per CLAUDE.md. PASS.
- **II. Append-Only Migrations**: every schema change is a new migration. PASS.
- **III. Comments Justify Only Non-Obvious Decisions**: comments only for hidden constraints, such as why `null` means unlimited or why the handover line is written by the server. PASS.
- **IV. Data Isolation by Ownership**:
  - Everything is resolved through `$request->user()->worlds()`, then the world's sessions, residents, regions and items.
  - Items referenced anywhere must belong to the same world; the narrator model must be one of the user's.
  - Handover requests are resolved inside the session.
  - Feature tests cover another user's world, another world's item, and another session's request. PASS.
- **V. Errors Fail Loudly**:
  - Not enough items or credits returns 422 with the reason; answering an answered request returns 409; a missing narrator model returns 422.
  - A refused tool call returns the reason to the model.
  - The client shows a toast for every failure. PASS.
- **VI. Feature-Test-First, Factory-Backed**: factories for every new model, with states for unlimited stock, vendors and takeable objects. The toast diff uses a node unit test, the established exception. PASS.
- **VII. No Speculative Abstraction**:
  - One inventory shape serves three real holder kinds (research R1).
  - The narrator has two real callers (activities and items).
  - No generic "request anything" machinery: only credits and items get a request, because only they move. PASS.
- **VIII. State Derivation During Render**: the change toasts, the vendor panel and the activity rows are derived during render from the latest inventory and region data; fetching uses effect-local closures. PASS.

No violations; Complexity Tracking is not needed.

## Project Structure

### Documentation (this feature)

```text
specs/018-items-inventory-credits/
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
│   ├── TransferInventory.php          # new: locked transfer + credit transactions
│   ├── StockSession.php               # new: copy starting inventories into a session
│   ├── ResolveInventory.php           # new: a holder's session inventory, copied on first use
│   ├── UseActivity.php                # new: check terms, narrate, transfer
│   ├── Narrate.php                    # new: forced-tool narrator call
│   ├── DeleteItem.php                 # new: remove an item from terms and releases
│   ├── ReconcilePassages.php          # changed: also drops terms and object inventories of vanished objects
│   └── AppendWorldConversationContext.php  # changed: the resident's own inventory
├── Enums/InventoryHolder.php, HandoverRequestStatus.php
├── Http/Controllers/Api/
│   ├── ItemController.php, ItemImageController.php
│   ├── StartingInventoryController.php, ActivityTermsController.php
│   ├── InventoryController.php        # inventory, credit history, goods, take
│   ├── HandoverController.php         # give, answer requests
│   ├── ActivityUseController.php, ItemUseController.php
│   ├── ConversationController.php     # changed: give/ask_for tools, inventory in response
│   └── WorldSessionController.php     # changed: stock the session on start
├── Http/Requests/                     # one per new write endpoint
├── Models/Item.php, StartingInventory.php, StartingInventoryItem.php, Inventory.php, InventoryItem.php, ActivityTerms.php, HandoverRequest.php, CreditTransaction.php
└── Services/AgentLoop/Tools/World/
    ├── GiveTool.php, AskForTool.php, NarrateTool.php   # new
    ├── UseTool.php, WhereCanITool.php, WhatIsInTool.php  # changed: respect terms
    └── WorldToolbox.php               # changed: inventory tools
app/Actions/GenerateResidentConversationTurn.php  # changed: give between residents

database/migrations/                   # new tables + worlds.narrator_model_id
database/factories/                    # one per new model

resources/js/
├── components/
│   ├── ItemsEditor.jsx                # new: World tab
│   ├── StartingInventoryEditor.jsx    # new: player, residents, objects
│   ├── ActivityTermsEditor.jsx        # new: region form
│   ├── WorldForm.jsx, WorldResidentsEditor.jsx, RegionForm.jsx   # changed
│   ├── WorldChat.jsx                  # changed: give, goods, request prompt
│   └── world/
│       ├── inventoryChanges.js        # new: diff two inventories into toasts
│       └── hud/CreditsReadout.jsx, InventoryPanel.jsx, HandoverRequestConfirm.jsx, NarrationCard.jsx, InspectCard.jsx (changed)
├── hooks/useInventory.js              # new
├── hooks/usePlayerActivities.js       # changed: activity-uses before starting, take rows
└── pages/WorldPage.jsx                # changed: HUD pieces

routes/api.php                         # new routes per contracts/api.md

tests/Feature/Api/ItemControllerTest.php, StartingInventoryControllerTest.php, ActivityTermsControllerTest.php, InventoryPlayControllerTest.php
tests/Feature/TransferInventoryTest.php, InventoryWorldToolsTest.php, NarrateTest.php
tests/Unit/InventoryChanges.test.js
```

**Structure Decision**: The existing single Laravel + React repo layout; new code sits beside the world, region and resident code it extends.

## Names to approve

These names are new and are used in this plan's code, tables and UI until you approve or change them:

| Name | Where | Refers to |
|---|---|---|
| narrator | code, world setting label "Narrator model" | the LLM call that judges plain-language terms and narrates outcomes |
| handover, handover request | tables, endpoints | a transfer of items or credits; a resident asking the player for one |
| activity terms | table, UI section title | what an object's activity requires, costs and gives |
| takeable, "Can be taken" | column, checkbox label | an object's item the player can take directly |
| for sale, "For sale" | column, checkbox label | a vendor's item shown to the player |
| `give`, `ask_for`, `narrate` | LLM tool names | the tools in [contracts/api.md](contracts/api.md) |
| Examine, Use, Give, Take | HUD button labels | the player's actions on items, objects and characters |

## Complexity Tracking

Not needed.
