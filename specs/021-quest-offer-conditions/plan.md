# Implementation Plan: Offer Conditions for Quests

**Branch**: `claude/optimistic-mendel-ss9haa` | **Date**: 2026-09-29 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/021-quest-offer-conditions/spec.md`

## Summary

A quest that starts by offer can carry an `offerWhen` condition and an `offerQuestion`, and the giver decides alone, in character, whether to offer, after checking what they need with a tool. Eight new condition leaves arrive; five work everywhere and react as they change.

- **Definition**: `start.offerWhen` and `start.offerQuestion`; leaves `feeling`, `questState`, `declinedTimes`, `gaveTo`, `spentWith` everywhere, and `messagesWith`, `giverIn`, `othersInTheZone` in `offerWhen` only (research R1, R2).
- **The giver's decision**: the quests prompt section writes each offerWhen in plain words with a discretion instruction; a new `check_offer_condition` tool returns a part's current value and what the quest asks; `offer_quest` never refuses on offerWhen, and logs the turn's lookups and whether offerWhen held (R3, R8).
- **offerQuestion**: the existing signal and judge path with the reserved id `:offer`; whether it is met is read from the event log (R6).
- **Data**: a new `item_transfers` ledger written by `TransferInventory`; `spentWith` reads the existing `credit_transactions`; `questState` and `declinedTimes` read `quest_offers`, withdrawn offers counting as declines (R4, R5).
- **Triggers**: `ResidentFeelingsChanged` and `QuestStateChanged` are new, `PlayerInventoryChanged` covers the two ledgers, and each trigger can carry a plain-words cause that the event log shows (R9).
- **Author UI**: the condition builder gains the leaves with descriptions, clamped inputs and errors under each row; the quest editor gains "Offer when" and "Offer question"; the event log shows causes and lookups (R10, R11).

## Technical Context

**Language/Version**: PHP 8.4; JavaScript (React 19, JSX)

**Primary Dependencies**: Laravel 13, Pest 4, Ziggy 2; feature 020's quest engine (`QuestConditions`, `AdvanceQuests`, `JudgeQuestion`), feelings, `TransferInventory`, `ResolveWorldState`. No new dependencies.

**Storage**: PostgreSQL in tests, MySQL locally. One new table, `item_transfers` ([data-model.md](data-model.md)); no changed columns.

**Testing**:
- **Pest feature tests** for the definition check, the new leaves and their triggers, the ledger, `check_offer_condition`, `offer_quest` logging, the offerQuestion, the prompt and quest deletion, with factories and the LLM faked.
- **Manually**, following [quickstart.md](quickstart.md): the editor, the giver's judgement scenes and the event log.

**Target Platform**: Desktop browsers via the existing SPA.

**Project Type**: Web application (Laravel backend + React frontend, single repo).

**Performance Goals**:
- A trigger adds three small grouped queries to `QuestSessionState` (feelings, the two ledgers, offers); no model call. Beats reacting to the new leaves finish within 2 s (SC-002).
- A lookup is a few indexed queries; the zone parts reuse the request's positions.

**Constraints**:
- The game never refuses an offer because offerWhen doesn't hold or the offerQuestion isn't met (FR-004).
- Nothing about an unoffered quest reaches the player's page (FR-024).
- Moment leaves are only valid in `start.offerWhen`, and read only the current turn (FR-014).
- Existing quests and sessions keep working unchanged.
- New UI follows the UI standard of [feature 018's tasks.md](../018-items-inventory-credits/tasks.md) and the existing quest editor (FR-025).

**Scale/Scope**:
- **Backend**: about 9 new files (migration, model, factory, `OfferMoment`, `DescribeCondition`, `LookUpOfferCondition`, two trigger events, the tool) and about 16 changed.
- **Frontend**: no new components; `ConditionBuilder`, `questConditionTypes`, `QuestEditor` and `QuestEventLog` change.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Lint-Enforced Code Style**: Pint and ESLint run once at push or PR time per CLAUDE.md. PASS.
- **II. Append-Only Migrations**: `item_transfers` is a new migration; nothing existing changes. PASS.
- **III. Comments Justify Only Non-Obvious Decisions**: comments only where the reason is hidden, such as why `:offer` can't collide with beat question ids, or why offerWhen never gates the offer. PASS.
- **IV. Data Isolation by Ownership**:
  - Ledger sums and offer history are scoped to the session; residents, items and quests named in a definition must belong to the quest's world (as today).
  - `check_offer_condition` only reads quests the resident gives, in the session of the conversation.
  - Feature tests cover another session's transfers and offers not counting. PASS.
- **V. Errors Fail Loudly**: refused lookups and signals return the reason to the model; definition problems return 422 with every path; a failed judge is logged as today. PASS.
- **VI. Feature-Test-First, Factory-Backed**: an `ItemTransfer` factory; quest definition factory states for offerWhen and offerQuestion. PASS.
- **VII. No Speculative Abstraction**:
  - `DescribeCondition` has three real callers: the prompt, the lookup tool and the `offered` event.
  - `OfferMoment` exists because the evaluator and the lookup both read the turn's positions and messages.
  - `LookUpOfferCondition` is shared by the tool and the `offered` event's `offerWhenHeld`.
  - No generic rule engine: the leaves are exactly those the spec lists. PASS.
- **VIII. State Derivation During Render**: the builder derives descriptions, clamps and per-row errors during render; no new effects. PASS.

Post-design re-check: the design holds; no violations, so Complexity Tracking is not needed.

## Project Structure

### Documentation (this feature)

```text
specs/021-quest-offer-conditions/
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
│   ├── Quests/QuestConditions.php          # changed: new leaves, OfferMoment, `offer` scope latching
│   ├── Quests/QuestSessionState.php        # changed: feelings, ledgers, offer history
│   ├── Quests/OfferMoment.php              # new: the turn's giver, region, positions, messages (R7)
│   ├── Quests/DescribeCondition.php        # new: plain words (R8)
│   ├── Quests/LookUpOfferCondition.php     # new: a part's value and ask; whether offerWhen holds (R3)
│   ├── Quests/ValidateQuestDefinition.php  # changed: new leaves, scopes, ranges, offer-only keys
│   ├── Quests/FindQuestReferences.php      # changed: new leaves, `quests` kind
│   ├── Quests/StartQuestRun.php, EndQuestRun.php, WithdrawQuestOffers.php  # changed: QuestStateChanged
│   ├── BuildQuestsPrompt.php               # changed: offerWhen prose, offerQuestion status, discretion
│   └── TransferInventory.php               # changed: item_transfers rows, causes on PlayerInventoryChanged
├── Contracts/QuestTrigger.php              # changed: cause()
├── Events/Quests/
│   ├── QuestTriggerEvent.php               # changed: cause() returns null
│   ├── PlayerInventoryChanged.php          # changed: gaveTo, spentWith, cause
│   ├── ResidentFeelingsChanged.php         # new
│   └── QuestStateChanged.php               # new
├── Listeners/AdvanceQuests.php             # changed: causes in payloads, offerWhen latching
├── Jobs/JudgeQuestion.php                  # changed: `:offer` answers
├── Models/ItemTransfer.php                 # new
├── Models/ResidentFeeling.php              # changed: dispatch on adjust
├── Models/Quest.php                        # changed: offerWhen(), offerQuestion(), question(':offer')
├── Http/Controllers/Api/QuestController.php      # changed: refuse deleting a named quest
├── Http/Controllers/Api/QuestOfferController.php # changed: QuestStateChanged
├── Http/Controllers/Api/ConversationController.php  # changed: OfferMoment and the new tool in questTools
└── Services/AgentLoop/Tools/World/
    ├── CheckOfferConditionTool.php         # new
    ├── OfferQuestTool.php                  # changed: logs lookups and offerWhenHeld
    └── SignalQuestionTool.php              # changed: the giver's offerQuestion

