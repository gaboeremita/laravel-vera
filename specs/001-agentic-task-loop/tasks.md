---

description: "Task list for the agentic task loop feature"
---

# Tasks: Agentic Task Loop for Agent-Mode Assistants

**Input**: Design documents from `/specs/001-agentic-task-loop/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Included — Constitution Principle VI (Feature-Test-First, Factory-Backed) makes Pest feature tests the default for this repo, not optional.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)

---

## Phase 1: Setup

- [X] T001 Create `config/agent.php` with `tool_timeout` (60), `step_limit` (10), `tool_retry_attempts` (3), `progress_cache_ttl` (10) — research.md #10
- [X] T002 [P] Create `App\Enums\AssistantMode` (`Assistant`, `Agent` cases) in `app/Enums/AssistantMode.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: No user story work can begin until this phase is complete — every story depends on the loop, both tools, and an assistant actually being configurable as agent-mode.

- [X] T003 Migration: add `mode` (string, default `assistant`) and `agent_config` (json, nullable) to `assistants` table in `database/migrations/`
- [X] T004 [P] Migration: add `supports_tools` (boolean, default `false`) to `ai_models` table in `database/migrations/`
- [X] T005 [P] Migration: add `tool_calls` (json, nullable) to `messages` table in `database/migrations/`
- [X] T006 Update `app/Models/Assistant.php`: add `mode` (cast to `AssistantMode`) and `agent_config` to `#[Fillable]` (depends on T002, T003)
- [X] T007 [P] Update `app/Models/AiModel.php`: add `supports_tools` to `#[Fillable]` (depends on T004)
- [X] T008 [P] Update `app/Models/Message.php`: add `tool_calls` to `$fillable` (depends on T005)
- [X] T009 Extend `app/DTOs/LlmResponse.php`: add `toolCalls` (array of normalized `{id, name, arguments}`) and `isFinal` (bool) — contracts/llm-provider.md
- [X] T010 Extend `app/Contracts/LlmProvider.php`: add `$tools` param to `chat()` — contracts/llm-provider.md (depends on T009)
- [X] T011 Implement tool-calling wire format in `app/Services/LlmProviders/AnthropicProvider.php` (`tool_use`/`tool_result` blocks) — research.md #1 (depends on T010)
- [X] T012 [P] Implement tool-calling wire format in `app/Services/LlmProviders/GenericProvider.php` (`tool_calls`/`role: tool`) — research.md #2 (depends on T010)
- [X] T013 [P] Create `app/Services/AgentLoop/Tools/GetCurrentDatetimeTool.php` — contracts/get-current-datetime-tool.md
- [X] T014 [P] Create `app/Services/AgentLoop/Tools/BasicCalculatorTool.php` — hand-rolled tokenizer/evaluator, no `eval()` — contracts/basic-calculator-tool.md
- [X] T015 Create `app/Services/AgentLoop/AgentLoopRunner.php`: calls `LlmProvider::chat()` in a loop; executes each step's tool call(s) sequentially (multiple calls in one step run one after another, not concurrently — plan.md Constraints); retries a failed call up to `config('agent.tool_retry_attempts')` times (FR-012); after exhausting retries, tries different approaches up to the same cap, stopping earlier if further attempts look futile (FR-013); stops and explains to the user when both are exhausted (FR-014); enforces `config('agent.tool_timeout')` per tool call (FR-015); enforces the step limit from `assistant.agent_config['step_limit'] ?? config('agent.step_limit')` (FR-004/FR-005) (depends on T011, T012, T013, T014)
- [X] T016 Wire `app/Http/Controllers/Api/ConversationController.php@sendMessage`: route through `AgentLoopRunner` when the assistant's `mode` is `Agent`; assistants with `mode = Assistant` take the existing unchanged path (FR-009) (depends on T015)
- [X] T017 Create `app/Http/Controllers/Api/AgentProgressController.php@show` + route `GET /api/assistants/{assistant}/conversations/{id}/agent-progress` in `routes/api.php` — contracts/agent-progress-endpoint.md (depends on T015)
- [X] T018 Add `supports_tools` gating: reject selecting a tool-incapable model for an agent-mode assistant (FR-007) — implemented in `app/Http/Controllers/Api/SettingsController.php@selectModel` rather than `AssistantController`, since model selection lives in `Settings`, not on the `Assistant` record itself (depends on T006, T007)
- [X] T018a **(added post-implementation — real gap found)** Add `supports_tools` to `app/Http/Controllers/Api/AiModelController.php@store`/`@update` validation and persistence (FR-016) — T018's gate had no way to ever be satisfied for a real model: `supports_tools` defaults to `false` on every existing `AiModel` row and nothing let a user set it to `true`
- [X] T018b **(added post-implementation)** [P] Add a `supports_tools` checkbox to `resources/js/components/ModelAccordion.jsx`, wired to the same field (FR-016)
- [X] T019 [P] Add an "Agent Mode" toggle to `resources/js/pages/CreateAssistantPage.jsx` and `resources/js/pages/EditAssistantPage.jsx`

