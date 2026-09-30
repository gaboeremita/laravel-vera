---

description: "Task list for term rules applied from the assistant's prompt"
---

# Tasks: Term Rules Applied From the Assistant's Prompt

**Input**: Design documents from `specs/022-prompt-term-rules/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api.md, quickstart.md

**Tests**: Included. CLAUDE.md requires every change to be tested and the constitution (Principle VI) requires factory-backed Pest feature tests.

**Organization**: Tasks are grouped by user story so each story can be implemented and tested on its own.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)

## Path Conventions

Laravel + React in one repository: `app/`, `resources/js/`, `database/factories/`, `tests/Feature/Api/`, `tests/Unit/` (Node test runner for JS utilities).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Test data shared by every story

- [X] T001 Add an `withTermRules(string $sectionText, array $settings = [])` state to `database/factories/AssistantFactory.php` that puts `$sectionText` in a top-level string prompt section (key `termRuleSection`, which no other factory state uses), and sets `agent_config.termRules` to `['section' => <that key>, 'markTerms' => false, 'swapInvariant' => false, 'highlightMissing' => false, ...$settings]`, preserving any other `agent_config` keys

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Rule parsing, stored settings, save validation and the settings UI. Every story needs these.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

### Tests for the foundation

- [X] T002 [P] Write `tests/Feature/Api/TermRulesSettingsTest.php` covering: `PATCH assistants.update` saves `agent_config.termRules` and keeps `agent_config.step_limit`; `GET assistants.show` returns the stored `termRules`, or defaults (`section` null, three flags false) when absent; `POST assistants.store` accepts `termRules` with the prompt being created; 422 on `agent_config.termRules.section` when `section` names a missing key or a list/object section; 422 naming each failing line (`Line N is not a valid rule: "…"`) when a `->` line has an empty side, on both `assistants.update` and `PUT prompt.update` (errors keyed `prompt` there); lines without `->` are accepted; renaming or removing the picked section through `prompt.update` succeeds; another user's assistant returns 404. Use factories and fake data only

### Implementation for the foundation

- [X] T003 [P] Create `app/DTOs/TermRule.php`: readonly constructor-promoted `string $source`, `array $sourceVariants`, `string $target`, `array $targetVariants`, `bool $invariant`, `bool $caseSensitive`, `int $line`, with PHPDoc array shapes (`list<string>`)
- [X] T004 Create `app/Actions/TermRules/ParseTermRules.php` with `handle(string $sectionText): array` returning `['rules' => list<TermRule>, 'failingLines' => list<array{line: int, text: string}>]`, per research R3: split into lines; skip lines without `->`; split once on `->`; strip trailing `(invariant)` / `(case)` marks in any order from the right side; split each side on `,` and trim; a line fails when either side has no non-empty term; when a source term or source variant repeats an earlier rule's, drop the later occurrence (first line wins) (depends on T003)
- [X] T005 Create `app/Rules/ValidTermRuleSection.php` (ValidationRule) constructed with the prompt array and the section key: fails when the key is not a top-level key or its value is not a string; otherwise runs `ParseTermRules` and calls `$fail` once per failing line with `Line {line} is not a valid rule: "{text}"` (depends on T004)
- [X] T006 Update `app/Http/Controllers/Api/AssistantController.php`: in `store` and `update`, validate `agent_config.termRules.section` (nullable string, plus `ValidTermRuleSection` against the incoming `prompt` or, when absent, the stored prompt) and the three booleans; merge only `termRules` into the existing `agent_config` array before saving; in `show`, return `agent_config.termRules` with defaults filled in (depends on T005)
- [X] T007 Update `app/Http/Controllers/Api/AssistantPromptController.php` `update`: when the assistant's `agent_config.termRules.section` is set and still present as a string section in the new prompt, add `ValidTermRuleSection` to the `prompt` rules so failing lines return 422 keyed `prompt` (depends on T005)
- [X] T008 [P] Create `resources/js/components/TermRuleSettings.jsx`: props `{ sections, value, onChange }`; a dropdown listing the top-level keys of `sections` whose value is a string (plus an empty "none" choice), and three checkboxes bound to `markTerms`, `swapInvariant`, `highlightMissing`. Labels use plain placeholder wording until the user approves the names (CLAUDE.md naming rule); match the form styling in `EditAssistantPage.jsx`
- [X] T009 Wire `TermRuleSettings` into `resources/js/pages/EditAssistantPage.jsx` (load `agent_config.termRules` from `assistants.show`, pass `prompt.sections`, send `agent_config.termRules` in the existing update request, surface 422 messages through the existing error toast) and `resources/js/pages/CreateAssistantPage.jsx` (pass the prompt being built, include `agent_config.termRules` in the create request) (depends on T006, T008)

**Checkpoint**: Settings save, reload and validate; with every checkbox off, nothing else changes (FR-005)

---

## Phase 3: User Story 1 - Rules in the prompt, matched terms marked in the message (Priority: P1) 🎯 MVP

**Goal**: With inline marking on, the newest user message reaches the model with each matched term annotated as `[occurrence -> target]`; the stored and displayed message stays as typed.

**Independent Test**: Assistant with two rules and `markTerms` on; send a message containing one source term; assert the faked model request carries the annotation for that term only and the stored message and `userContent` are unchanged; with `markTerms` off, the request carries the text as typed.

### Tests for User Story 1

- [X] T010 [P] [US1] Write `tests/Feature/Api/TermRulesConversationTest.php` (marking section) posting to `conversations.sendMessage` with `Http::fake` capturing the model request: annotation of a matched term; every occurrence of repeated terms annotated; variant match annotated with the rule's target; term inside a longer word left alone (including accented neighbours); longest match wins for overlapping terms (`court` vs `supreme court`); case-insensitive by default and exact with `(case)`; no match leaves the text identical; `markTerms` off leaves it identical and the rule lines still appear in the system prompt; earlier user messages in `messages` reach the model as typed (FR-008a); after editing the rule section between two sends, the second request uses the edited rules; a timing test parses and marks one message against 500 generated rules and asserts it finishes in under 50 ms (SC-003); stored user message and `userContent` stay as typed; retrieval query uses the unmarked text; picked section missing or empty sends the text unchanged

### Implementation for User Story 1

- [X] T011 [P] [US1] Create `app/DTOs/MarkedMessage.php`: readonly `string $text`, `array $placeholders` (`array<string, string>` placeholder → target), `array $matches` (`list<array{rule: TermRule, start: int, length: int}>` in UTF-8 byte offsets of the original text)
- [X] T012 [US1] Create `app/Actions/TermRules/MarkTermRules.php` with `handle(string $text, list<TermRule> $rules, bool $markTerms, bool $swapInvariant): MarkedMessage`, per research R4/R5: build one case-insensitive and one case-sensitive alternation (terms sorted longest first, `preg_quote`d, wrapped in `(?<![\p{L}\p{N}])` / `(?![\p{L}\p{N}])`, `u` flag), run both with `PREG_OFFSET_CAPTURE`, merge keeping the longest match at overlapping positions, record `matches`, and when `$markTerms` rewrite each occurrence as `[occurrence -> target]`; leave a clear seam for placeholders (US2) (depends on T011, T004)
- [X] T013 [US1] Add a private `markedTermRules(Assistant $assistant, string $text): ?MarkedMessage` helper to `app/Http/Controllers/Api/ConversationController.php` that returns null when `section` is null, the section is missing or not a string, or every flag is off; otherwise parses and marks (depends on T012)
- [X] T014 [US1] In `ConversationController::sendMessage`, after the user message is stored and creator-mode tags are stripped, replace only `$validated['messages'][$lastUserIndex]['content']` with the marked text; keep `$lastUserMessage['content']`, the stored message, retrieval and `userContent` on the text as typed (depends on T013)
- [X] T015 [P] [US1] In `ConversationController::sendDiscordMessage`, apply the same marking to the content of the triggering message (whichever Discord user wrote it) sent to the model, keeping the stored message as typed; earlier channel messages stay as typed (depends on T013)
- [X] T016 [P] [US1] In `app/Console/Commands/TelegramPollCommand.php`, apply the same marking to the incoming text sent to the model, keeping the stored message as typed; reuse `ParseTermRules` and `MarkTermRules` directly (depends on T012)

**Checkpoint**: User Story 1 works end to end on web, Discord and Telegram

---

## Phase 4: User Story 2 - Exact swap for invariant terms (Priority: P2)

**Goal**: With exact swap on, invariant terms reach the model as `⟦n⟧` placeholders and the reply shows the exact target text; no placeholder is ever shown or stored.

**Independent Test**: Assistant with an `(invariant)` rule and `swapInvariant` on; fake a model reply containing `⟦1⟧`; assert the request carried `⟦1⟧`, the returned `content` and the stored assistant message contain the exact target, and the system prompt is identical to the one sent with `swapInvariant` off.

### Tests for User Story 2

- [X] T017 [P] [US2] Extend `tests/Feature/Api/TermRulesConversationTest.php` (swap section): invariant occurrence replaced by `⟦n⟧` in the request, numbered per message; reply `⟦1⟧` restored to the exact target in `content` and the stored message; reply without the placeholder returned as written; non-invariant rule untouched by the swap and still annotated when `markTerms` is on; the system prompt gains no text from the swap; restore also applies on the agent-loop branch (assistant with tools), on Discord, and on Telegram

### Implementation for User Story 2

- [X] T018 [US2] Extend `MarkTermRules::handle` in `app/Actions/TermRules/MarkTermRules.php`: when `$swapInvariant`, replace each invariant rule occurrence with `⟦n⟧` (n from 1 per message) instead of the annotation, recording `placeholders[⟦n⟧] = target` (depends on T012)
- [X] T019 [US2] Add `restore(string $reply): string` to `app/DTOs/MarkedMessage.php` that replaces every recorded placeholder with its exact target text (depends on T011)
- [X] T020 [US2] In `ConversationController::sendMessage`, after the model call on both the agent-loop and plain `chat` branches, run `$content = $marked->restore($content)` before TTS parsing and storage (depends on T014, T018, T019)
- [X] T021 [P] [US2] Apply the same restore in `ConversationController::sendDiscordMessage` before the reply is parsed and stored (depends on T015, T018, T019)
- [X] T022 [P] [US2] Apply the same restore in `app/Console/Commands/TelegramPollCommand.php` before the reply is stored and sent (depends on T016, T018, T019)

**Checkpoint**: User Stories 1 and 2 both work independently

---

## Phase 5: User Story 3 - Highlight of missing target terms (Priority: P3)

**Goal**: With the highlight on, the web chat shows a warning line under the reply listing missing target terms and underlines the source terms that required them in the user's message; nothing is stored.

**Independent Test**: Assistant with a rule and `highlightMissing` on; fake a reply without the target; assert `missingTerms` lists it with the right UTF-16 range; fake a reply with the target (or a target variant) and assert an empty list; in the browser the warning and underline show, and disappear on reload.

### Tests for User Story 3

- [X] T023 [P] [US3] Extend `tests/Feature/Api/TermRulesConversationTest.php` (highlight section): `missingTerms` present only when `highlightMissing` is on and a rule matched; missing target listed with `ranges` in UTF-16 offsets (include a message with accented characters and an emoji before the term); target variant counts as present; `(case)` rules check case exactly; restored placeholders count as present; nothing about missing terms is stored on the messages
- [X] T024 [P] [US3] Write `tests/Unit/TermHighlights.test.js` (Node test runner, like `tests/Unit/WorldCollision.test.js`) for the range-splitting utility: no ranges, one range, several ranges, range at start and end, and add a `test:term-highlights` script to `package.json` next to the existing `test:*` scripts

### Implementation for User Story 3

- [X] T025 [US3] Add `missingTerms(string $reply): array` to `app/DTOs/MarkedMessage.php` returning `list<array{target: string, ranges: list<array{int, int}>}>`: for each matched rule, check the reply for its target or target variants with the same boundary and case handling as matching; for missing ones, convert that rule's match byte offsets in the original text to UTF-16 code-unit offsets (comment why: the browser indexes JS strings) (depends on T011, T012)
- [X] T026 [US3] In `ConversationController::sendMessage`, when `highlightMissing` is on and the marked message has matches, add `missingTerms` (computed on `$content` after any placeholder restore that ran) to the JSON response (depends on T014, T025)
- [X] T027 [P] [US3] Create `resources/js/utils/termHighlights.js` exporting a pure function that splits a text into `{ text, underlined }` parts from a list of `[start, length]` ranges (depends on nothing)
- [X] T028 [US3] In `resources/js/hooks/useConversationChat.js`, when `data.missingTerms` is a non-empty list, keep it on the in-memory reply message (`missingTerms`) and the ranges on the in-memory user message (`underlines`); never persist them (depends on T026)
- [X] T029 [US3] In `resources/js/components/ChatMessage.jsx`, render the user message through `termHighlights.js` with underlined spans when `msg.underlines` is set, and a warning line under the assistant reply listing `msg.missingTerms[].target` when set; derive during render, no effects (Principle VIII) (depends on T027, T028)

**Checkpoint**: All three stories work independently

---

## Phase 6: Polish & Cross-Cutting Concerns

- [ ] T030 Walk through every scenario in `specs/022-prompt-term-rules/quickstart.md` with the user, including comparing adherence with marking on and off on the same messages (SC-001), and fix what surfaces
- [ ] T031 At push/PR time only (CLAUDE.md cadence): run `vendor/bin/pint`, `npm run lint`, and `php artisan test --compact`, and fix everything they surface

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: none
- **Foundational (Phase 2)**: depends on Setup; blocks all stories
- **US1 (Phase 3)**: depends on Foundational
- **US2 (Phase 4)**: depends on Foundational and on `MarkTermRules`/`MarkedMessage` from US1 (T011, T012) and the controller hook points (T014–T016)
- **US3 (Phase 5)**: depends on Foundational and US1 (T011, T012, T014); independent of US2
- **Polish (Phase 6)**: after the stories you ship

### Within Each Story

- Tests first, failing before implementation
- DTOs before actions, actions before controller wiring, backend before frontend

### Parallel Opportunities

- T002, T003, T008 in parallel at the start of Phase 2
- T010 and T011 in parallel; T015 and T016 in parallel after T012/T013
- T017 in parallel with T018/T019; T021 and T022 in parallel
- T023, T024 and T027 in parallel

## Parallel Example: User Story 1

```text
Task: "T010 [US1] Marking tests in tests/Feature/Api/TermRulesConversationTest.php"
Task: "T011 [US1] Create app/DTOs/MarkedMessage.php"
# after T012 and T013:
Task: "T015 [US1] Marking in ConversationController::sendDiscordMessage"
Task: "T016 [US1] Marking in app/Console/Commands/TelegramPollCommand.php"
```

## Parallel Example: User Story 3

```text
Task: "T023 [US3] Highlight tests in tests/Feature/Api/TermRulesConversationTest.php"
Task: "T024 [US3] tests/Unit/TermHighlights.test.js"
Task: "T027 [US3] resources/js/utils/termHighlights.js"
```

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1 and Phase 2
2. Phase 3 (US1)
3. Validate with quickstart scenarios 1, 2, 5, 6 and 7

### Incremental Delivery

1. Foundation → settings save and validate
2. US1 → marking (MVP)
3. US2 → exact swap
4. US3 → missing-term highlight
