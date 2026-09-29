---
description: "Task list for Offer Conditions for Quests"
---

# Tasks: Offer Conditions for Quests

**Input**: Design documents from `/specs/021-quest-offer-conditions/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/api.md](contracts/api.md), [quickstart.md](quickstart.md)

**Tests**: Included. Constitution VI requires factory-backed Pest feature tests for touched behaviour. Per CLAUDE.md, tests are written with each story, but Pint, ESLint and the test suite run once, in the final phase.

**Organization**: Tasks are grouped by user story, numbered as in [spec.md](spec.md): US1 the giver decides, US2 feelings, other quests and exchanges, US3 the moment of the conversation, US4 the offer question, US5 the quest editor, US6 seeing how a giver decided. Phases follow priority: the P1 stories (US1, US2, US5) come first.

**UI standard (applies to every UI task)**: the one in [feature 018's tasks.md](../018-items-inventory-credits/tasks.md) and the existing quest editor: theme tokens only, the `SELECT` and `SMALL_BUTTON` styles of `ConditionBuilder.jsx`, `FieldErrors` from `RubricEditor.jsx`, `hud-enter-fade` on new rows. Labels, descriptions and the tool name use the approved names in [plan.md](plan.md).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: The user story the task belongs to

---

## Phase 1: Setup

**Purpose**: Create the files the later phases fill in.

- [X] T001 Create the migration with `php artisan make:migration create_item_transfers_table --no-interaction` in `database/migrations/`
- [X] T002 [P] Create the model and factory with `php artisan make:model ItemTransfer --factory --no-interaction` in `app/Models/ItemTransfer.php` and `database/factories/ItemTransferFactory.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The item ledger, the definition accessors, trigger causes and the evaluator's new parameters that every story uses.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T003 Implement the `item_transfers` migration from T001 in `database/migrations/` per [data-model.md](data-model.md): foreign keys and their delete rules, `quantity`, `reason`, `by_creator`, and the index on (`world_session_id`, `from_inventory_id`, `to_inventory_id`, `item_id`)
- [X] T004 [P] Implement `ItemTransfer` (fillable as `CreditTransaction`, `by_creator` boolean cast, `worldSession()`, `item()`) in `app/Models/ItemTransfer.php` and its factory in `database/factories/ItemTransferFactory.php`
- [X] T005 [P] Add `offerWhen(): ?array` and `offerQuestion(): ?string` to `app/Models/Quest.php` reading `definition.start`, and make `question(':offer')` return `['id' => ':offer', 'text' => offerQuestion()]` when the quest has one, with a comment on why `:offer` can't collide with beat question ids (research R6)
- [X] T006 [P] Add states `offerWhen(array $condition)` and `offerQuestion(string $text)` to `database/factories/QuestFactory.php`, each setting `start.mode` to `offer` and leaving `start.giver` as the `offeredBy` state set it
- [X] T007 [P] Add `cause(): ?string` to `app/Contracts/QuestTrigger.php` and a default returning null in `app/Events/Quests/QuestTriggerEvent.php` (research R9)
- [X] T008 In `app/Listeners/AdvanceQuests.php`, add the trigger's `cause()` to the `beat_finished`, `started`, `completed` and `failed` payloads when it isn't null; give `handle()` in `app/Actions/Quests/StartQuestRun.php` an optional payload array merged into its `started` event, since that event is written there
- [X] T009 [P] Create `app/Actions/Quests/OfferMoment.php`: a readonly value with the session, the giver (`WorldResident`), the region and the request's positions (`array{user?: …, residents?: …}`); its answers for the moment leaves come in US3
- [X] T010 Give `QuestConditions::holds()` in `app/Actions/Quests/QuestConditions.php` an optional trailing `?OfferMoment $moment = null`, passed down through `all`, `any` and `not`

**Checkpoint**: The ledger exists, triggers can explain themselves, and conditions can be evaluated for a turn.

---

## Phase 3: User Story 1 - A giver decides when to offer a quest (Priority: P1) 🎯 MVP

**Goal**: The giver knows each offerable quest's offerWhen in plain words, checks its parts with `check_offer_condition`, and decides alone; offers record what was checked.

**Independent Test**: [quickstart.md](quickstart.md) step 2 with a trust-only offerWhen; spec US1's independent test.