**Checkpoint**: Foundation ready — an assistant can be put into agent mode, the loop mechanism exists, both tools exist, and progress is queryable.

---

## Phase 3: User Story 1 - Single tool call to completion (Priority: P1) 🎯 MVP

**Goal**: An agent-mode assistant calls one tool, incorporates the result, and answers — without the user sending a second message. Non-agent-mode assistants and tool-free tasks are unaffected.

**Independent Test**: quickstart.md Scenario 1.

- [X] T020 [P] [US1] Feature test `tests/Feature/AgentLoopSingleToolCallTest.php`: single `get_current_datetime` call answered correctly; single `basic_calculator` call answered correctly; a task needing no tool is answered directly (FR-001a); a non-agent-mode assistant is unaffected (FR-009) — all via `Http::fake()`-sequenced responses and `Carbon::setTestNow()` — **4/4 passing**
- [X] T021 [US1] Run quickstart.md Scenario 1 manually against a real agent-mode assistant — **verified live** against OpenRouter (`google/gemma-4-26b-a4b-it`, the `.env`-configured default): both `get_current_datetime` and `basic_calculator` correctly called and answered from, no mocking

**Checkpoint**: User Story 1 is fully functional and independently testable — this is the MVP.

---

## Phase 4: User Story 2 - Chaining multiple tool calls (Priority: P2)

**Goal**: The assistant chains a second, dependent tool call based on the first call's result, and the user sees live progress while it works.

**Independent Test**: quickstart.md Scenario 2.