database/migrations/xxxx_create_item_transfers_table.php, database/factories/ItemTransferFactory.php
database/factories/QuestFactory.php         # changed: offerWhen and offerQuestion states

resources/js/
├── utils/questConditionTypes.js            # changed: new types, descriptions, offerOnly
├── components/ConditionBuilder.jsx         # changed: new fields, descriptions, per-row errors
├── components/QuestEditor.jsx              # changed: Offer when, Offer question
└── components/QuestEventLog.jsx            # changed: causes and lookups

tests/Feature/QuestDefinitionValidationTest.php, QuestProgressTest.php, JudgeQuestionTest.php, TransferInventoryTest.php, Api/QuestControllerTest.php  # extended
tests/Feature/QuestOfferConditionsTest.php  # new: prompt, lookups, offers, moment leaves, offerQuestion
```

**Structure Decision**: The existing layout; quest actions stay under `app/Actions/Quests/`, triggers under `app/Events/Quests/`.

## Approved names

Approved in this session. Names from the spec (offerWhen, offerQuestion, the leaf keys, "a named resident", "no one besides the giver") are not repeated.

**UI labels**

| Name | Where | Refers to |
|---|---|---|
| "Offer when" | quest editor field, when the quest starts by offer | `start.offerWhen` |
| "Offer question" | quest editor field | `start.offerQuestion` |
| "The giver checks these and decides alone whether to offer." | help line under both | FR-020 |
| "Feeling", "Quest state", "Times declined", "Gave to", "Spent with", "Messages sent", "Giver in zone", "Others in the zone" | condition types | the eight leaves |
| "Romance", "Trust", "Liking"; "at least", "at most" | feeling fields | `kind`, bounds |
| "Offered", "Active", "Declined", "Abandoned" | quest state choices | `state` |
| "This quest" | quest pickers | the quest being edited |
| "Offer when only" | badge on moment rows | `offerOnly` |
| "Offered while these didn't hold:" | event log | `unmetParts` |

**Condition type descriptions** (shown under each row): "How the resident feels about the player right now.", "Where another quest stands.", "How many times the player turned down a quest's offer, walking away included.", "How many of an item the player has handed the resident this session.", "How many credits the player has paid the resident this session.", "How many messages the player has sent the resident this session.", "Where the giver is standing when the player talks to them.", "Who else is in the giver's zone when the player talks to them."

**LLM tool name**: `check_offer_condition`.

**Terms in code and docs**: offer moment (what only the current turn knows, research R7), lookup (one `check_offer_condition` call), moment leaves (`messagesWith`, `giverIn`, `othersInTheZone`), cause (a trigger's sentence for the log).

**Tables and classes**: `item_transfers` / `ItemTransfer`; `OfferMoment`, `DescribeCondition`, `LookUpOfferCondition`, `CheckOfferConditionTool`, `ResidentFeelingsChanged`, `QuestStateChanged`.

## Complexity Tracking

Not needed.
