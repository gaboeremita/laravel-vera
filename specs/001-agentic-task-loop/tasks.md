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

- [ ] T001 Create `config/agent.php` with `tool_timeout` (60), `step_limit` (10), `tool_retry_attempts` (3), `progress_cache_ttl` (10) — research.md #10
- [ ] T002 [P] Create `App\Enums\AssistantMode` (`Assistant`, `Agent` cases) in `app/Enums/AssistantMode.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: No user story work can begin until this phase is complete — every story depends on the loop, both tools, and an assistant actually being configurable as agent-mode.

- [ ] T003 Migration: add `mode` (string, default `assistant`) and `agent_config` (json, nullable) to `assistants` table in `database/migrations/`
- [ ] T004 [P] Migration: add `supports_tools` (boolean, default `false`) to `ai_models` table in `database/migrations/`
- [ ] T005 [P] Migration: add `tool_calls` (json, nullable) to `messages` table in `database/migrations/`
- [ ] T006 Update `app/Models/Assistant.php`: add `mode` (cast to `AssistantMode`) and `agent_config` to `#[Fillable]` (depends on T002, T003)
- [ ] T007 [P] Update `app/Models/AiModel.php`: add `supports_tools` to `#[Fillable]` (depends on T004)
- [ ] T008 [P] Update `app/Models/Message.php`: add `tool_calls` to `$fillable` (depends on T005)
- [ ] T009 Extend `app/DTOs/LlmResponse.php`: add `toolCalls` (array of normalized `{id, name, arguments}`) and `isFinal` (bool) — contracts/llm-provider.md
- [ ] T010 Extend `app/Contracts/LlmProvider.php`: add `$tools` param to `chat()` — contracts/llm-provider.md (depends on T009)
- [ ] T011 Implement tool-calling wire format in `app/Services/LlmProviders/AnthropicProvider.php` (`tool_use`/`tool_result` blocks) — research.md #1 (depends on T010)
- [ ] T012 [P] Implement tool-calling wire format in `app/Services/LlmProviders/GenericProvider.php` (`tool_calls`/`role: tool`) — research.md #2 (depends on T010)
- [ ] T013 [P] Create `app/Services/AgentLoop/Tools/GetCurrentDatetimeTool.php` — contracts/get-current-datetime-tool.md
- [ ] T014 [P] Create `app/Services/AgentLoop/Tools/BasicCalculatorTool.php` — hand-rolled tokenizer/evaluator, no `eval()` — contracts/basic-calculator-tool.md
- [ ] T015 Create `app/Services/AgentLoop/AgentLoopRunner.php`: calls `LlmProvider::chat()` in a loop; executes each step's tool call(s) sequentially (multiple calls in one step run one after another, not concurrently — plan.md Constraints); retries a failed call up to `config('agent.tool_retry_attempts')` times (FR-012); after exhausting retries, tries different approaches up to the same cap, stopping earlier if further attempts look futile (FR-013); stops and explains to the user when both are exhausted (FR-014); enforces `config('agent.tool_timeout')` per tool call (FR-015); enforces the step limit from `assistant.agent_config['step_limit'] ?? config('agent.step_limit')` (FR-004/FR-005) (depends on T011, T012, T013, T014)
- [ ] T016 Wire `app/Http/Controllers/Api/ConversationController.php@sendMessage`: route through `AgentLoopRunner` when the assistant's `mode` is `Agent`; assistants with `mode = Assistant` take the existing unchanged path (FR-009) (depends on T015)
- [ ] T017 Create `app/Http/Controllers/Api/AgentProgressController.php@show` + route `GET /api/assistants/{assistant}/conversations/{id}/agent-progress` in `routes/api.php` — contracts/agent-progress-endpoint.md (depends on T015)
- [ ] T018 Add `supports_tools` gating to `app/Http/Controllers/Api/AssistantController.php` validation: reject/warn when `mode = Agent` is selected for an `AiModel` with `supports_tools = false` (FR-007) (depends on T006, T007)
- [ ] T019 [P] Add an "Agent Mode" toggle to `resources/js/pages/CreateAssistantPage.jsx` and `resources/js/pages/EditAssistantPage.jsx`

**Checkpoint**: Foundation ready — an assistant can be put into agent mode, the loop mechanism exists, both tools exist, and progress is queryable.

---

## Phase 3: User Story 1 - Single tool call to completion (Priority: P1) 🎯 MVP

**Goal**: An agent-mode assistant calls one tool, incorporates the result, and answers — without the user sending a second message. Non-agent-mode assistants and tool-free tasks are unaffected.

**Independent Test**: quickstart.md Scenario 1.

- [ ] T020 [P] [US1] Feature test `tests/Feature/AgentLoopSingleToolCallTest.php`: single `get_current_datetime` call answered correctly; single `basic_calculator` call answered correctly; a task needing no tool is answered directly (FR-001a); a non-agent-mode assistant is unaffected (FR-009) — all via `Http::fake()`-sequenced responses and `Carbon::setTestNow()`
- [ ] T021 [US1] Run quickstart.md Scenario 1 manually against a real agent-mode assistant to confirm the automated test matches real behavior

**Checkpoint**: User Story 1 is fully functional and independently testable — this is the MVP.

---

## Phase 4: User Story 2 - Chaining multiple tool calls (Priority: P2)

**Goal**: The assistant chains a second, dependent tool call based on the first call's result, and the user sees live progress while it works.

**Independent Test**: quickstart.md Scenario 2.

- [ ] T022 [P] [US2] Feature test `tests/Feature/AgentLoopChainedToolCallsTest.php`: two dependent tool calls resolved in one exchange; more than two dependent calls chained up to the step limit — via `Http::fake()`-sequenced responses (research.md #9's accepted limitation: chaining is validated here, not via a real model's live tool choice)
- [ ] T023 [US2] Create `resources/js/components/AgentProgressIndicator.jsx`: polls `AgentProgressController`'s endpoint every 2 seconds while a send is in flight, stops on resolve — state derived during render per Constitution Principle VIII, not set in a `useEffect` (depends on T017)
- [ ] T024 [US2] Wire `AgentProgressIndicator` into `resources/js/pages/ChatPage.jsx` for agent-mode sends (depends on T023)
- [ ] T025 [US2] Run quickstart.md Scenario 2 manually ("what's today's day of the month, tripled?") against a real agent-mode assistant

**Checkpoint**: User Stories 1 and 2 both work independently; live progress is visible during multi-step work.

---

## Phase 5: User Story 3 - Graceful stop at the safety step limit (Priority: P3)

**Goal**: A task that can't finish within the step limit stops cleanly and reports what it accomplished, instead of looping indefinitely or erroring.

**Independent Test**: quickstart.md Scenario 3.

- [ ] T026 [P] [US3] Feature test `tests/Feature/AgentLoopStepLimitTest.php`: a task exceeding a configured step limit stops and returns a clear partial result, not an error or a hang — via `Http::fake()`-sequenced responses longer than the limit
- [ ] T027 [US3] Run quickstart.md Scenario 3 manually with a low `agent_config` step-limit override

**Checkpoint**: All three user stories are independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [ ] T028 [P] `vendor/bin/pint --test` passes with zero errors (Constitution Principle I)
- [ ] T029 [P] `npm run lint` passes with zero errors (Constitution Principle I)
- [ ] T030 Run the full quickstart.md validation guide end-to-end

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
