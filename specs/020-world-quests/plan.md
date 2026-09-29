# Implementation Plan: Quests for Worlds

**Branch**: `claude/friendly-hypatia-j2gily` | **Date**: 2026-09-29 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/020-world-quests/spec.md`

## Summary

Worlds get quests: beats joined by requirements, conditions made of tracked checks, flags residents grant and questions a model judges, and endings a model writes against the author's rubric.

- **Schema**: `quests` and `campaigns` per world with a JSON definition; per session, `world_session_quests` (one row per run), the append-only `quest_events`, `quest_offers` and `world_session_campaigns` ([data-model.md](data-model.md)).
- **Triggers** (clarification Q1): travel, zone crossings, talking, activities, inventory changes, learned and acknowledged facts, flags and judged questions each dispatch an event. One `AdvanceQuests` listener latches event conditions and finishes beats, starts and ends quests. Nothing polls (research R3).
- **Residents**: a `quests` prompt section carries beat prose, grantable flags, questions to signal, quests to offer and the endings they remember in this session (R8). New tools: `grant_flag`, `signal_question`, `offer_quest` (R6, R7, R9).
- **Model calls**, queued: `JudgeQuestion` verifies a signalled question with cited messages (R7); `AssessQuestEnding` and `AssessCampaignEnding` write endings with a forced `record_ending` tool (R10, R11).
- **Creator mode**: tools to start, end, reset, finish or undo beats, set flags, re-assess and edit definitions, logged as the creator's (R13).
- **Author UI**: Quests and Campaigns sections on the world edit page, with a Form/JSON editor and a recursive condition builder fed by world data (R17).
- **Player UI**: tracker, beat notices, quest log on K, ending card with a details toggle, offer request and card, all live over a private Reverb channel (R12, R18); a quest event log on the sessions page.

## Technical Context

**Language/Version**: PHP 8.4; JavaScript (React 19, JSX)

**Primary Dependencies**: Laravel 13 (events, queued jobs, broadcasting), Reverb 1 and Laravel Echo (already installed), Pest 4, Ziggy 2; the existing agent loop, narrator model, inventory transfer and fact learning. No new dependencies.

**Storage**: PostgreSQL in tests, MySQL locally. Six new tables ([data-model.md](data-model.md)); no changed columns.

**Testing**:
- **Pest feature tests** for the configuration and play endpoints, the definition check, trigger-driven progress, the tools, the judged check, the assessments and creator mode, with factories and the LLM faked.
- **Manually**, following [quickstart.md](quickstart.md): the editor, HUD pieces, live updates and the judged scenes.

**Target Platform**: Desktop browsers via the existing SPA.

**Project Type**: Web application (Laravel backend + React frontend, single repo).

**Performance Goals**:
- A tracked trigger costs a few queries under one lock, with no model call; the tracker updates within 2 s (SC-003).
- A judged check is one model call per signal; an ending is one model call, shown within 30 s (SC-006).
- Prompts gain one short section.

**Constraints**:
- No code or migration names a world, region, resident or quest (FR-021).
- Hidden beats never reach the browser before they finish.
- Judged checks and assessments read stored messages with OOC and creator tags removed.
- Endings are remembered only through the session's quest data, never `long_term_memory` (R8).
- Existing worlds and sessions keep working with no quests.
- Every new UI piece follows the UI standard of [feature 1's tasks.md](../018-items-inventory-credits/tasks.md) (FR-022).

**Scale/Scope**: Tens of quests per world, a handful active per session, tens of beats per quest.
- **Backend**: about 55 new files (migrations, models, factories, enums, events, listener, jobs, actions, tools, controllers, requests) and about 15 changed.
- **Frontend**: about 14 new components and hooks, about 6 changed.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Lint-Enforced Code Style**: Pint and ESLint run once at push or PR time per CLAUDE.md. PASS.
- **II. Append-Only Migrations**: every table is a new migration; no existing migration changes. PASS.
- **III. Comments Justify Only Non-Obvious Decisions**: comments only for hidden constraints, such as why fail is checked before complete, why the log refuses updates, or why endings stay out of `long_term_memory`. PASS.
- **IV. Data Isolation by Ownership**:
  - Everything resolves through `$request->user()->worlds()`, then the world's quests, campaigns and the user's sessions.
  - Every id in a definition must belong to the same world (R14); the broadcast channel authorises only the session's owner.
  - Ending memory is scoped to the session (R8).
  - Feature tests cover another user's world, another world's references, another session's runs and offers, and the channel. PASS.
- **V. Errors Fail Loudly**:
  - Refused tools return the reason to the model.
  - Failed judged checks and assessments are logged as events, reported, and shown to the player with a way to retry.
  - Definition problems return 422 with every path. PASS.
- **VI. Feature-Test-First, Factory-Backed**: a factory for every new model, with states for runs (active, ended, with ending), definitions (with beats, questions, grants) and offers. PASS.
- **VII. No Speculative Abstraction**:
  - `QuestTrigger` exists because eleven real events feed one listener.
  - `AssessEnding` is shared because quests and campaigns are its two real callers.
  - No generic rule engine: the condition tree has exactly the leaves the spec lists. PASS.
- **VIII. State Derivation During Render**: notices, the tracker and the ending card derive from `useQuests` state during render; the channel subscription and initial fetch use effect-local closures, as `useAvatarBackground` does. PASS.

Post-design re-check: the design above holds; no violations, so Complexity Tracking is not needed.

## Project Structure

### Documentation (this feature)

```text
specs/020-world-quests/
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
│   ├── Quests/ValidateQuestDefinition.php      # new: shape, references, cycles (R14)
│   ├── Quests/QuestConditions.php              # new: evaluates a condition tree (R2)
│   ├── Quests/QuestSessionState.php            # new: the snapshot conditions read
│   ├── Quests/SyncSessionQuests.php            # new: creates available and auto runs (R4)
│   ├── Quests/RecordQuestEvent.php             # new: appends to the log and collects notices
│   ├── Quests/EndQuestRun.php                  # new: status, log, QuestEnded, assessment job
│   ├── Quests/ReconcileQuestRuns.php           # new: after a definition edit (R16)
│   ├── Quests/FindQuestReferences.php          # new: blocks deletions (R15)
│   ├── Quests/JudgeQuestionAction.php          # new: forced judgement call (R7)
│   ├── Quests/AssessEnding.php                 # new: forced record_ending call (R10, R11)
│   ├── Quests/PlayerRunView.php                # new: a run as the player sees it
│   ├── BuildQuestsPrompt.php                   # new: the quests prompt section (R8)
│   ├── CreatorModeTags.php                     # changed: withoutCommands
│   ├── TransferInventory.php, TravelThroughPassage.php, LearnFact.php  # changed: dispatch triggers
│   └── GenerateResidentConversationTurn.php    # changed: quests section
├── Contracts/QuestTrigger.php                  # new
├── Enums/QuestStatus.php, EndingStatus.php, QuestEventType.php, QuestOfferStatus.php
├── Events/Quests/                              # new: the eleven triggers (R3) and QuestsUpdated, QuestEndingReady broadcasts
├── Listeners/AdvanceQuests.php, CheckCampaignEnded.php, UnlockQuests.php
├── Jobs/JudgeQuestion.php, AssessQuestEnding.php, AssessCampaignEnding.php
├── Http/Controllers/Api/
│   ├── QuestController.php, CampaignController.php, QuestOptionsController.php   # new
│   ├── QuestPlayController.php, QuestOfferController.php                          # new
│   ├── ConversationController.php              # changed: quests section, tools, questOffer, PlayerTalkedTo
│   ├── ResidentDecisionController.php, ResidentActivityController.php, ActivityUseController.php  # changed
│   ├── WorldSessionController.php              # changed: sync on store and resume, zone crossings
│   └── ItemController.php, FactController.php, RegionController.php, WorldResidentController.php  # changed: 422 when referenced
├── Http/Requests/StoreQuestRequest.php, UpdateQuestRequest.php, StoreCampaignRequest.php, UpdateCampaignRequest.php
├── Models/Quest.php, Campaign.php, WorldSessionQuest.php, QuestEvent.php, QuestOffer.php, WorldSessionCampaign.php; World, WorldSession (changed)
└── Services/AgentLoop/Tools/World/
    ├── GrantFlagTool.php, SignalQuestionTool.php, OfferQuestTool.php        # new
    ├── JudgementTool.php, RecordEndingTool.php                              # new: forced
    └── Creator: StartQuestTool, EndQuestTool, ResetQuestTool, SetBeatTool, SetQuestFlagTool, AssessQuestTool, EditQuestTool  # new

