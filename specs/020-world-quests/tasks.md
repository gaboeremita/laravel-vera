---
description: "Task list for Quests for Worlds"
---

# Tasks: Quests for Worlds

**Input**: Design documents from `/specs/020-world-quests/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/api.md](contracts/api.md), [quickstart.md](quickstart.md)

**Tests**: Included. Constitution VI requires factory-backed Pest feature tests for touched behaviour. Per CLAUDE.md, tests are written with each story, but Pint, ESLint and the test suite run once, in the final phase.

**Organization**: Tasks are grouped by user story, numbered as in [spec.md](spec.md): US1 writing a quest, US2 playing through it, US3 residents' part and grants, US4 judged questions, US5 endings, US6 offers and chains, US7 campaigns, US8 creator mode, US9 the quest log. Phases follow priority: the P1 stories (US1, US2, US3, US5) come first.

**UI standard (applies to every UI task)**: the one in [feature 1's tasks.md](../018-items-inventory-credits/tasks.md): HUD classes and entrance animations from `resources/css/app.css` (`world-hud-panel`, `world-hud-label`, `world-hud-key`, `world-hud-glow`, `world-pause-backdrop`, `hud-enter-*`), `Accordion`, `ConfirmationModal` and `Toggle` for configuration, theme tokens only, designed empty states. Labels and tool names use the approved names in [plan.md](plan.md).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: The user story the task belongs to

---

## Phase 1: Setup

**Purpose**: Create the files the later phases fill in.

- [X] T001 Create the migrations with `php artisan make:migration --no-interaction` in `database/migrations/`: `create_campaigns_table`, `create_quests_table`, `create_world_session_quests_table`, `create_quest_events_table`, `create_quest_offers_table`, `create_world_session_campaigns_table`, in that order
- [X] T002 [P] Create models with factories with `php artisan make:model <Name> --factory --no-interaction` for `Campaign`, `Quest`, `WorldSessionQuest`, `QuestEvent`, `QuestOffer`, `WorldSessionCampaign` in `app/Models/` and `database/factories/`
- [X] T003 [P] Create `app/Enums/QuestStatus.php` (`Available`, `Active`, `Completed`, `Failed`, `Abandoned`), `app/Enums/EndingStatus.php` (`Pending`, `Written`, `Failed`), `app/Enums/QuestOfferStatus.php` (`Pending`, `Accepted`, `Declined`, `Withdrawn`) and `app/Enums/QuestEventType.php` with the cases in [data-model.md](data-model.md)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The schema, models, the event log, the player's view of a run and the broadcast channel that every story uses.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T004 Implement the six migrations from T001 in `database/migrations/` per [data-model.md](data-model.md): JSON columns, cascades, `quests.campaign_id` null on delete, unique (`world_id`, `key`) on quests and campaigns, unique (`world_session_id`, `quest_id`, `run`), unique (`world_session_id`, `campaign_id`), and `quest_events` with `created_at` only
- [X] T005 [P] Implement `Quest` and `Campaign` (fillable, `definition` array cast, `world()`, `Quest::campaign()`, `Campaign::quests()`) in `app/Models/`, and `World::quests()` and `World::campaigns()` in `app/Models/World.php`
- [X] T006 [P] Implement `WorldSessionQuest` (enum, array and datetime casts; `quest()`, `worldSession()`, `events()`; `finishedBeats()`, `hasFlag(string)`, `isOpen()`), `QuestOffer`, `WorldSessionCampaign` in `app/Models/`, and `QuestEvent` with `UPDATED_AT = null` and `updating` and `deleting` hooks that throw, since the log is append-only (research R5); add `WorldSession::questRuns()`, `questOffers()` and `campaignEndings()` in `app/Models/WorldSession.php`
- [X] T007 [P] Implement the factories in `database/factories/` with states `autoStart()`, `offeredBy(WorldResident)`, `repeatable()`, `withBeats(array)` on `QuestFactory`; `active()`, `ended(QuestStatus)`, `withEnding()` on `WorldSessionQuestFactory`; `pending()` on `QuestOfferFactory`; and add `worldQuest(World $world, array $definition = [], array $attributes = [])` beside `worldFact()` in `tests/Pest.php`, building a valid definition from defaults
- [X] T008 [P] Implement `app/Actions/Quests/RecordQuestEvent.php`: appends a `quest_events` row (run, type, beat, payload, by creator) and returns it; the one way the log is written
- [X] T009 [P] Implement `app/Actions/Quests/PlayerRunView.php`: a run as the `RunView` of [contracts/api.md](contracts/api.md), leaving out hidden beats that aren't finished (research R12)
- [X] T010 [P] Add the `world-session.{sessionId}` channel to `routes/channels.php`, authorised when the session belongs to the user's `WorldUser`; implement `app/Events/Quests/QuestsUpdated.php` (`quests.updated`, `{ runs, notices }`) and `app/Events/Quests/QuestEndingReady.php` (`quests.ending`) as `ShouldBroadcastNow`, following `app/Events/AvatarBackgroundStatusUpdated.php`
- [X] T011 [P] Create `app/Contracts/QuestTrigger.php`: `sessionId(): int`, `leaf(): string` (the condition key it concerns) and `matches(array $leaf): bool` (research R3)

**Checkpoint**: Quests and runs can be stored and broadcast; stories can proceed.

---

## Phase 3: User Story 1 - Write a quest for a world (Priority: P1) 🎯 MVP

**Goal**: Quests are written in a form or as JSON, checked against the world on save, and protect what they name from deletion.

**Independent Test**: [quickstart.md](quickstart.md) step 1.

- [X] T012 [US1] Implement `app/Actions/Quests/ValidateQuestDefinition.php` per research R14: shape (including start modes, requires outcomes, unique beat, question, dimension and tier names), references (regions, zones from `layout.zones`, objects and activities from `Region::objectActivities`, residents, items, facts, beats, questions, other quests' and campaigns' keys, all in this world), beat cycles by depth-first search, and quest cycles across the world's quests with this definition in place; returns problems keyed by path and warnings for residents on `grants` or `questions` whose model can't call tools
- [X] T013 [P] [US1] Create `app/Http/Requests/SaveQuestRequest.php`, used by store and update like `SaveFactRequest`: `key` (pattern, ≤ 80, unique per world), `title` (≤ 120), `campaignId` (same world), `definition` checked with `ValidateQuestDefinition`, errors keyed by definition path
- [X] T014 [US1] Implement `app/Http/Controllers/Api/QuestController.php` (index with `sessionCount` and `problems` re-run per quest, store and update returning `warnings`, destroy) and `app/Http/Controllers/Api/QuestOptionsController.php` (the picker data of [contracts/api.md](contracts/api.md)), with their routes in `routes/api.php`
- [X] T015 [US1] Implement `app/Actions/Quests/FindQuestReferences.php` (quests naming a region, resident, item or fact) and return 422 `{ message, quests }` from `destroy` in `app/Http/Controllers/Api/ItemController.php`, `FactController.php`, `RegionController.php` and `NpcController.php` (an NPC whose placements a quest names), and when a resident is removed in `app/Http/Controllers/Api/WorldResidentController.php` (research R15)
- [X] T016 [P] [US1] Write `tests/Feature/QuestDefinitionValidationTest.php`: a valid quest passes; each missing reference is reported with its path; a beat cycle and a cross-quest cycle name their members; duplicate ids; bad start modes and outcomes; a tool-less resident on `grants` is a warning, not an error
- [X] T017 [P] [US1] Write `tests/Feature/Api/QuestControllerTest.php`: CRUD; 422 with every problem; `problems` appear after a zone or activity disappears from a layout; `quest-options` shape; deleting an item, fact, region or NPC, or removing a resident, that a quest names returns 422 naming the quest; another user's world 404
- [X] T018 [P] [US1] Implement `resources/js/hooks/useQuestOptions.js`: loads `worlds.quest-options` once per world with an effect-local closure and exposes it with a reload
- [X] T019 [P] [US1] Build `resources/js/components/ConditionBuilder.jsx` per research R17: a recursive group (All of, Any of, None of) of rows; each row picks a condition type from the approved labels, then its fields from `useQuestOptions` data (region → zone or object → activity, residents, items, facts, this quest's flags, questions and beats, other quests' keys and flags); remove buttons; `+ Condition` and `+ Group`; follow the UI standard
- [X] T020 [P] [US1] Build `resources/js/components/RubricEditor.jsx`: guidance, dimensions (name, description) and tiers as removable chips; shared by quests and campaigns; follow the UI standard
- [X] T021 [US1] Build `resources/js/components/QuestBeatEditor.jsx`: player text, "Hidden until finished", "After" (multi-select of this quest's other beats), "Finishes when" with `ConditionBuilder`, and collapsible "What residents know", "Flags residents grant" and "Questions" lists with resident pickers; follow the UI standard
- [X] T022 [US1] Build `resources/js/components/QuestEditor.jsx`: title, key, description, starts (with the session, on a condition with `ConditionBuilder`, offered by a resident), requires, "Can be played again", beats list with add and remove, completes and fails when, `RubricEditor`; a Form/JSON toggle styled like `resources/js/components/SchemaEditor.jsx` where switching to form mode is refused with the parse or shape error shown; server errors shown on the field their path names, and listed at the top when no field matches; warnings shown after saving; follow the UI standard
- [X] T023 [US1] Build `resources/js/components/QuestsEditor.jsx` (list with campaign, beat count and problems; `+ New quest`; delete through `ConfirmationModal` with `sessionCount`; empty state) and add it as the "Quests" accordion in `resources/js/pages/EditWorldPage.jsx`; make sure the 422 deletion refusals in the item, fact, region and resident editors show the quest names

**Checkpoint**: Authors write valid quests; nothing a quest names can be deleted.

---

## Phase 4: User Story 2 - Play through a quest (Priority: P1) 🎯 MVP

**Goal**: Triggers finish beats and start and end quests; the tracker and notices follow live.

**Independent Test**: [quickstart.md](quickstart.md) step 2.

- [X] T024 [US2] Implement `app/Actions/Quests/QuestSessionState.php` (a snapshot: the player's inventory, known facts, acknowledgements, and the session's runs by quest key) and `app/Actions/Quests/QuestConditions.php` per research R2: evaluates a condition tree against the snapshot and a run, reads latched event leaves from `state.seen` (keys hashed from the watched condition's path and the leaf), `flag` of other quests from their latest run's `resultingFlags`, `question` from `state.questions`, `beat` from `finishedBeats`; a reference that no longer exists never matches
- [X] T025 [US2] Implement `app/Actions/Quests/SyncSessionQuests.php` per research R4 (runs for quests with no open run: `auto` ones active with a `started` event and a `QuestStarted` dispatch on their first run, the rest available; requirements are added in US6) and call it in `store` and `resume` of `app/Http/Controllers/Api/WorldSessionController.php`; in `store`, after syncing, dispatch `PlayerEnteredRegion` for the spawn region and `PlayerEnteredZone` for each zone of the spawn position's zone chain
- [X] T026 [US2] Implement `app/Actions/Quests/EndQuestRun.php`: sets status, `ended_at` and `ending_status = Pending`, records the event, and dispatches `QuestEnded`; the assessment job is added in US5
- [X] T027 [US2] Implement the triggers in `app/Events/Quests/`, each implementing `QuestTrigger`: `PlayerEnteredRegion`, `PlayerEnteredZone`, `PlayerTalkedTo`, `PlayerUsedActivity`, `ResidentUsedActivity`, `PlayerInventoryChanged`, `FactLearned`, `FactAcknowledged`, `QuestFlagChanged`, `QuestQuestionJudged`, `QuestEnded`, and `QuestStarted`, which names one run
- [X] T028 [US2] Implement `app/Listeners/AdvanceQuests.php` per research R3: handles every `QuestTrigger` after commit under `Cache::lock("quests:{session}")->block(5, …)`, reporting and rethrowing a lock timeout; loads the session's available and active runs; latches matching event leaves of watched conditions; for `QuestStarted`, evaluates every watched condition of that run; evaluates fail then complete, then current beats repeatedly, then `start.when` of available `condition` quests (dispatching `QuestStarted` when one starts); records events, ends runs through `EndQuestRun`, and broadcasts `QuestsUpdated` with the changed runs and notices. Implement `app/Listeners/UnlockQuests.php`: on `QuestEnded`, runs `SyncSessionQuests`
- [X] T029 [US2] Dispatch the triggers: `PlayerEnteredRegion` in `app/Actions/TravelThroughPassage.php`; `PlayerEnteredZone` in `updatePosition` of `app/Http/Controllers/Api/WorldSessionController.php` once per zone in the new position's zone chain (`ResolveWorldState::locate`) that wasn't in the stored position's chain; `PlayerTalkedTo` after the reply in `sendMessage` of `app/Http/Controllers/Api/ConversationController.php` in world sessions; `PlayerUsedActivity` in `app/Http/Controllers/Api/ActivityUseController.php` when allowed; `ResidentUsedActivity` from a new `app/Actions/RecordResidentActivity.php` that creates the `ResidentActivity` row, used by both `store` in `app/Http/Controllers/Api/ResidentActivityController.php` and `recordDecision` in `app/Http/Controllers/Api/ResidentDecisionController.php`, when the activity names one; `PlayerInventoryChanged` in `app/Actions/TransferInventory.php` when either side is a player inventory; `FactLearned` in `app/Actions/LearnFact.php` when newly known; `FactAcknowledged` in `app/Services/AgentLoop/Tools/World/AcknowledgeTool.php` when the row is new
- [X] T030 [US2] Implement `app/Actions/Quests/ReconcileQuestRuns.php` per research R16 and call it from `update` in `app/Http/Controllers/Api/QuestController.php`
- [X] T031 [US2] Implement `index` in `app/Http/Controllers/Api/QuestPlayController.php` (`{ runs, campaigns }`) and its route per [contracts/api.md](contracts/api.md) in `routes/api.php`
- [X] T032 [P] [US2] Write `tests/Feature/QuestProgressTest.php`: an auto quest starts with the session; a first beat about an item the player already holds finishes the moment the quest starts; a beat about the spawn region or zone finishes at session start; entering a room inside a zone also enters the outer zone; a resident's activity from a decision finishes a `residentDid` beat; one trigger finishing beats in two quests records both and sends one notice per beat; travelling finishes an `enterRegion` beat and broadcasts a notice; moving within a zone finishes `enterZone` once; a beat waits for its requirements and finishes in the same pass once they are met; credits reaching 50 finish a beat that stays finished below 50; acknowledging a fact finishes its beat; fail wins over complete; completing with no `complete` condition; the log records every step and refuses updates; a quest added later reaches a session on resume; an edit drops removed beats; a hidden beat is absent from the player's view until finished; another session's runs are never touched
- [X] T033 [P] [US2] Implement `resources/js/hooks/useQuests.js`: fetches `worlds.sessions.quests.index`, subscribes to `world-session.{sessionId}` for `quests.updated` and `quests.ending` (effect-local closure, as `resources/js/hooks/useAvatarBackground.js` does), merges changed runs, and queues notices
- [X] T034 [P] [US2] Build `resources/js/components/world/hud/QuestTracker.jsx`: a HUD panel on the right under `CreditsReadout` listing up to three active quests with their current visible beats, then "+N more"; follow the UI standard
- [X] T035 [P] [US2] Build `resources/js/components/world/hud/BeatNotice.jsx`: a title card in the style of `ZoneTitleCard` showing one queued notice at a time ("BEAT COMPLETE", "QUEST STARTED", "QUEST COMPLETE", "QUEST FAILED", "QUEST ABANDONED"); follow the UI standard
- [X] T036 [US2] Wire `useQuests`, `QuestTracker` and `BeatNotice` into `resources/js/pages/WorldPage.jsx`, and call `persistPosition` from `handleLocationChange` when the zone changes, so zone crossings reach the server at once instead of on the 10-second save (research R3)

**Checkpoint**: Quests move forward as the player plays.

---

## Phase 5: User Story 3 - Residents play their part and grant progress (Priority: P1) 🎯 MVP

**Goal**: Named residents get beat prose while it is current, and grant the flags a beat allows.

**Independent Test**: [quickstart.md](quickstart.md) step 3.

- [X] T037 [US3] Implement `app/Actions/BuildQuestsPrompt.php` per research R8 with the knowledge and grants parts (the other parts come in US4, US5, US6 and US8), taking the session, the resident and the `TurnMode`; wording states the desired behaviour directly. Append its section as `quests` in `sendMessage` of `app/Http/Controllers/Api/ConversationController.php`, in `app/Http/Controllers/Api/ResidentDecisionController.php` and in `app/Actions/GenerateResidentConversationTurn.php`, beside `facts`
- [X] T038 [US3] Implement `app/Services/AgentLoop/Tools/World/GrantFlagTool.php` per research R6 (flag enum of current beats' grants for this resident; refuses the rest; sets the flag with who and why; records `FlagSet`; dispatches `QuestFlagChanged`) and add it in `sendMessage` only, when it has at least one flag to offer
- [X] T039 [P] [US3] Write `tests/Feature/QuestWorldToolsTest.php` (US3 cases, faked LLM): the prose is in the prompt only while its beat is current, in all three kinds of turn; a grant finishes its beat and is logged with the reason; a flag the beat doesn't allow, a resident it doesn't name, or a beat that isn't current is refused; decisions and conversations between residents don't get `grant_flag`

**Checkpoint**: Quests are about people.

---

## Phase 6: User Story 5 - Endings judged against the rubric (Priority: P1) 🎯 MVP

**Goal**: A model writes each ending; the player sees it; involved residents remember it in the session.

**Independent Test**: [quickstart.md](quickstart.md) step 5.

- [X] T040 [P] [US5] Implement `app/Services/AgentLoop/Tools/World/RecordEndingTool.php`, the forced `record_ending` tool per research R10, with `tier` and `dimension` as enums of the rubric's names when given
- [X] T041 [US5] Implement `app/Actions/Quests/AssessEnding.php` per research R10: one call on `ResolveNarratorModel` with the forced tool, reading the description, rubric, how the run ended, the event log with creator events marked, the flags, and excerpts of involved residents' conversations in the session between `started_at` and `ended_at` (last 40 each, OOC spans and creator tags removed); returns the ending
- [X] T042 [US5] Implement `app/Jobs/AssessQuestEnding.php` (3 tries; on success stores `ending`, `ending_status = Written`, records `EndingWritten`, broadcasts `QuestEndingReady`; in `failed()` sets `Failed`, records `EndingFailed` with the error, broadcasts, reports) and dispatch it from `app/Actions/Quests/EndQuestRun.php`
- [X] T043 [US5] Implement `abandon` and `assess` in `app/Http/Controllers/Api/QuestPlayController.php` and their routes per [contracts/api.md](contracts/api.md) in `routes/api.php`
- [X] T044 [US5] Add the endings part to `app/Actions/BuildQuestsPrompt.php`: title, tier and epilogue of this session's written endings for the residents involved (giver, residents named in the definition, residents who granted a flag or signalled a question in the run); nothing is written to `long_term_memory` (research R8)
- [X] T045 [P] [US5] Write `tests/Feature/AssessEndingTest.php` (faked LLM): the request carries rubric, log, creator marks and excerpts without OOC text; tiers and dimensions are enums; free-form ending without tiers; failure after retries leaves the status, records `EndingFailed`, and `assess` runs it again; abandoning ends and assesses; an involved resident's prompt carries the ending in the same session and not in another session
- [X] T046 [P] [US5] Build `resources/js/components/world/hud/EndingCard.jsx`: a full-screen overlay in the style of `HandoverRequestConfirm` with title, tier and epilogue, a "DETAILS" toggle for each score and reason, and the pending ("THE ENDING IS BEING WRITTEN") and failed ("THE ENDING COULDN'T BE WRITTEN", "WRITE IT AGAIN") states; follow the UI standard
- [X] T047 [US5] Show `EndingCard` in `resources/js/pages/WorldPage.jsx` when `useQuests` receives an ending, and post `assess` from it when retried

**Checkpoint**: MVP complete: quests are written, played, advanced by residents, and end with a judged ending.

---

## Phase 7: User Story 4 - Conditions judged from conversation (Priority: P2)

**Goal**: A named resident signals a question; a separate model verifies it with cited messages.

**Independent Test**: [quickstart.md](quickstart.md) step 4.

- [ ] T048 [P] [US4] Add `withoutCommands(string $content): string` to `app/Actions/CreatorModeTags.php`, removing `[creator mode: …]` commands the way `withoutActivations` removes activations
- [ ] T049 [P] [US4] Implement `app/Services/AgentLoop/Tools/World/JudgementTool.php`, the forced `judgement` tool (`met`, `messageIds`, `reason`)
- [ ] T050 [US4] Implement `app/Actions/Quests/JudgeQuestionAction.php` per research R7: the question and beat text, the conversation's last 30 stored messages prefixed by id, with OOC spans and creator commands removed, inside a block described as data; a yes without valid ids counts as no
- [ ] T051 [US4] Implement `app/Jobs/JudgeQuestion.php` (`ShouldBeUnique` per run and question): records `QuestionJudged` with the answer, ids and reason; on yes sets `state.questions[id]` and dispatches `QuestQuestionJudged`, unless the run is no longer active; a failure is recorded as a no with the error and reported
- [ ] T052 [US4] Implement `app/Services/AgentLoop/Tools/World/SignalQuestionTool.php` (question enum of current beats' questions naming this resident; records `QuestionSignalled`; dispatches `JudgeQuestion`), add it in `sendMessage` of `app/Http/Controllers/Api/ConversationController.php`, and add the questions part to `app/Actions/BuildQuestsPrompt.php`
- [ ] T053 [P] [US4] Write `tests/Feature/JudgeQuestionTest.php` (faked LLM): no check without a signal; a yes with valid ids finishes the beat; a no keeps it and allows another signal; ids outside the conversation count as no; OOC-only and creator-command text never reaches the request; a second signal while one is queued runs no second check; an answer after the quest ended changes nothing; an unnamed resident can't signal

**Checkpoint**: Conversations can finish beats, verified.

---

## Phase 8: User Story 6 - Quests offered by residents, and quests that chain (Priority: P2)

**Goal**: Givers offer quests in chat; the player accepts or declines on a card; quests require other quests' outcomes and can repeat.

**Independent Test**: [quickstart.md](quickstart.md) step 6.

- [ ] T054 [US6] Extend `app/Actions/Quests/SyncSessionQuests.php` with requirements (`ended`, `completed`, `failed`, `abandoned`, `tier:<name>` of the named quest's latest run) and repeatable runs per research R4 (a new available run after the latest ends; later runs of an `auto` quest start available)
- [ ] T055 [US6] Implement `app/Services/AgentLoop/Tools/World/OfferQuestTool.php` per research R9, add it in `sendMessage` of `app/Http/Controllers/Api/ConversationController.php` with `questOffer` in the response, and add the offers part to `app/Actions/BuildQuestsPrompt.php`
- [ ] T056 [US6] Implement `app/Http/Controllers/Api/QuestOfferController.php` (`answer` returning the line and run, dispatching `QuestStarted` on accept, 409 when not pending; `withdraw` for a conversation) and their routes per [contracts/api.md](contracts/api.md) in `routes/api.php`; withdraw pending offers in `resume` of `app/Http/Controllers/Api/WorldSessionController.php`
- [ ] T057 [P] [US6] Write `tests/Feature/Api/QuestPlayControllerTest.php` (US6 cases): only the giver can offer, and only an available quest; accept starts it and returns the line; decline keeps it available; withdraw on chat close and resume; a quest requiring `tier:triumph` stays unavailable after another tier; a repeatable quest gets a new run that keeps the old run's log and ending; a non-repeatable one doesn't; condition-start quests start when their condition holds; another session 404
- [ ] T058 [P] [US6] Build `resources/js/components/world/hud/QuestOfferCard.jsx` modeled on `resources/js/components/world/hud/HandoverRequestConfirm.jsx`: "A QUEST", title, description, giver, first visible beats, "ACCEPT" (Enter) and "NOT NOW" (Esc); follow the UI standard
- [ ] T059 [US6] Show the offer as a request line in `resources/js/components/world/WorldChat.jsx` that opens `QuestOfferCard`; on answer send the returned line as the player's next message, as handover answers do; withdraw pending offers where pending handover requests are cancelled; wire it in `resources/js/pages/WorldPage.jsx`

**Checkpoint**: Quests are offered in the story and chain.

---

## Phase 9: User Story 7 - Campaigns (Priority: P2)

**Goal**: Quests group into campaigns with their own ending.

**Independent Test**: [quickstart.md](quickstart.md) step 7.

- [ ] T060 [P] [US7] Create `app/Http/Requests/StoreCampaignRequest.php` and `UpdateCampaignRequest.php`: key, title, definition (description and a rubric with at least one dimension), `questIds` of this world, 422 when a quest is in another campaign
- [ ] T061 [US7] Implement `app/Http/Controllers/Api/CampaignController.php` and its routes per [contracts/api.md](contracts/api.md) in `routes/api.php`; accept `{ campaign, outcome }` requirements in `app/Actions/Quests/ValidateQuestDefinition.php` and `app/Actions/Quests/SyncSessionQuests.php`
- [ ] T062 [US7] Implement `app/Listeners/CheckCampaignEnded.php` and `app/Jobs/AssessCampaignEnding.php` per research R11, reusing `app/Actions/Quests/AssessEnding.php` with the campaign's rubric and its quests' endings as evidence; add campaigns to `index` in `app/Http/Controllers/Api/QuestPlayController.php`
- [ ] T063 [P] [US7] Write `tests/Feature/Api/CampaignControllerTest.php` (CRUD, one campaign per quest, another world's quests refused) and campaign cases in `tests/Feature/AssessEndingTest.php` (assessed once after the last quest; a repeatable quest counts after its first run; tier from the campaign's list; a later quest can require the campaign's outcome)
- [ ] T064 [US7] Build `resources/js/components/CampaignsEditor.jsx` (title, key, description, quest multi-select, `RubricEditor`, delete through `ConfirmationModal`, empty state) and add it as the "Campaigns" accordion in `resources/js/pages/EditWorldPage.jsx`; show campaign endings with `EndingCard`; follow the UI standard

**Checkpoint**: Chained quests become one story.

---

## Phase 10: User Story 8 - Creator mode for quests (Priority: P2)

**Goal**: Creator turns steer quests; every step is marked as the creator's.

**Independent Test**: [quickstart.md](quickstart.md) step 8.

- [ ] T065 [P] [US8] Implement the creator tools in `app/Services/AgentLoop/Tools/World/` per research R13: `StartQuestTool` and `ResetQuestTool` (both dispatch `QuestStarted`; a reset also deletes its campaign's `world_session_campaigns` row so the campaign is assessed again, research R11), `EndQuestTool`, `SetBeatTool` (undo cascades to beats that require it and dispatches `QuestStarted` so the undone beats are re-checked), `SetQuestFlagTool`, `AssessQuestTool`, `EditQuestTool` (checked by `ValidateQuestDefinition`, then `ReconcileQuestRuns`); each records its events with `by_creator` and dispatches the same events as play
- [ ] T066 [US8] On creator turns in world sessions, add the tools of T065 in `sendMessage` of `app/Http/Controllers/Api/ConversationController.php` and the creator part (every quest's status, beats and flags) to `app/Actions/BuildQuestsPrompt.php`
- [ ] T067 [P] [US8] Write `tests/Feature/QuestCreatorModeTest.php` (faked LLM): each tool's effect with `by_creator`; undo cascades; reset keeps earlier log rows; an invalid edit is refused with the configuration's messages; creator events reach the assessment marked; no quest tools without active creator mode

**Checkpoint**: Quests are quick to test.

---

## Phase 11: User Story 9 - The quest log (Priority: P3)

**Goal**: The player's quest log and the owner's quest event log.

**Independent Test**: [quickstart.md](quickstart.md) step 9.

- [ ] T068 [US9] Implement `questEvents` in `app/Http/Controllers/Api/QuestPlayController.php` and its route per [contracts/api.md](contracts/api.md) in `routes/api.php`
- [ ] T069 [P] [US9] Extend `tests/Feature/Api/QuestPlayControllerTest.php` (US9 cases): the log is oldest first with creator marks; empty states; another user's session 404
- [ ] T070 [P] [US9] Build `resources/js/components/world/hud/QuestLogPanel.jsx` in the style of `LearnedFactsPanel`: "QUESTS" title, campaigns grouping their quests, each quest's latest run with finished and current visible beats or its ending, earlier runs under it, "ABANDON" with "Abandon this quest?" confirmation, "WRITE IT AGAIN" for failed endings, keyboard navigation, empty state; follow the UI standard
- [ ] T071 [US9] Open `QuestLogPanel` with K in `resources/js/pages/WorldPage.jsx` and add K to `resources/js/components/world/hud/ControlsLegend.jsx`
- [ ] T072 [US9] Build `resources/js/components/QuestEventLog.jsx` (each event with quest, run, beat, type, payload summary, creator mark, time; empty state) and show it per session under its approved label "Quest log" in `resources/js/pages/WorldSessionsPage.jsx` beside `RevealLog`; this is the author's quest event log, separate from the player's `QuestLogPanel`; follow the UI standard

**Checkpoint**: All stories complete.

---

## Phase 12: Polish & Cross-Cutting Concerns

- [ ] T073 Add the `quests` prompt section to the prompt-section table in `ARCHITECTURE.md` and describe triggers, runs, judged questions and endings beside facts and inventory
- [ ] T074 Review every new and changed screen against the UI standard: spacing, alignment, animations, empty states, long titles and epilogues, keyboard use, and the look next to existing HUD pieces and editors; search the new code and migrations for any hardcoded world, region, resident or quest (FR-021); fix what doesn't match
- [ ] T075 Run the quality gates once: `vendor/bin/pint --dirty --format agent`, `npm run lint`, `php artisan test --compact`; fix everything that surfaces
- [ ] T076 Hand the user the [quickstart.md](quickstart.md) walkthrough, including the judged scenes of SC-005 and the timing goals of SC-003 and SC-006

---

## Dependencies & Execution Order

- **Setup (Phase 1)** → **Foundational (Phase 2)** → stories.
- **US1** first: every other story needs a quest to act on.
- **US2** after US1. **US3** and **US5** after US2.
- **US4** after US3 (it extends `BuildQuestsPrompt` and the tool wiring).
- **US6** after US5 (requirements read endings' tiers).
- **US7** after US5 and US6 (campaign endings and requirements).
- **US8** after US5; T065's reset of campaign endings after T062.
- T025, T028, T056 and T065 dispatch `QuestStarted` (T027); T029's `RecordResidentActivity` replaces the direct `ResidentActivity::create` in both callers.
- **US9** after US5; its abandon control uses T043.
- T029, T037, T038, T052, T055 and T066 all edit `sendMessage`; run them in that order.
- T037, T044, T052, T055 and T066 all extend `BuildQuestsPrompt`; run them in that order.
- **Polish** last.

## Parallel Examples

- **Phase 1**: T002 and T003 in parallel after T001.
- **Phase 2**: T005–T011 in parallel after T004.
- **US1**: T013, T016, T017 in parallel after T012; T018, T019, T020 in parallel.
- **US2**: T032–T035 in parallel after T031.
- **US5**: T040 in parallel with T041's setup; T045 and T046 in parallel after T044.
- **US4**: T048 and T049 in parallel.
- **US6**: T057 and T058 in parallel after T056.
- **US7**: T060 in parallel with T062's listener.
- **US9**: T069 and T070 in parallel after T068.

## Implementation Strategy

1. **MVP**: Phases 1–6 (US1, US2, US3, US5): quests are written, advanced by triggers and resident grants, and end with a judged ending. Validate with quickstart steps 1, 2, 3 and 5.
2. **Open conditions**: US4, judged questions.
3. **World integration**: US6 offers and chains, then US7 campaigns.
4. **Tools**: US8 creator mode, then US9 logs.
5. **Polish**: docs, the UI review, then the gates, once.
