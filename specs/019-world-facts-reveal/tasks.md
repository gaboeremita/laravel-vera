---
description: "Task list for Facts and Reveal Safeguards"
---

# Tasks: Facts and Reveal Safeguards

**Input**: Design documents from `/specs/019-world-facts-reveal/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/api.md](contracts/api.md), [quickstart.md](quickstart.md)

**Tests**: Included. Constitution VI requires factory-backed Pest feature tests for touched behaviour. Per CLAUDE.md, tests are written with each story, but Pint, ESLint and the test suite run once, in the final phase.

**Organization**: Tasks are grouped by user story, numbered as in [spec.md](spec.md): US1 giving residents facts, US2 guarding and revealing, US3 relaying, US4 OOC turns, US5 creator mode, US6 creator commands, US7 learned facts, US8 items and activities, US9 reveal log.

**UI standard (applies to every UI task)**: the one in [feature 1's tasks.md](../018-items-inventory-credits/tasks.md): HUD classes and entrance animations from `resources/css/app.css`, `Accordion`, `ConfirmationModal` and `Toggle` for configuration, theme tokens only, designed empty states. Labels and tool names use the approved names in [plan.md](plan.md).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: The user story the task belongs to

---

## Phase 1: Setup

**Purpose**: Create the files the later phases fill in.

- [X] T001 Create the migrations with `php artisan make:migration --no-interaction` in `database/migrations/`: `create_facts_table`, `create_fact_relays_table`, `create_known_facts_table`, `create_fact_acknowledgements_table`, `create_reveal_attempts_table`, `add_review_reveals_to_worlds_table`, `add_creator_mode_at_to_conversations_table`, `add_creator_password_to_users_table`, `add_by_creator_to_credit_transactions_table`, `add_reveals_fact_id_to_items_table`, `add_reveals_fact_id_to_activity_terms_table`, in that order
- [X] T002 [P] Create models with factories with `php artisan make:model <Name> --factory --no-interaction` for `Fact`, `KnownFact`, `FactAcknowledgement`, `RevealAttempt` in `app/Models/` and `database/factories/`
- [X] T003 [P] Create `app/Enums/RevealSource.php` (`InCharacter`, `OocTurn`, `Creator`, `Item`, `Activity`)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The schema, models and shared actions every story uses.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T004 Implement the eleven migrations from T001 in `database/migrations/` per [data-model.md](data-model.md) (cascades, null on delete, unique indexes, `fact_relays` composite key, `review_reveals` default true)
- [X] T005 [P] Implement `Fact` (fillable, `holder()` to `WorldResident`, `relays()` belongs-to-many `WorldResident` through `fact_relays`) in `app/Models/Fact.php`, and `WorldResident::facts()` in `app/Models/WorldResident.php`
- [X] T006 [P] Implement `KnownFact`, `FactAcknowledgement` and `RevealAttempt` (source enum cast, `fact()`, `worldSession()`, `worldResident()` where they apply) in `app/Models/`, and `WorldSession::knownFacts()`, `WorldSession::factAcknowledgements()`, `WorldSession::revealAttempts()` in `app/Models/WorldSession.php`
- [X] T007 [P] Add `review_reveals` (fillable, boolean cast) to `app/Models/World.php`; `creator_mode_at` (fillable, datetime cast) to `app/Models/Conversation.php`; `creator_password` (`hashed` cast, hidden) to `app/Models/User.php`; `by_creator` to `app/Models/CreditTransaction.php`; `reveals_fact_id` and `revealsFact()` to `app/Models/Item.php` and `app/Models/ActivityTerms.php`
- [X] T008 [P] Implement the factories with states `withRelays()`, `known()`, `creatorMode()` where they apply, in `database/factories/`, and add `worldFact(WorldResident $holder, array $attributes = [])` beside `worldItem()` in `tests/Pest.php`
- [X] T009 [P] Add `hasOutOfCharacter(string $text): bool` and `stripOutOfCharacter(string $text): string` to `app/Services/LlmResponseTagParser.php`, using the `[ooc: …]` grammar it already recognises (research R7)
- [X] T010 [P] Extract `app/Actions/ResolveNarratorModel.php` from the private `llm()` in `app/Actions/Narrate.php` (world narrator model, then the default, else `NarratorUnavailable`) and make `Narrate` use it; `tests/Feature/NarrateTest.php` keeps passing unchanged
- [X] T011 Implement `app/Actions/LearnFact.php`: records a `known_facts` row (source, source name, summary) unless one exists, writes the `reveal_attempts` row it is given, and returns the `KnownFact` payload of [contracts/api.md](contracts/api.md) when the fact is newly known

**Checkpoint**: Facts can be stored and learned; stories can proceed.

---

## Phase 3: User Story 1 - Give residents facts (Priority: P1) 🎯 MVP

**Goal**: Facts on residents in the region editor, with relay residents, refused for residents without tool calling.

**Independent Test**: [quickstart.md](quickstart.md) step 1.

- [X] T012 [P] [US1] Create `SaveFactRequest` in `app/Http/Requests/`: `topic` (≤ 120, unique per resident), `content`, `disclosure` required; `relayResidentIds` residents of the same world, never the holder; refuses when the holder's model, for the requesting user, doesn't support tools, with the message of research R12 (FR-002)
- [X] T013 [US1] Implement `app/Http/Controllers/Api/FactController.php` (index with `usage` and `toolsUnsupported` when the resident's model no longer supports tools, store, update syncing relays, destroy) and its routes per [contracts/api.md](contracts/api.md) in `routes/api.php`
- [X] T014 [P] [US1] Write `tests/Feature/Api/FactControllerTest.php`: CRUD, duplicate topic, relay outside the world or equal to the holder, refused tool-less holder with the reason, `usage` counts sessions, deleting cascades known facts and nulls log rows, removing a resident deletes their facts, another user's world 404
- [X] T015 [US1] Build `resources/js/components/ResidentFactsEditor.jsx`: the resident's facts as accordion rows (topic, content, disclosure, a multi-select of other residents who can act on it), add, save, delete through `ConfirmationModal` with the `usage` count, the refusal reason and `toolsUnsupported` shown inline, and an empty state; follow the UI standard
- [X] T016 [US1] Add the facts section to each resident row in `resources/js/components/WorldResidentsEditor.jsx`, and show the facts' usage in the confirmation before removing a resident who has facts

**Checkpoint**: Residents hold facts.

---

## Phase 4: User Story 2 - Characters guard and reveal facts (Priority: P1) 🎯 MVP

**Goal**: Holders know only topics and prose; `reveal` returns the content after the review; every attempt is logged.

**Independent Test**: [quickstart.md](quickstart.md) steps 2 and 3.

- [X] T017 [US2] Implement `app/Actions/BuildFactsPrompt.php` for holders per the table in research R2: facts the player doesn't know get topic and prose; facts learned from this holder add the content to speak of freely; facts learned elsewhere add the content and the source name, to talk about once the player brings it up. Wording states the desired behaviour directly. Takes the session, the resident and a turn mode (`InCharacter`, `OocTurn`, `Creator`, `BetweenResidents`) so later stories extend it
- [X] T018 [P] [US2] Implement `app/Services/AgentLoop/Tools/World/VerdictTool.php`, the forced `verdict` tool (`approved`, `verdict`), following `NarrateTool`
- [X] T019 [US2] Implement `app/Actions/ReviewReveal.php` per research R4: one call on `ResolveNarratorModel` with the forced `verdict` tool, reading the prose, topic, reason, region and zone, the holder's recent `messages.expression`, the conversation's `long_term_memory`, both sides' holdings (`Narrate::holdings`), the player's credit transactions to the holder, and the last stored messages with OOC spans removed inside a block described as data; any failure returns a rejection with the failure as the verdict and is reported
- [X] T020 [US2] Implement `app/Services/AgentLoop/Tools/World/RevealTool.php` per research R3: enum of the holder's topics, refuses facts not held, one review per fact per turn, skips the review when the player already knows it or the world's `review_reveals` is off, logs every attempt through `LearnFact`, returns `revealed` with the content or `not_now` with "It doesn't feel like the right moment yet."; collects the facts learned this turn
- [X] T021 [US2] Wire the holder side into `sendMessage` in `app/Http/Controllers/Api/ConversationController.php`: in world sessions, append the `facts` section and add `RevealTool` only when the resident's model supports tools; a resident without tool support gets neither (research R12)
- [X] T022 [P] [US2] Write `tests/Feature/ReviewRevealTest.php` (faked LLM): the request carries prose, reason, stored conversation without OOC spans, memory, holdings and credits, and never the content; approval and rejection; a missing verdict or model rejects with the failure recorded
- [X] T023 [P] [US2] Write `tests/Feature/FactWorldToolsTest.php` (US2 cases, faked LLM): the system prompt carries topic and prose and never the content; approved reveal returns the content and records known fact and log; rejected reveal returns `not_now` and records only the log; a second reveal of a rejected fact in the same turn makes no new review; review off approves unreviewed; a fact learned elsewhere gives the holder content and source; a fact not held is refused

**Checkpoint**: Secrets are earned in the story. MVP complete.

---

## Phase 5: User Story 3 - Relay facts to other characters (Priority: P2)

**Goal**: Relay residents know the topic, get the content once the player knows it, and acknowledge only real knowledge.

**Independent Test**: [quickstart.md](quickstart.md) step 4.

- [X] T024 [US3] Extend `app/Actions/BuildFactsPrompt.php` for relay residents per research R2: the topic and that they want to find out, in every mode; the content and "the user has learned this; when they tell you, you can act on it" once the player knows it
- [X] T025 [US3] Implement `app/Services/AgentLoop/Tools/World/AcknowledgeTool.php` per research R5 and add it in `sendMessage` in `app/Http/Controllers/Api/ConversationController.php` when the resident relays a fact the player knows
- [X] T026 [P] [US3] Extend `tests/Feature/FactWorldToolsTest.php` (US3 cases): relay prompt has the topic and no content before the player knows it, the content after; acknowledgement recorded once; refused when the player doesn't know the fact

**Checkpoint**: Facts travel through the player.

---

## Phase 6: User Story 4 - Out-of-character turns (Priority: P2)

**Goal**: OOC turns give holders the content and skip the review; resident-only turns never bypass.

**Independent Test**: [quickstart.md](quickstart.md) step 5.

- [X] T027 [US4] Detect OOC turns in `sendMessage` in `app/Http/Controllers/Api/ConversationController.php` with `hasOutOfCharacter()` on the player's latest message, and pass `OocTurn` to `BuildFactsPrompt` and `RevealTool` (content in the prompt; reveals approved unreviewed, logged with source `OocTurn`)
- [X] T028 [US4] Use the `BetweenResidents` mode in `app/Actions/GenerateResidentConversationTurn.php` (holders: topics and prose; relay residents: topics; no `reveal` or `acknowledge`), and give `app/Http/Controllers/Api/ResidentDecisionController.php` no facts section (FR-012, FR-012a)
- [X] T029 [P] [US4] Extend `tests/Feature/FactWorldToolsTest.php` (US4 cases): OOC turn prompt carries the content and the history keeps the OOC text; OOC reveal is unreviewed with source `OocTurn`; a resident conversation turn carries topics only and offers neither tool

**Checkpoint**: The player can step out of the story.

---

## Phase 7: User Story 5 - Creator mode, verified by the server (Priority: P2)

**Goal**: A hashed per-user password activates creator mode per conversation; the password is never stored, sent or bundled.

**Independent Test**: [quickstart.md](quickstart.md) step 6, up to the command.

- [X] T030 [P] [US5] Implement `app/Http/Controllers/Api/CreatorPasswordController.php` (`show` → `{ isSet }`, `update` sets or clears) with an `UpdateCreatorPasswordRequest` (8–200 characters or null) in `app/Http/Requests/`, and its routes per [contracts/api.md](contracts/api.md) in `routes/api.php`
- [X] T031 [US5] Implement `app/Actions/CreatorModeTags.php` per research R9: finds `[creator mode: "…"]` activations and `[creator mode: …]` commands in a message, and returns the message with activations removed (or replaced by `[creator mode]` on success)
- [X] T032 [US5] Apply creator mode in `sendMessage` in `app/Http/Controllers/Api/ConversationController.php`: clean activations from every user message in the incoming history before anything is stored or sent; verify with `Hash::check` and set `creator_mode_at`; return `userContent` and `creatorMode`; after a failed activation with nothing left, return without a reply; exclude `secret trigger` always and `creator mode` unless active; when a message carries an activation and a command, check the activation first so the command applies on the same turn
- [X] T033 [US5] Apply the same `creator mode` rule to `reactToGeneratedImage` and `reactToBackgroundChange` in `app/Http/Controllers/Api/ConversationController.php` (same conversation), and exclude `creator mode` and `secret trigger` where there is no player conversation or the channel is public: `sendDiscordMessage` in `app/Http/Controllers/Api/ConversationController.php`, `app/Actions/GenerateResidentConversationTurn.php`, `app/Http/Controllers/Api/ResidentDecisionController.php`, `app/Services/ImageGenProviders/ImageGenPromptEnhancer.php`, `app/Services/AvatarBackground/AvatarBackgroundPromptEnhancer.php`
- [X] T034 [US5] In `resources/js/hooks/useConversationChat.js`, delete `CREATOR_MODE_TRIGGER`; replace the local copy of the sent message with `userContent`; refetch emotions when `creatorMode.active` turns on; show `creatorMode.notice` through the existing toast; handle a response with no reply; pass `learnedFacts` and `inventory` from the response to caller callbacks so `WorldChat` can forward them
- [X] T035 [US5] Add the creator password section to `resources/js/pages/SettingsPage.jsx`: whether one is set, a password field to set or change it, a clear action, saying it applies to every assistant; follow the UI standard
- [X] T036 [P] [US5] Write `tests/Feature/Api/CreatorPasswordControllerTest.php`: set, change, clear, stored hashed, never returned
- [X] T037 [P] [US5] Write `tests/Feature/CreatorModeTest.php` (US5 cases, faked LLM): right password activates and persists on the conversation; wrong password and no password stay off with the notice; the stored message and every message sent to the model have no password; another conversation, including another resident's, stays off; `creator mode` section only while active; `secret trigger` never

**Checkpoint**: The password lives only as a hash on the server.

---

## Phase 8: User Story 6 - Creator commands (Priority: P2)

**Goal**: Creator turns get unscoped tools and the admin tools; their actions count and are marked as the creator's.

**Independent Test**: [quickstart.md](quickstart.md) step 6, the command.

- [X] T038 [P] [US6] Implement `app/Services/AgentLoop/Tools/World/SetFactKnownTool.php` (every fact of the world as "holder: topic"; known writes through `LearnFact` with source `Creator` and the fact's content as summary; unknown deletes the row and logs it per [data-model.md](data-model.md))
- [X] T039 [P] [US6] Implement `app/Services/AgentLoop/Tools/World/GrantTool.php` and `RemoveTool.php` (holder enum of "the user" and the world's residents; credits and items through `TransferInventory` with a null side; `by_creator` on the credit transaction), passing `by_creator` through an added parameter of `app/Actions/TransferInventory.php`
- [X] T040 [US6] On creator turns in `sendMessage` in `app/Http/Controllers/Api/ConversationController.php`: build the `WorldToolbox` without zone access, post restriction or activity gate (`app/Services/AgentLoop/Tools/World/WorldToolbox.php` as needed); give `RevealTool` and `BuildFactsPrompt` the `Creator` mode (every fact, no review); add the three tools of T038 and T039
- [X] T041 [P] [US6] Extend `tests/Feature/CreatorModeTest.php` (US6 cases): creator reveal of a fact not held is known with source `Creator`; grant of 100 credits marks `by_creator`; unknown removes the known fact; a command without active creator mode adds no tools; an activation and a command in one message apply on the same turn

**Checkpoint**: Creator mode steers the world.

---

## Phase 9: User Story 7 - What the player has learned (Priority: P3)

**Goal**: A HUD panel of learned facts with summaries, and a toast for each new one.

**Independent Test**: [quickstart.md](quickstart.md) step 3, the panel.

- [X] T042 [US7] Implement `app/Actions/SummarizeLearnedFact.php` per research R13 (one call on `ResolveNarratorModel`; "You haven't heard the details yet." when the reply tells nothing; falls back to the in-story reply, reported, on failure) and call it in `sendMessage` in `app/Http/Controllers/Api/ConversationController.php` after the reply, for each fact the `RevealTool` made known this turn, storing the summary and returning `learnedFacts`
- [X] T043 [US7] Implement `index` for known facts in `app/Http/Controllers/Api/FactPlayController.php` and its route per [contracts/api.md](contracts/api.md) in `routes/api.php`
- [X] T044 [P] [US7] Write `tests/Feature/Api/FactPlayControllerTest.php` (US7 cases): known facts newest first with summary and source; empty list; `learnedFacts` in the message response with the summary written from the reply; a reply that tells nothing gets the fixed sentence; another session 404
- [X] T045 [P] [US7] Implement `resources/js/hooks/useKnownFacts.js`: holds the session's known facts, fetches them (effect-local closure), accepts `learnedFacts` from any response and raises a toast for each
- [X] T046 [P] [US7] Build `resources/js/components/world/hud/LearnedFactsPanel.jsx`: a HUD overlay opened by a key, entries with topic, source and summary, newest first, keyboard navigable like `InventoryPanel`, empty state; follow the UI standard
- [X] T047 [US7] Wire `useKnownFacts` and `LearnedFactsPanel` into `resources/js/pages/WorldPage.jsx`, pass `learnedFacts` from `resources/js/components/world/WorldChat.jsx`, and add the key to `resources/js/components/world/hud/ControlsLegend.jsx`

**Checkpoint**: The player keeps track of the story.

---

## Phase 10: User Story 8 - Items and activities that reveal facts (Priority: P3)

**Goal**: Examining an item or succeeding at an activity makes its linked fact known.

**Independent Test**: [quickstart.md](quickstart.md) step 7.

- [X] T048 [US8] Accept and return `revealsFactId` (a fact of the same world) in `app/Http/Requests/SaveItemRequest.php`, `app/Http/Requests/UpdateActivityTermsRequest.php`, `app/Http/Controllers/Api/ItemController.php` and `app/Http/Controllers/Api/ActivityTermsController.php`
- [X] T049 [US8] On examine in `app/Http/Controllers/Api/ItemUseController.php` and on a successful player use in `app/Actions/UseActivity.php` (through `app/Http/Controllers/Api/ActivityUseController.php`): add the linked fact's content to the narrator's situation, learn it through `LearnFact` with source `Item` or `Activity` and the narration as summary, and return `learnedFacts`
- [X] T050 [P] [US8] Extend `tests/Feature/Api/FactPlayControllerTest.php` (US8 cases, faked LLM): examining a linked item learns the fact with the narration as summary; a failed activity learns nothing; a fact of another world in `revealsFactId` returns 422
- [X] T051 [US8] Add a fact picker to `resources/js/components/ItemsEditor.jsx` and `resources/js/components/ActivityTermsEditor.jsx` (facts of the world grouped by holder, with a none option), and pass `learnedFacts` from item and activity responses to `useKnownFacts`; follow the UI standard

**Checkpoint**: Secrets can be found in the world.

---

## Phase 11: User Story 9 - Review the reveal log (Priority: P3)

**Goal**: The world owner reads every reveal attempt of a session.

**Independent Test**: [quickstart.md](quickstart.md) step 2, the log.

- [X] T052 [US9] Implement `revealAttempts` in `app/Http/Controllers/Api/FactPlayController.php` and its route per [contracts/api.md](contracts/api.md) in `routes/api.php`
- [X] T053 [P] [US9] Extend `tests/Feature/Api/FactPlayControllerTest.php` (US9 cases): attempts oldest first with every field; entries kept with their copied names after the fact is deleted; empty log; another user's session 404
- [X] T054 [US9] Build `resources/js/components/RevealLog.jsx` (each attempt with holder, topic, source, reason, verdict, approved or rejected, reviewed or not, time; empty state) and open it per session from `resources/js/pages/WorldSessionsPage.jsx`; follow the UI standard
- [X] T055 [US9] Add the review toggle to `resources/js/components/WorldForm.jsx` and accept `reviewReveals` in `app/Http/Requests/StoreWorldRequest.php`, `app/Http/Requests/UpdateWorldRequest.php` and `app/Http/Controllers/Api/WorldController.php`, with a test case in `tests/Feature/Api/FactControllerTest.php`

**Checkpoint**: All stories complete.

---

## Phase 12: Polish & Cross-Cutting Concerns

- [X] T056 Update the prompt-section table in `ARCHITECTURE.md` (`creator mode` only while active, `secret trigger` always excluded) and describe facts, the review and creator mode beside the inventory sections
- [ ] T057 Review every new and changed screen against the UI standard: spacing, alignment, animations, empty states, long topics and summaries, keyboard use, and the look next to existing HUD pieces and editors; fix what doesn't match
- [X] T058 Search the repository and a fresh `npm run build` output in `public/build/` for the old hardcoded password and confirm no match (SC-007)
- [X] T059 Run the quality gates once: `vendor/bin/pint --dirty --format agent`, `npm run lint`, `php artisan test --compact`; fix everything that surfaces
- [X] T060 Add to [quickstart.md](quickstart.md) at least ten scripted scenes for SC-005 (disclosure prose, a short conversation, location, expected verdict), mixing clear approvals, clear rejections and borderline cases, then hand the user the walkthrough including those scenes and the timing goal of SC-006

---

## Dependencies & Execution Order

- **Setup (Phase 1)** → **Foundational (Phase 2)** → stories.
- **US1** first: every other story needs facts to act on.
- **US2** after US1. **US3** and **US4** after US2 (they extend `BuildFactsPrompt` and `RevealTool`).
- **US5** after Phase 2; independent of the fact stories.
- **US6** after US2 and US5.
- **US7** after US2 (summaries of revealed facts); T051's frontend part after T045.
- **US8** after US7 (`learnedFacts` and `useKnownFacts`).
- **US9** after US2.
- T021, T025, T027, T032, T040 and T042 all edit `sendMessage`; run them in that order.
- **Polish** last.

## Parallel Examples

- **Phase 1**: T002 and T003 in parallel after T001.
- **Phase 2**: T005–T010 in parallel after T004.
- **US1**: T012 and T014 in parallel.
- **US2**: T018 in parallel with T017; T022 and T023 in parallel after T021.
- **US5**: T030, T036 and T037 in parallel; US5 can run alongside US2–US4 apart from the `sendMessage` order above.
- **US6**: T038 and T039 in parallel.
- **US7**: T044, T045 and T046 in parallel after T043.

## Implementation Strategy

1. **MVP**: Phases 1–4 (US1, US2): residents hold facts and share them only when the review agrees. Validate with quickstart steps 1–3.
2. **Story mechanics**: US3 and US4, relaying and OOC turns.
3. **Creator mode**: US5, then US6. US5 can come first when removing the hardcoded password is urgent.
4. **Depth**: US7, US8, US9.
5. **Polish**: docs, the UI review, the bundle check, then the gates, once.
