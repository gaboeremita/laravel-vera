---
description: "Task list for Items, Inventory and Credits"
---

# Tasks: Items, Inventory and Credits

**Input**: Design documents from `/specs/018-items-inventory-credits/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/api.md](contracts/api.md), [quickstart.md](quickstart.md)

**Tests**: Included. Constitution VI requires factory-backed Pest feature tests for touched behaviour; pure client logic gets a `node --test` unit test. Per CLAUDE.md, tests are written with each story, but Pint, ESLint and the test suite run once, in the final phase.

**Organization**: Tasks are grouped by user story, numbered as in [spec.md](spec.md): US1 items and starting stock, US2 seeing and giving, US3 residents give and ask (and vendors), US4 taking from objects, US5 activities that cost and give, US6 items that hold or need something, US7 credit history.

**UI standard (applies to every UI task)**: New UI must match the current theme and look polished.
- **HUD pieces** use the existing HUD classes and entrance animations in `resources/css/app.css` (`world-hud-panel`, `world-hud-label`, `world-hud-key`, `world-hud-glow`, `world-pause-backdrop`, `hud-enter-fade`, `hud-enter-rise`, `hud-enter-rule`, `hud-enter-scale`), modeled on `resources/js/components/world/hud/PassageConfirm.jsx`, `ZoneTitleCard.jsx` and `InspectCard.jsx`.
- **Configuration UI** uses `resources/js/components/common/Accordion.jsx`, `ConfirmationModal.jsx`, `Toggle.jsx` and the label, input and button classes of `WorldResidentsEditor.jsx` and `RegionPassagesEditor.jsx`.
- **Colors** use theme tokens only (`accent`, `fg-1`–`fg-3`, `line-1`, `danger`); no hard-coded colors and no unstyled browser controls.
- **Details:** item images show as thumbnails with a styled placeholder when missing; every list has a designed empty state; numbers and "unlimited" read clearly (∞ for unlimited).
- **Wording:** labels follow the "Names to approve" table in [plan.md](plan.md) until the user approves or changes them.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: The user story the task belongs to

---

## Phase 1: Setup

**Purpose**: Create the files the later phases fill in.

- [ ] T001 Create the migrations with `php artisan make:migration --no-interaction` in `database/migrations/`: `create_items_table`, `create_starting_inventories_table`, `create_starting_inventory_items_table`, `create_inventories_table`, `create_inventory_items_table`, `create_activity_terms_table`, `create_handover_requests_table`, `create_credit_transactions_table`, `add_narrator_model_id_to_worlds_table`, in that order
- [ ] T002 [P] Create models with factories with `php artisan make:model <Name> --factory --no-interaction` for `Item`, `StartingInventory`, `StartingInventoryItem`, `Inventory`, `InventoryItem`, `ActivityTerms`, `HandoverRequest`, `CreditTransaction` in `app/Models/` and `database/factories/`
- [ ] T003 [P] Create `app/Enums/InventoryHolder.php` (`Player`, `Resident`, `Object`) and `app/Enums/HandoverRequestStatus.php` (`Pending`, `Accepted`, `Declined`, `Unaffordable`)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The schema, models, the transfer action and session stocking that every story uses.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T004 Implement all nine migrations from T001 per [data-model.md](data-model.md) (column types, nullable = unlimited, cascades, unique indexes) in `database/migrations/`
- [ ] T005 [P] Implement `Item` (fillable, `releases_items` array cast, `world()`, `cardImage()` morph role `card`) in `app/Models/Item.php`
- [ ] T006 [P] Implement `StartingInventory` and `StartingInventoryItem` (holder enum cast, `items()`, `item()`, `worldResident()`, `region()`) in `app/Models/StartingInventory.php` and `app/Models/StartingInventoryItem.php`
- [ ] T007 [P] Implement `Inventory` and `InventoryItem` (holder enum cast, `items()`, `item()`, `worldSession()`, `worldResident()`, `region()`, `displayName()` returning the player's name, the resident's assistant name or the layout object's name) in `app/Models/Inventory.php` and `app/Models/InventoryItem.php`
- [ ] T008 [P] Implement `ActivityTerms` (`gives_items` array cast, `requiredItem()`, `region()`), `HandoverRequest` (status enum cast, `items` array cast, `inventory()`, `conversation()`), and `CreditTransaction` in `app/Models/`
- [ ] T009 Add relations `World::items()`, `World::startingInventories()`, `World::narratorModel()` (and `narrator_model_id` fillable), `WorldSession::inventories()`, `WorldSession::creditTransactions()`, `Region::activityTerms()` in `app/Models/World.php`, `app/Models/WorldSession.php`, `app/Models/Region.php`
- [ ] T010 [P] Implement the factories with states: `unlimited()`, `forSale()`, `takeable()`, `forPlayer()`, `forResident()`, `forObject()` where they apply, in `database/factories/`
- [ ] T011 Implement `app/Actions/ResolveInventory.php`: returns a holder's session inventory, copying it from its starting inventory (or creating it empty) on first use (research R3)
- [ ] T012 Implement `app/Actions/TransferInventory.php`: lock both inventories, check the giver has enough (unlimited always does), move credits and items, delete item rows at 0, write a `CreditTransaction` with both display names for any credits, all in one transaction; throw a `RuntimeException` naming what is missing (research R4)
- [ ] T013 Implement `app/Actions/StockSession.php` copying every starting inventory of the world with its items into the session, and call it from `store()` in `app/Http/Controllers/Api/WorldSessionController.php`
- [ ] T014 [P] Write `tests/Feature/TransferInventoryTest.php`: moves items and credits; unlimited stays unlimited; refuses when short and leaves both sides unchanged; removes rows at 0; records credit transactions with names; `StockSession` copies configuration and later configuration changes don't reach the session; `ResolveInventory` copies on first use
- [ ] T015 [P] Implement `resources/js/components/world/inventoryChanges.js` (diff two player inventories into toast lines such as "+2 Bread", "-30 credits") and `tests/Unit/InventoryChanges.test.js`
- [ ] T016 Implement `resources/js/hooks/useInventory.js`: holds the player's inventory for the session, fetches it, accepts `inventory` from any response, and raises change toasts through `useToast` using `inventoryChanges.js` (effect-local async closure per Constitution VIII)

**Checkpoint**: Inventories exist and can move safely; stories can proceed.

---

## Phase 3: User Story 1 - Define items and starting stock (Priority: P1) 🎯 MVP

**Goal**: Items per world and starting inventories for the player and residents, copied into new sessions.

**Independent Test**: [quickstart.md](quickstart.md) step 1.

- [ ] T017 [P] [US1] Create `StoreItemRequest` and `UpdateItemRequest` (unique name per world, `releasesItems` items of the same world, non-negative numbers) in `app/Http/Requests/`
- [ ] T018 [US1] Implement `app/Http/Controllers/Api/ItemController.php` (index with `usage`, store, update, destroy through `DeleteItem`) and `app/Http/Controllers/Api/ItemImageController.php` (card image, following `WorldImageController`)
- [ ] T019 [US1] Implement `app/Actions/DeleteItem.php`: removes the item from `gives_items` and `releases_items` lists, then deletes it (cascades clear inventories and requirements)
- [ ] T020 [P] [US1] Create `UpdatePlayerStartingInventoryRequest` (no unlimited) and `UpdateResidentStartingInventoryRequest` (refuses items or credits when the resident's model can't call tools, FR-002a) in `app/Http/Requests/`
- [ ] T021 [US1] Implement index, player update and resident update in `app/Http/Controllers/Api/StartingInventoryController.php`
- [ ] T022 [US1] Add the item and starting inventory routes from [contracts/api.md](contracts/api.md) in `routes/api.php`
- [ ] T023 [P] [US1] Write `tests/Feature/Api/ItemControllerTest.php`: CRUD, duplicate names, another world's item in `releasesItems`, delete with usage clears inventories, terms and lists, another user's world 404
- [ ] T024 [P] [US1] Write `tests/Feature/Api/StartingInventoryControllerTest.php`: player refuses unlimited, resident accepts unlimited and for-sale, FR-002a refusal message, a new session starts with the configuration
- [ ] T025 [US1] Build `resources/js/components/ItemsEditor.jsx`: an item list with thumbnails and base prices, add and edit in an accordion (name, description, image, base price, contents, use requirement, releases, consumed on use), delete through `ConfirmationModal` showing the usage counts, and an empty state; follow the UI standard
- [ ] T026 [US1] Build `resources/js/components/StartingInventoryEditor.jsx`: reusable rows of item picker (with thumbnail), quantity with an unlimited toggle (hidden for the player), optional for-sale or takeable toggle, and a credits field with unlimited toggle; follow the UI standard
- [ ] T027 [US1] Add the Items section and the player's starting inventory to the World tab in `resources/js/components/WorldForm.jsx`, and a starting inventory section per resident in `resources/js/components/WorldResidentsEditor.jsx` that shows the FR-002a reason inline when refused

**Checkpoint**: Items and starting stock are configurable; new sessions carry them.

---

## Phase 4: User Story 2 - See and give what the player holds (Priority: P1)

**Goal**: The player sees credits and items, and gives them to the character they're talking with.

**Independent Test**: [quickstart.md](quickstart.md) step 2.

- [ ] T028 [P] [US2] Create `StoreHandoverRequest` (resident in this world, non-negative amounts) in `app/Http/Requests/`
- [ ] T029 [US2] Implement `show` (player inventory) in `app/Http/Controllers/Api/InventoryController.php` and `store` in `app/Http/Controllers/Api/HandoverController.php` (transfer player → resident, return `line`, `inventory`, `changes` per research R7)
- [ ] T030 [US2] Add the inventory and handover routes in `routes/api.php`
- [ ] T031 [P] [US2] Write `tests/Feature/Api/InventoryPlayControllerTest.php` (US2 cases): inventory shape, give moves items and credits and returns the line, giving more than held returns 422 and moves nothing, another session 404
- [ ] T032 [P] [US2] Build `resources/js/components/world/hud/CreditsReadout.jsx`: a small always-visible HUD panel with the credit balance that pulses briefly on change; follow the UI standard
- [ ] T033 [P] [US2] Build `resources/js/components/world/hud/InventoryPanel.jsx`: a HUD overlay opened by a key shown in `ControlsLegend`, a grid of item cards with thumbnail, name and quantity, and the item's description on highlight; keyboard navigable like `InspectCard`; empty state; follow the UI standard
- [ ] T034 [US2] Add the give control to `resources/js/components/WorldChat.jsx`: pick items and quantities or an amount of credits, never more than held, then post the handover and send the returned `line` as the player's message
- [ ] T035 [US2] Wire `useInventory`, `CreditsReadout` and `InventoryPanel` into `resources/js/pages/WorldPage.jsx` and add the key to `resources/js/components/world/hud/ControlsLegend.jsx`

**Checkpoint**: The player can see and give.

---

## Phase 5: User Story 3 - Residents give and ask for payment (Priority: P1)

**Goal**: `give` and `ask_for` tools, handover requests the player answers, and vendors' goods.

**Independent Test**: [quickstart.md](quickstart.md) step 3.

- [ ] T036 [P] [US3] Implement `app/Services/AgentLoop/Tools/World/GiveTool.php` (items by name and credits from the resident's own inventory to the conversation partner through `TransferInventory`; refusals return the reason) and `AskForTool.php` (creates a pending `HandoverRequest` with a reason; returns "You asked; they will answer.")
- [ ] T037 [US3] Add an `inventoryTools()` builder to `app/Services/AgentLoop/Tools/World/WorldToolbox.php` (give always, ask_for only toward the player) and use it in the world path of `sendMessage` in `app/Http/Controllers/Api/ConversationController.php`, returning `inventory`, `changes` and `handoverRequest` in the response
- [ ] T038 [US3] Give residents `GiveTool` toward each other in `app/Actions/GenerateResidentConversationTurn.php` when their model supports tools
- [ ] T039 [US3] Add the resident's own inventory section (items, quantities, for-sale marks, base prices, credits) to the prompt in `app/Actions/AppendWorldConversationContext.php` (research R13)
- [ ] T040 [P] [US3] Create `AnswerHandoverRequestRequest` in `app/Http/Requests/`
- [ ] T041 [US3] Implement `answer` in `app/Http/Controllers/Api/HandoverController.php` (accepted / declined / unaffordable, 409 when answered, returns `line`) and `goods` in `app/Http/Controllers/Api/InventoryController.php` (items for sale only; `[]` for non-vendors), with routes in `routes/api.php`
- [ ] T042 [P] [US3] Write `tests/Feature/InventoryWorldToolsTest.php`: give moves from the resident, refuses beyond stock, unlimited stays unlimited, ask_for creates a pending request, residents give to each other, the prompt lists their own inventory and never the player's
- [ ] T043 [P] [US3] Extend `tests/Feature/Api/InventoryPlayControllerTest.php`: accept moves credits and items together, decline moves nothing, unaffordable status, second answer 409, goods show only for-sale items and nothing for non-vendors
- [ ] T044 [P] [US3] Build `resources/js/components/world/hud/HandoverRequestConfirm.jsx`: the request's items (with thumbnails) and credits, the reason, accept disabled with an explanation when unaffordable, ENTER / ESC keys; styled after `PassageConfirm.jsx`
- [ ] T045 [US3] Show the request confirm when a reply carries `handoverRequest`, post the answer and send the returned `line`, and add the vendor goods strip (thumbnails, quantities, base prices) to the conversation panel when the partner is a vendor, in `resources/js/components/WorldChat.jsx`

**Checkpoint**: Trading with characters works end to end. MVP complete (US1–US3).

---

## Phase 6: User Story 4 - Take items from objects (Priority: P2)

**Goal**: Objects hold items and credits; takeable items can be taken one at a time.

**Independent Test**: [quickstart.md](quickstart.md) step 4.

- [ ] T046 [P] [US4] Create `UpdateObjectStartingInventoryRequest` (object exists in the layout, takeable only here) in `app/Http/Requests/`
- [ ] T047 [US4] Implement the object update in `app/Http/Controllers/Api/StartingInventoryController.php`, `take` in `app/Http/Controllers/Api/InventoryController.php`, and per-object `takeable` entries in the region show response when a `session` is given (`app/Http/Controllers/Api/RegionController.php`), with routes in `routes/api.php`
- [ ] T048 [US4] Delete starting inventories, session object inventories and activity terms of objects or activities that vanish from a re-uploaded layout, in `app/Actions/ReconcilePassages.php`
- [ ] T049 [P] [US4] Write `tests/Feature/Api/ObjectInventoryTest.php`: take moves one, unlimited never runs out, not takeable or empty returns 422, a new session restores the configured amount, a re-upload without the object removes its inventory
- [ ] T050 [US4] Add an object inventory editor (a layout object picker, then `StartingInventoryEditor` with takeable toggles and credits) to `resources/js/components/RegionForm.jsx`
- [ ] T051 [US4] Add a "take" row per takeable item left, with thumbnail and count, to `resources/js/components/world/hud/InspectCard.jsx`, and handle choosing it in `resources/js/hooks/usePlayerActivities.js`

**Checkpoint**: The world has things to find.

---

## Phase 7: User Story 5 - Activities that cost and give (Priority: P2)

**Goal**: Activity terms (item, cost, gives, plain-language requirement and outcome) and the narrator.

**Independent Test**: [quickstart.md](quickstart.md) steps 5 and 6.

- [ ] T052 [P] [US5] Implement `app/Services/AgentLoop/Tools/World/NarrateTool.php` (`{ succeeded, narration }`) and `app/Actions/Narrate.php` (one forced-tool call on the world's narrator model, falling back to `LlmManager::fromConfig()`, with world and region prompts, the terms, the actor's holdings, location and the attempt; throws when no model is available) (research R10)
- [ ] T053 [US5] Accept `narratorModelId` (one of the user's models) in `app/Http/Requests/UpdateWorldRequest.php` and return it from `app/Http/Controllers/Api/WorldController.php`; add a styled model select to `resources/js/components/WorldForm.jsx`
- [ ] T054 [P] [US5] Create `UpdateActivityTermsRequest` (activity offered by that object, items of this world) in `app/Http/Requests/`
- [ ] T055 [US5] Implement `app/Http/Controllers/Api/ActivityTermsController.php` (index, update, destroy) and add `activityTerms` to the region show response, with routes in `routes/api.php`
- [ ] T056 [US5] Implement `app/Actions/UseActivity.php` (check required item and cost, check the object can cover what it gives, narrate plain-language terms, then one transfer for cost, consumed item and gives) and `app/Http/Controllers/Api/ActivityUseController.php` returning `allowed`, `reason`, `narration`, `inventory`, `changes`, with its route
- [ ] T057 [US5] Make `app/Services/AgentLoop/Tools/World/UseTool.php`, `WhereCanITool.php` and `WhatIsInTool.php` leave out activities whose item or credit terms the resident can't meet, and have `UseTool` apply terms through `UseActivity`, returning a narrated refusal to the model
- [ ] T058 [P] [US5] Write `tests/Feature/NarrateTest.php` (faked LLM: success and refusal, prompt contents, missing model 422) and `tests/Feature/Api/ActivityTermsTest.php` (configuration validation; use refused without the key or credits with the reason; cost, consumption and gives applied; stock exhaustion; residents not offered unaffordable activities; narrated requirement success and refusal)
- [ ] T059 [P] [US5] Build `resources/js/components/world/hud/NarrationCard.jsx`: a HUD card for narrated results that types in like `ZoneTitleCard`, distinguishes success and refusal through the accent and `danger` tokens, and shows the item and credit changes beneath; follow the UI standard
- [ ] T060 [US5] In `resources/js/hooks/usePlayerActivities.js`, call activity-uses before starting an activity that has terms, ask for an optional attempt line when the terms have a plain-language requirement (a small styled input in the inspect card), start only when allowed, and show the narration in `NarrationCard`
- [ ] T061 [US5] Build `resources/js/components/ActivityTermsEditor.jsx` (per layout object and activity: required item and consumed toggle, cost, gives credits and items, requirement and outcome text areas) and add it to `resources/js/components/RegionForm.jsx`; follow the UI standard

**Checkpoint**: The world charges, rewards and judges.

---

## Phase 8: User Story 6 - Items that hold or need something (Priority: P2)

**Goal**: Examine and use items, judged and narrated.

**Independent Test**: [quickstart.md](quickstart.md) step 7.

- [ ] T062 [US6] Implement `app/Http/Controllers/Api/ItemUseController.php`: `examine` (narrates `contents`; 422 when not held) and `use` (narrates `use_requirement` with the attempt; on success removes one when `consumed_on_use` and gives `releases_credits` and `releases_items` from the item definition), with routes in `routes/api.php`
- [ ] T063 [P] [US6] Write `tests/Feature/Api/ItemUseTest.php` (faked LLM): examine narrates, use with a wrong attempt fails and changes nothing, a right attempt releases and consumes, items not held return 422
- [ ] T064 [US6] Add Examine and Use actions to the highlighted item in `resources/js/components/world/hud/InventoryPanel.jsx`, with a styled attempt input for Use, showing results in `NarrationCard`

**Checkpoint**: Items carry stories.

---

## Phase 9: User Story 7 - Credit history (Priority: P3)

**Goal**: The player's credit history for the session.

**Independent Test**: [quickstart.md](quickstart.md) step 3, then open the history.

- [ ] T065 [US7] Implement `creditHistory` in `app/Http/Controllers/Api/InventoryController.php` with its route
- [ ] T066 [P] [US7] Extend `tests/Feature/Api/InventoryPlayControllerTest.php`: entries for gifts, payments, activity costs and gives, newest first, names kept after a resident is removed, empty history
- [ ] T067 [US7] Add a history tab to `resources/js/components/world/hud/InventoryPanel.jsx`: signed amounts in accent (in) and `fg-2` (out), counterpart, reason and time, with an empty state

**Checkpoint**: All stories complete.

---

## Phase 10: Polish & Cross-Cutting Concerns

- [ ] T068 Review every new and changed screen against the UI standard: spacing, alignment, animations, empty states, long names, ∞ display, keyboard use, and the look next to existing HUD pieces and editors; fix what doesn't match
- [ ] T069 Update the "Names to approve" labels in the UI to whatever the user approved or changed
- [ ] T070 Run the quality gates once: `vendor/bin/pint --dirty --format agent`, `npm run lint`, `php artisan test --compact`, and `node --test tests/Unit/`; fix everything that surfaces
- [ ] T071 Hand the user [quickstart.md](quickstart.md) for the manual walkthrough, including a visual check of every new screen

---

## Dependencies & Execution Order

- **Setup (Phase 1)** → **Foundational (Phase 2)** → stories.
- **US1** first: every other story needs items and inventories to configure.
- **US2** after US1. **US3** after US2 (reuses the handover line flow and `WorldChat` give work).
- **US4** after US1 (independent of US2 and US3).
- **US5** after US1; T057 touches the same tools as US3's T037, so run it after US3 when both are in progress.
- **US6** after US5 (uses `Narrate` and `NarrationCard`) and US2 (uses `InventoryPanel`).
- **US7** after US2.
- **Polish** last.

## Parallel Examples

- **Phase 2**: T005–T008 and T010 in parallel after T004; T014 and T015 in parallel after T012.
- **US1**: T017, T020, T023 and T024 in parallel; T025 and T026 in parallel.
- **US2**: T031, T032 and T033 in parallel after T029.
- **US3**: T036, T040, T042, T043 and T044 in parallel.
- **US5**: T052, T054, T058 and T059 in parallel.

## Implementation Strategy

1. **MVP**: Phases 1–5 (US1–US3), meaning items, starting stock, giving, and residents trading and asking. Validate with quickstart steps 1–3.
2. **World interaction**: US4 and US5, adding objects that hold things and activities that cost, give and judge.
3. **Depth**: US6 and US7.
4. **Polish**: the UI review, then the gates, once.