- [X] T011 [US1] Create `app/Actions/Quests/DescribeCondition.php` (research R8): `leaf(array $leaf, ?WorldResident $giver): string` and `tree(?array $condition, ?WorldResident $giver): string` in plain English with names from the world (residents, items, facts, regions, zones, objects, activities, quests by title), from the giver's point of view when given one ("your trust toward the user", "you are in the Docks"), for every existing leaf; `all` joins with "and", `any` with "or", `not` reads "not …"
- [X] T012 [US1] Create `app/Actions/Quests/LookUpOfferCondition.php` (research R3): `parts(Quest $quest, WorldResident $giver): array` listing each leaf of `offerWhen` with its path and its `DescribeCondition` label; `lookUp(WorldSessionQuest $run, string $path, QuestSessionState $state, OfferMoment $moment): array{part, value, asks}` with the values of [contracts/api.md](contracts/api.md) for every existing leaf (latched leaves read `state.seen` under the scope `offer`); `unmetParts(WorldSessionQuest $run, QuestSessionState $state, OfferMoment $moment): array` returning the labels of the leaves that don't hold, and `holds()` using `QuestConditions::holds()` with scope `offer`
- [X] T013 [US1] In `app/Listeners/AdvanceQuests.php`, latch the event leaves of `start.offerWhen` for Available runs of quests that start by offer, under the scope `offer`, and save the run when anything latched (research R9)
- [X] T014 [US1] Create `app/Services/AgentLoop/Tools/World/CheckOfferConditionTool.php` per [contracts/api.md](contracts/api.md): constructed with the session, conversation, giver and `OfferMoment`; `quest` and `part` enums from the giver's offerable runs that have offerWhen (same rules as `OfferQuestTool::offerable()`) and their `LookUpOfferCondition::parts()`; refuses a part that isn't in that quest's offerWhen, naming the parts it has; keeps a public `$lookups` list of every result this turn
- [X] T015 [US1] Change `app/Services/AgentLoop/Tools/World/OfferQuestTool.php` to take an optional `CheckOfferConditionTool` and `OfferMoment`, and add `lookups` (every lookup of the turn, in order), `offerWhenHeld` and `unmetParts` to the `offered` event's payload when the quest has offerWhen; its refusals stay exactly as they are, with a comment that offerWhen never gates the offer (FR-004)
- [X] T016 [US1] In `questTools()` of `app/Http/Controllers/Api/ConversationController.php`, build an `OfferMoment` from the region and `$validated['positions']`, add `check_offer_condition` when any offerable quest of the resident has offerWhen, and pass the tool and moment to `offer_quest`
- [X] T017 [US1] Extend `offers()` in `app/Actions/BuildQuestsPrompt.php` per the prompt section of [contracts/api.md](contracts/api.md): each offerable quest with offerWhen gets "Offer it only once this holds: … Check each part with check_offer_condition before you offer.", and the section ends with the discretion line once when any listed quest has offerWhen or an offerQuestion
- [X] T018 [P] [US1] Create `tests/Feature/QuestOfferConditionsTest.php` (US1 cases): the prompt carries the offerWhen prose and the discretion line only for quests with offerWhen; `check_offer_condition` returns value and ask for `has`, `credits` and a latched `enterRegion`; a part outside the quest's offerWhen is refused; `offer_quest` succeeds while offerWhen doesn't hold and records `lookups`, `offerWhenHeld: false` and `unmetParts`; a quest without offerWhen gets no tool and behaves as before; with an offer of it pending, the quest has no offerWhen line and no `check_offer_condition` entry (FR-005); another session's runs aren't offerable

**Checkpoint**: A giver can check and offer quests with offerWhen over the existing leaves.

---

## Phase 4: User Story 2 - Conditions about feelings, other quests and exchanges (Priority: P1) 🎯 MVP

**Goal**: `feeling`, `questState`, `declinedTimes`, `gaveTo` and `spentWith` hold wherever conditions are used, and beats react the moment they change, with a readable cause.

**Independent Test**: [quickstart.md](quickstart.md) steps 4 and 5; spec US2's independent test.