routes/api.php, routes/channels.php             # new routes and the world-session channel
database/migrations/, database/factories/        # six tables, six factories

resources/js/
├── components/
│   ├── QuestsEditor.jsx                         # new: list, problems, delete warning
│   ├── QuestEditor.jsx                          # new: Form/JSON toggle, errors by path
│   ├── QuestBeatEditor.jsx                      # new: one beat, knowledge, grants, questions
│   ├── ConditionBuilder.jsx                     # new: recursive groups and rows with pickers
│   ├── RubricEditor.jsx                         # new: guidance, dimensions, tiers (quests and campaigns)
│   ├── CampaignsEditor.jsx                      # new
│   ├── QuestEventLog.jsx                        # new: on the sessions page
│   ├── world/hud/QuestTracker.jsx, BeatNotice.jsx, QuestLogPanel.jsx, EndingCard.jsx, QuestOfferCard.jsx  # new
│   ├── world/hud/ControlsLegend.jsx             # changed: K
│   └── world/WorldChat.jsx                      # changed: offer request line
├── hooks/useQuests.js, useQuestOptions.js       # new
└── pages/EditWorldPage.jsx, WorldPage.jsx, WorldSessionsPage.jsx  # changed

tests/Feature/Api/QuestControllerTest.php, CampaignControllerTest.php, QuestPlayControllerTest.php
tests/Feature/QuestDefinitionValidationTest.php, QuestProgressTest.php, QuestWorldToolsTest.php
tests/Feature/JudgeQuestionTest.php, AssessEndingTest.php, QuestCreatorModeTest.php
```

**Structure Decision**: The existing single Laravel + React repo layout. Quest-specific actions are grouped under `app/Actions/Quests/` and trigger events under `app/Events/Quests/`, because the feature adds about a dozen of each. Everything else sits beside the code it extends.

## Approved names

These names are used in the code, tables and UI. Names already in the spec's description (quest, beat, flag, rubric, tier, giver, epilogue, campaign, quest tracker, quest log, ending card, `record_ending`, and the condition keys it lists) are not repeated.

**UI labels**

| Name | Where | Refers to |
|---|---|---|
| "Quests", "Campaigns" | accordion titles on the world edit page | the two editors |
| "Key" | field label | a quest's or campaign's id that other quests name |
| "Starts" with "With the session", "On a condition", "Offered by" | field and choices | `start.mode` |
| "Requires" / "+ Quest outcome" | field and button | `requires` |
| "Can be played again" | toggle | `repeatable` |
| "Player text", "After", "Finishes when", "Hidden until finished" | beat fields | a beat's text, `requires`, `when`, `hidden` |
| "What residents know" | beat section | `knowledge` |
| "Flags residents grant" | beat section | `grants` |
| "Questions" | beat section | judged questions |
| "Completes when" / "Every beat is finished"; "Fails when" / "Never" | quest fields and defaults | `complete`, `fail` |
| "Ending", "Guidance", "Dimensions", "Tiers" | rubric section and fields | the rubric |
| "All of", "Any of", "None of", "+ Condition", "+ Group" | condition builder | combinators |
| "Enters region", "Enters zone", "Talks to", "Uses", "Resident uses", "Holds item", "Holds credits", "Knows fact", "Resident learned fact", "Flag", "Question met", "Beat finished" | condition types | the leaves in research R2 |
| "QUESTS" | quest log title; ControlsLegend label for K | the quest log |
| "BEAT COMPLETE", "QUEST STARTED", "QUEST COMPLETE", "QUEST FAILED", "QUEST ABANDONED" | beat notice | notice types |
| "A QUEST", "ACCEPT", "NOT NOW" | offer card | the offer |
| "[You accept "…"]", "[You decline "…" for now]" | chat line after answering | the giver is told |
| "ABANDON" and "Abandon this quest?" | quest log control and confirmation | abandoning |
| "THE ENDING IS BEING WRITTEN", "THE ENDING COULDN'T BE WRITTEN", "WRITE IT AGAIN" | ending card and quest log | ending states |
| "DETAILS" | ending card toggle | scores and reasons |
| "Quest log" | sessions page section | the event log |

**LLM tool names**: `grant_flag`, `signal_question`, `offer_quest`, `judgement`; creator turns: `start_quest`, `end_quest`, `reset_quest`, `set_beat`, `set_quest_flag`, `assess_quest`, `edit_quest`.

**Terms in code and docs**: run (one play of a quest in a session), trigger (an event quests listen for), latched condition (an event condition that stays met once it happens), question (a judged condition), signal (a resident's claim that a question is met), involved residents (who remember an ending, research R8).

**Tables and enums**: as in [data-model.md](data-model.md): `campaigns`, `quests`, `world_session_quests`, `quest_events`, `quest_offers`, `world_session_campaigns`; `QuestStatus`, `EndingStatus`, `QuestEventType`, `QuestOfferStatus`.

**Condition keys beyond the spec's list**: `question`, `beat`, and `flag` with `{ quest, name }` for another quest's resulting flags.

## Complexity Tracking

Not needed.