- [X] T022 [P] [US2] Feature test `tests/Feature/AgentLoopChainedToolCallsTest.php` — **2/2 passing**
- [X] T023 [US2] Create `resources/js/components/AgentProgressIndicator.jsx` — state derived during render per Constitution Principle VIII (ESLint's `set-state-in-effect` rule caught a real violation of this on the first pass, fixed using the same pattern as `ConversationList.jsx`)
- [X] T024 [US2] Wire `AgentProgressIndicator` into `resources/js/pages/ChatPage.jsx` for agent-mode sends
- [X] T025 [US2] Run quickstart.md Scenario 2 manually — **partially verified live**: confirmed live tool-calling works end-to-end against a real model (T021), but the specific dependent-chain example ("day of the month, tripled") wasn't separately exercised live — the model choosing to chain both tools together on its own isn't guaranteed on any given run, only proven deterministically via the Pest suite (T022)

**Checkpoint**: User Stories 1 and 2 both work independently; live progress is visible during multi-step work.

---

## Phase 5: User Story 3 - Graceful stop at the safety step limit (Priority: P3)

**Goal**: A task that can't finish within the step limit stops cleanly and reports what it accomplished, instead of looping indefinitely or erroring.

**Independent Test**: quickstart.md Scenario 3.

- [X] T026 [P] [US3] Feature test `tests/Feature/AgentLoopStepLimitTest.php` — **2/2 passing**
- [X] T027 [US3] Run quickstart.md Scenario 3 manually — **verified live** with `agent_config.step_limit = 1` against a real, tool-requiring task: the model made one real tool call, hit the limit, and the loop returned a real model-generated summary ("I have successfully used my tool... nothing left to do") instead of continuing or erroring (FR-005). Narrower caveat than originally stated: this proves a single call exceeding a low limit stops gracefully, not a real model reliably chaining several calls before hitting a higher limit — that part is still Pest-only (T026), since coaxing a live model into a specific number of chained calls on demand isn't reliable, unlike simply lowering the limit to 1.

**Checkpoint**: All three user stories are independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T028 [P] `vendor/bin/pint --test` passes with zero errors (Constitution Principle I) — **passing**
- [X] T029 [P] `npm run lint` passes with zero errors (Constitution Principle I) — **0 errors, 47 pre-existing warnings unrelated to this feature**
- [X] T030 Run the full quickstart.md validation guide end-to-end — **automated portions done** (full `php artisan test` suite: 16/16 passing, no regressions); the three manual live-chat scenarios (T021/T025/T027) remain undone — need a real LLM API key/model, not available in this session
- [X] T031 **(added post-implementation — found via real user testing)** `AgentProgressIndicator`'s poll `catch {}` was a silent empty catch, swallowing every failure including a real one: Ziggy's route list is injected once server-side at page load (`@routes` in `resources/views/welcome.blade.php`), so a browser tab open before `conversations.agent-progress` existed had no way to know the route existed, and the resulting error vanished with no trace. Fixed to `console.error` instead of swallowing (Constitution Principle V — errors fail loudly, not just in backend code). Documented the hard-refresh requirement in quickstart.md.
- [X] T032 **(added post-implementation — real crash found via real user testing)** Loading a conversation containing a persisted `tool_call` message (`content: null`) crashed the whole page white: `ConversationController::show()` had no role filter, so `tool_call` rows reached `ChatMessage.jsx`'s `InlineText`, which assumed `content` was always a string and threw on `null.length`, with no error boundary to contain it. This was always the actual design intent (FR-011/research.md #4: these records exist for the loop's own reconstruction, not as user-facing history) — the endpoint was just never actually filtered to match. Fixed both ends: `show()` excludes `role != 'tool_call'`, and `InlineText` now guards against null text regardless. New regression test: `tests/Feature/ConversationShowExcludesToolCallsTest.php`.
- [X] T033 **(out of scope — pre-existing bug, found blocking real verification, three attempts to actually root-cause it)** `ChatMessage.jsx`'s `code` component used an `inline` prop to decide between rendering plain `<code>` or `<pre><code>` — but `react-markdown` v10.1.0 never passes an `inline` prop at all (confirmed against the package's own type definitions), so every code span, inline or fenced, always took the `<pre>` branch. A single-backtick inline mention like `` `PHP_INT_MAX` `` inside a sentence therefore produced a `<pre>` nested inside a `<p>` — invalid HTML, a React hydration/DOM-nesting crash. Two earlier attempts (a shallow "does this paragraph contain a block child" check, then a recursive version of the same check) patched the *symptom* — including one attempt that introduced an unrelated `RangeError` from unbounded recursion with no base case — without addressing that `code` was misclassifying itself in the first place. Real fix: split into separate `code` and `pre` components matching how the parser actually structures a fenced block (`pre > code`) versus inline code (bare `code`, no `pre` ancestor at all) — not introduced by this feature, but blocked verifying it live, so fixed anyway.
- [X] T034 **(added post-implementation — refined during real user testing)** FR-010/FR-011 originally read as "not persisted" meaning the progress indicator should disappear the instant a task finishes, not just that nothing gets written to the database — the user's actual intent was that a completed task's tool-call summary stays visible in the UI for the rest of that browser session (frontend state only), gone on reload since nothing is in the database (FR-010a, spec.md Clarifications "refined 2026-08-27"). Implemented: `AgentLoopRunner::run()` now returns `App\DTOs\AgentRunResult` (content + a tool-call summary) instead of a bare string; `ConversationController::sendMessage` includes `tool_calls` in its JSON response for agent-mode sends; `ChatPage.jsx` attaches it to the new message in local state; new `resources/js/components/AgentToolCallsTrace.jsx` renders it as a collapsible trace line, wired into `ChatMessage.jsx`. New assertions in `tests/Feature/AgentLoopSingleToolCallTest.php` cover the response payload.
- [X] T035 **(added post-implementation — `/speckit-analyze` finding G1)** FR-015 ("System MUST enforce a configurable timeout on each individual tool call") was never actually enforced — `config('agent.tool_timeout')` was defined but only ever referenced in a comment explaining why enforcement was deferred, reasoning `pcntl` is "typically unavailable in FPM contexts" without checking whether that applied here. It's loaded and working in this environment. Implemented real enforcement in `AgentLoopRunner::executeWithTimeout()` via `pcntl_alarm`/`pcntl_signal(SIGALRM, ...)`, scoped per retry attempt. Verified twice: `tests/Feature/AgentLoopToolTimeoutTest.php` (a deliberately slow mock tool interrupted well before its full sleep duration) and a temporary, unauthenticated test route hit via real `curl` through Herd's actual PHP-FPM (not just CLI, which is what Pest runs under) — confirmed the alarm fires and interrupts inside a real web worker too, then the test route was removed. research.md #11 documents the correction.
- [X] T036 **(`/speckit-analyze` finding I1)** plan.md's Project Structure tree was stale relative to the actual final file set. A first attempt at fixing it was itself incomplete — missed `SettingsController.php`, `AssistantController.php`, `ChatMessage.jsx`, `CreateAssistantPage.jsx`, `EditAssistantPage.jsx`, and `routes/api.php` — caught by cross-checking against `git diff --stat main...001-agentic-task-loop` programmatically rather than trusting memory of what had been touched. All 39 changed files (38 committed + `AgentLoopToolTimeoutTest.php`, uncommitted at the time) are now represented.
- [X] T037 **(`/speckit-analyze` finding A1)** SC-005 read as a per-step guarantee ("at every step... a user can tell") that the polling mechanism (research.md #5, 2-second interval) doesn't actually promise — a step completing within one polling window can finish unseen. Reworded to scope the claim to what the mechanism actually delivers: visibility for tasks running longer than one polling interval, with an explicit note that it's not a per-step guarantee.
- [X] T038 **(`/speckit-analyze` finding U1)** Key Entities never got a "Tool-Call Summary" entry when FR-010a was added, so a reader skimming only that section wouldn't know the session-visible summary concept existed or that it's a distinct, non-persisted display artifact from a Tool Call itself. Added.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies.
- **Foundational (Phase 2)**: Depends on Setup — blocks all user stories.
- **User Stories (Phase 3–5)**: All depend on Foundational completion. Recommended order is priority order (P1 → P2 → P3) since US2's live-progress UI (T023/T024) depends on Foundational's T017, and US3 has no dependency on US1/US2 beyond the shared loop — but each story is independently testable once Foundational is done.
- **Polish (Phase 6)**: Depends on whichever stories are complete.

### Within Each User Story

- Feature test before manual quickstart validation.
- Story complete before moving to the next priority.

### Parallel Opportunities

- T004/T005 (migrations), T007/T008 (models), T012 (GenericProvider) alongside T011 (AnthropicProvider), T013/T014 (the two tools) can all run in parallel within Phase 2.
- T020, T022, T026 (the three feature test files) can be written in parallel once Foundational is done, since each targets a different file.
- T028/T029 (lint) can run in parallel with each other.

---

## Parallel Example: Foundational Phase

```bash
# Once T002/T003 land, these can run together:
Task: "Migration: add supports_tools to ai_models table"
Task: "Migration: add tool_calls to messages table"
Task: "Create GetCurrentDatetimeTool"
Task: "Create BasicCalculatorTool"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup.
2. Complete Phase 2: Foundational (critical — blocks everything).
3. Complete Phase 3: User Story 1.
4. **Stop and validate**: quickstart.md Scenario 1 against a real agent-mode assistant.

### Incremental Delivery

1. Setup + Foundational → an assistant can be switched to agent mode and make one tool call.
2. Add User Story 1 → single tool call round-trip works end-to-end (MVP).
3. Add User Story 2 → chaining and live progress visible.
4. Add User Story 3 → safe stop at the step limit.

## Notes

- All migrations are additive — no existing migration is edited (Constitution Principle II).
- Every query resolving an assistant's tools, config, or progress cache key must go through the requesting user's own `AssistantUser`/`Conversation` via the existing `ResolvesAssistantUser` trait (Constitution Principle IV — see data-model.md).
- Tool-call failures must surface to the model and be logged, never silently swallowed (Constitution Principle V).