- [X] T019 [US2] In `app/Actions/TransferInventory.php`, write an `ItemTransfer` row per item moved, next to `recordCredits`, with the same reason and `byCreator`; give `PlayerInventoryChanged` a cause when the player gives items or credits to a resident ("The user gave Mara 2 bread", "The user paid Mara 30 credits")
- [X] T020 [US2] Add `gaveTo` and `spentWith` to `leaves()`, and a constructor `?string $cause` returned by `cause()`, in `app/Events/Quests/PlayerInventoryChanged.php`
- [X] T021 [P] [US2] Create `app/Events/Quests/ResidentFeelingsChanged.php` (leaves `feeling`; cause "Mara's trust is now 3", naming each changed feeling) and dispatch it from `ResidentFeeling::adjust()` in `app/Models/ResidentFeeling.php` when a value changed
- [X] T022 [P] [US2] Create `app/Events/Quests/QuestStateChanged.php` (leaves `questState` and `declinedTimes`; a cause such as "The user declined The Lost Ledger") and dispatch it from `app/Actions/Quests/StartQuestRun.php`, `app/Actions/Quests/EndQuestRun.php`, `app/Actions/Quests/WithdrawQuestOffers.php`, `app/Services/AgentLoop/Tools/World/OfferQuestTool.php` and the accept and decline paths of `app/Http/Controllers/Api/QuestOfferController.php`
- [X] T023 [US2] Extend `app/Actions/Quests/QuestSessionState.php` per [data-model.md](data-model.md): load feelings by resident (a resident without a feelings row reads as the model's starting value, 0), item quantities given by resident and item (`item_transfers` from the player's inventory to residents' inventories), credits paid by resident (`credit_transactions` likewise), and per quest key the pending offer, latest answered offer status and declined-or-withdrawn count (`quest_offers`); add `feeling()`, `gaveTo()`, `spentWith()`, `questState()` per the table in [data-model.md](data-model.md) and `declinedTimes()`
- [X] T024 [US2] Add the five leaves to `holds()` in `app/Actions/Quests/QuestConditions.php`: `feeling` with `atLeast` and `atMost`, `questState`, `declinedTimes`, `gaveTo`, `spentWith`
- [X] T025 [US2] Add the five leaves to `app/Actions/Quests/DescribeCondition.php` and their values to `app/Actions/Quests/LookUpOfferCondition.php` per [contracts/api.md](contracts/api.md)
- [X] T026 [P] [US2] Extend `tests/Feature/TransferInventoryTest.php`: each item movement writes an `item_transfers` row with reason and creator flag; a gift to a resident dispatches `PlayerInventoryChanged` with its cause
- [X] T027 [P] [US2] Extend `tests/Feature/QuestProgressTest.php` (US2 cases): a beat on trust at least 3 finishes when `adjust()` reaches 3 with the cause in its payload's `because`; `atMost` holds at or below; a resident with no feelings row reads as 0; `gaveTo` sums two handovers and ignores items coming back; `spentWith` sums payments; `questState` declined then active after accepting; a withdrawn offer counts toward `declinedTimes` and makes the state declined; a start condition on `feeling` starts the quest; another session's transfers and offers don't count
- [X] T028 [P] [US2] Extend `tests/Feature/QuestOfferConditionsTest.php` (US2 cases): `check_offer_condition` returns the feeling with one decimal, the quest state and the sums

**Checkpoint**: The five leaves work everywhere and are visible to the giver.

---

## Phase 5: User Story 5 - Writing offer conditions in the quest editor (Priority: P1) 🎯 MVP

**Goal**: Authors write offerWhen, the offerQuestion and every new leaf in the form or JSON, with checks that name each problem where it is.

**Independent Test**: [quickstart.md](quickstart.md) step 1; spec US5's independent test.

- [X] T029 [US5] Extend `app/Actions/Quests/ValidateQuestDefinition.php` per [data-model.md](data-model.md): `start.offerWhen` (as a condition, path `start.offerWhen`) and `start.offerQuestion` (non-empty string), both refused unless `start.mode` is `offer`; the eight leaves with their references (this quest's own key allowed for `questState` and `declinedTimes`), ranges and bounds; the moment leaves refused outside `start.offerWhen` with "This condition only works in Offer when."; `beat` and `question` refused inside `start.offerWhen`; a warning when the giver's model can't call tools and the quest has offerWhen or an offerQuestion
- [X] T030 [US5] Extend `app/Actions/Quests/FindQuestReferences.php`: walk `start.offerWhen`, count residents and items named by the new leaves, and add a `quests` kind collecting `questState` and `declinedTimes` quest keys with `ensureQuestUnused(Quest)`; call it from `destroy` in `app/Http/Controllers/Api/QuestController.php`, returning the same 422 shape as the other destroy endpoints
- [X] T031 [P] [US5] Add the eight types to `resources/js/utils/questConditionTypes.js` with their approved labels and descriptions, and `offerOnly: true` on `messagesWith`, `giverIn` and `othersInTheZone`; export the list of types allowed outside offerWhen
- [X] T032 [US5] Extend `resources/js/components/ConditionBuilder.jsx`: defaults and fields for the eight leaves (feeling: resident, Romance/Trust/Liking, at least or at most, a number clamped to -10…10; quest state: quest with "This quest" first, then Offered/Active/Declined/Abandoned; counts and amounts clamped to whole numbers of at least 1; giver in zone: region and zone; others in the zone: "a named resident" with a resident picker, or "no one besides the giver"); the type's description under each row and an "Offer when only" badge on moment rows; a `path` prop and `errorsAt` so each row shows its own errors under it with `FieldErrors`; follow the UI standard
- [X] T033 [US5] In `resources/js/components/QuestEditor.jsx`, when the quest starts by offer, show "Offer when" (a `ConditionBuilder` with every type except `beat` and `question`, path `start.offerWhen`) and "Offer question" (a textarea), with the help line "The giver checks these and decides alone whether to offer."; switching the start mode away from offer drops both; pass `path` and `errorsAt` to every `ConditionBuilder`, and the types allowed outside offerWhen to the others; follow the UI standard
- [X] T034 [P] [US5] Extend `tests/Feature/QuestDefinitionValidationTest.php` (US5 cases): each new leaf accepted when valid; unknown resident, item, quest or zone refused at its path; feeling out of range, no bound, and `atLeast` above `atMost` refused; counts below 1 refused; moment leaves refused in a beat, `complete`, `fail` and `start.when`; offerWhen and offerQuestion refused on a quest that doesn't start by offer; this quest's own key accepted in `questState`; `beat` and `question` refused inside `start.offerWhen`; and, in `tests/Feature/QuestCreatorModeTest.php`, an `edit_quest` putting a moment leaf in a beat is refused with the same message (FR-017)
- [X] T035 [P] [US5] Extend `tests/Feature/Api/QuestControllerTest.php` (US5 cases): deleting a quest another quest names in `questState` or `declinedTimes` returns 422 with that quest; deleting an item or resident named by `gaveTo` or `feeling` returns 422

**Checkpoint**: MVP complete: authors write offer conditions, givers check and decide, and the new leaves drive beats.

---

## Phase 6: User Story 3 - Conditions about the moment of the conversation (Priority: P2)

**Goal**: `messagesWith`, `giverIn` and `othersInTheZone` read the current turn, in offerWhen only.

**Independent Test**: [quickstart.md](quickstart.md) step 2 with the docks and B; spec US3's independent test.

- [X] T036 [US3] Add `messagesWith(int $residentId): int`, `giverZoneChain(): array` and `residentsInGiverZone(): array` to `app/Actions/Quests/OfferMoment.php` per research R7: messages with `role = user` in the session's conversations between the player and that resident's assistant; the giver's zone chain from `ResolveWorldState::locate()`; the other residents whose own zone chain contains the giver's innermost zone, residents without a position counting as elsewhere
- [X] T037 [US3] Add the three leaves to `holds()` in `app/Actions/Quests/QuestConditions.php`, false without an `OfferMoment` and false when the named zone no longer exists; add them to `app/Actions/Quests/DescribeCondition.php` and their values to `app/Actions/Quests/LookUpOfferCondition.php`, including the missing-zone value of [contracts/api.md](contracts/api.md)
- [X] T038 [P] [US3] Extend `tests/Feature/QuestOfferConditionsTest.php` (US3 cases): `messagesWith` counts the message being answered and OOC and creator-command messages, and not other residents' conversations; `giverIn` matches a parent zone; `othersInTheZone` with a named resident and with `nobody`, with residents in and out of the giver's zone and without positions; a `giverIn` zone removed from the layout reads as no longer existing; `offerWhenHeld` reflects them

**Checkpoint**: Offers can depend on where and with whom the giver is.

---

## Phase 7: User Story 4 - A question the giver weighs before offering (Priority: P2)

**Goal**: The giver keeps the offerQuestion in mind, signals it, is told the judge's answer, and weighs it.

**Independent Test**: [quickstart.md](quickstart.md) step 3; spec US4's independent test.

- [X] T039 [US4] Create `app/Actions/Quests/OfferQuestionStatus.php`: for a session and quest, whether a `question_judged` event for `:offer` with `met: true` and the current text's hash exists on any of its runs in the session, and the latest answer's reason otherwise (research R6); used by the prompt and `SignalQuestionTool`
- [X] T040 [US4] Extend `signallable()` in `app/Services/AgentLoop/Tools/World/SignalQuestionTool.php` with the giver's offerQuestions on Available runs they give, while not met and no offer of it is pending, as `:offer` entries; record `question_signalled` with the text hash
- [X] T041 [US4] In `app/Jobs/JudgeQuestion.php`, add the text hash to the `question_judged` payload for `:offer`, and write no run state for it
- [X] T042 [US4] Extend `offers()` in `app/Actions/BuildQuestsPrompt.php` with the offerQuestion line and its status per [contracts/api.md](contracts/api.md), using `OfferQuestionStatus`
- [X] T043 [P] [US4] Extend `tests/Feature/JudgeQuestionTest.php` (US4 cases): a signalled offerQuestion is judged on an Available run; a yes records the hash and leaves the run state alone; a no lets the giver signal again; a changed text needs a new yes; only the giver can signal it; a pending offer hides it
- [X] T044 [P] [US4] Extend `tests/Feature/QuestOfferConditionsTest.php` (US4 cases): the prompt says "It has been confirmed." after a yes and gives the reason after a no; a yes on an earlier run counts for a later one

**Checkpoint**: Offer questions work end to end.

---

## Phase 8: User Story 6 - Seeing how a giver decided (Priority: P2)

**Goal**: The quest event log shows causes, lookups and offers made while offerWhen didn't hold, in plain words, and the player's page never receives a quest before it is offered.

**Independent Test**: [quickstart.md](quickstart.md) step 6; spec US6's independent test.

- [X] T045 [US6] In `resources/js/components/QuestEventLog.jsx`, show `payload.because` as a sentence, list an `offered` event's `lookups` as "part: value, asks …", show "Offered while these didn't hold:" with `unmetParts` when `offerWhenHeld` is false, and leave those keys out of the key-value summary; follow the UI standard
- [X] T046 [US6] Leave Available runs out of `index` in `app/Http/Controllers/Api/QuestPlayController.php`, and `questAvailable` notices out of `app/Actions/Quests/BroadcastQuestRuns.php`, which sends an Available run as its id and status only (so a reset back to Available still reaches the page), so an unoffered quest's title and description never reach the player's page (FR-024); the offer card keeps its own payload and the run joins the list once accepted
- [X] T047 [US6] Extend `tests/Feature/Api/QuestPlayControllerTest.php` (US6 cases): the quest events endpoint returns `because`, `lookups`, `offerWhenHeld` and `unmetParts` as recorded; the quests index leaves out Available runs and includes the run once its offer is accepted; `QuestsUpdated` carries Available runs as id and status only, and no `questAvailable` notice

**Checkpoint**: All stories complete.

---

## Phase 9: Polish & Cross-Cutting Concerns

- [X] T048 Describe offer conditions, the new leaves, `check_offer_condition` and the item ledger beside quests in `ARCHITECTURE.md`
- [ ] T049 Review every changed screen against the UI standard (long names in pickers, narrow widths, error placement, the badge and descriptions next to existing rows), and confirm in the browser's network panel that nothing names a quest before it is offered (FR-024); fix what doesn't match
- [ ] T050 Run the quality gates once: `vendor/bin/pint --dirty --format agent`, `npm run lint`, `php artisan test --compact`; fix everything that surfaces
- [ ] T051 Hand the user the [quickstart.md](quickstart.md) walkthrough, including the giver judgement scenes of SC-001 and the timing goals of SC-002 and SC-006

---

## Dependencies & Execution Order

- **Setup (Phase 1)** → **Foundational (Phase 2)** → stories.
- **US1** first: `DescribeCondition`, `LookUpOfferCondition` and the tool are what later stories extend.
- **US2** after US1 (T025 extends T011 and T012; T022 edits `OfferQuestTool` after T015).
- **US5** after US2: validation and the builder cover the leaves US2 adds; it can start in parallel with US2 for the existing leaves.
- **US3** after US1; T037 extends `QuestConditions` after T024.
- **US4** after US1; T042 extends `BuildQuestsPrompt` after T017.
- **US6** after US1 and US2 (it shows their payloads).
- T013 and T008 both edit `AdvanceQuests`; run T008 first.
- T024 and T037 both edit `QuestConditions::holds()`; run in that order.
- T017 and T042 both edit `BuildQuestsPrompt::offers()`; run in that order.
- **Polish** last.

## Parallel Examples

- **Phase 1**: T002 after T001.
- **Phase 2**: T004–T007 and T009 in parallel after T003.
- **US1**: T018 in parallel with T016 and T017 once T014 and T015 are done.
- **US2**: T021 and T022 in parallel; T026, T027, T028 in parallel after T025.
- **US5**: T031 in parallel with T029 and T030; T034 and T035 in parallel.
- **US3**: T038 after T037.
- **US4**: T043 and T044 in parallel after T042.

## Implementation Strategy

1. **MVP**: Phases 1–5 (US1, US2, US5): offer conditions are written in the editor, the giver checks them with a tool and decides, and the new leaves drive beats. Validate with quickstart steps 1, 2 (without the docks), 4 and 5.
2. **The moment**: US3, where and with whom.
3. **The question**: US4, the offer question.
4. **The author's view**: US6, the event log.
5. **Polish**: docs, the UI review, then the gates, once.
