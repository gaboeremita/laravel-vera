---

description: "Task list for the agent image generation tool feature"
---

# Tasks: Agent Image Generation Tool

**Input**: Design documents from `/specs/002-agent-image-gen-tool/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Included — Constitution Principle VI (Feature-Test-First, Factory-Backed) makes Pest feature tests the default for this repo, not optional.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)

---

## Phase 1: Setup

No setup tasks — no new dependencies, migrations, or configuration keys (plan.md Technical Context; data-model.md confirms no schema changes at all).

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: No user story work can begin until this phase is complete — every story depends on the extended `AgentTool` interface and the shared `ImageGenerationService` existing first.

- [X] T001 Extend `App\Contracts\AgentTool` interface: add `timeoutSeconds(): int` and `retryAttempts(): int` methods in `app/Contracts/AgentTool.php` — research.md #4, #5
- [X] T002 [P] Implement `timeoutSeconds()`/`retryAttempts()` on `app/Services/AgentLoop/Tools/GetCurrentDatetimeTool.php`, both returning the existing global config values (`config('agent.tool_timeout')`/`config('agent.tool_retry_attempts')`) — preserves today's behavior exactly (depends on T001)
- [X] T003 [P] Implement `timeoutSeconds()`/`retryAttempts()` on `app/Services/AgentLoop/Tools/BasicCalculatorTool.php`, same as T002 (depends on T001)
- [X] T004 Update `app/Services/AgentLoop/AgentLoopRunner.php`: `executeWithTimeout()` calls `$tool->timeoutSeconds()` instead of `config('agent.tool_timeout')` directly; `executeWithRetries()` calls `$tool->retryAttempts()` instead of `config('agent.tool_retry_attempts')` directly (depends on T001, T002, T003)
- [X] T005 Create `App\Services\ImageGenProviders\ImageGenerationService` in `app/Services/ImageGenProviders/ImageGenerationService.php` with `generate(AssistantUser $assistantUser, Conversation $conversation, string $rawPrompt): array{enhancedPrompt: string, imageData: string}`, `isAvailableFor(AssistantUser $assistantUser): bool`, and `resolveTimeoutFor(AssistantUser $assistantUser): int` — contracts/image-generation-service.md, research.md #1, #4, #6

**Checkpoint**: Foundation ready — the interface and shared service both exist; user story implementation can now begin.

---

## Phase 3: User Story 1 - Assistant generates an image mid-task (Priority: P1) 🎯 MVP

**Goal**: An agent-mode assistant with image generation configured can call `generate_image` mid-task and have the result appear in the conversation, without the user ever typing `/create-image`.

**Independent Test**: quickstart.md Scenario 1.

### Tests for User Story 1

- [X] T006 [P] [US1] Feature test `tests/Feature/ImageGenerationToolSingleCallTest.php`: `Http::fake()`-sequences an LLM `tool_use`/`tool_calls` response requesting `generate_image` followed by a final-answer response, and fakes the image-gen provider's HTTP call; asserts a new `assistant`-role message with an attached `Image` is created, and the loop's final answer is returned (FR-001) — contracts/generate-image-tool.md
- [X] T007 [P] [US1] Feature test `tests/Feature/ImageGenerationToolAvailabilityTest.php`: an assistant with no image-gen provider/model configured and no global `ai.image_gen.url` never has `generate_image` registered as an available tool (FR-005, spec.md Edge Cases) — research.md #6

### Implementation for User Story 1

- [X] T008 [US1] Create `App\Services\AgentLoop\Tools\ImageGenerationTool implements AgentTool` in `app/Services/AgentLoop/Tools/ImageGenerationTool.php`, constructed with `ImageGenerationService`, `AssistantUser`, `Conversation`: `handle()` rejects a missing/empty `prompt` argument with a thrown `\RuntimeException` (FR-008), calls `ImageGenerationService::generate()`, creates a carrier `Message` (`role: 'assistant'`, `content: ''`) on the conversation, stores the image via `Image::storeFromBase64($imageData, $carrierMessage, "messages/{$assistantUser->user_id}/{$conversation->id}")`, and returns `{"status": "success", "enhanced_prompt": "..."}`; `timeoutSeconds()` returns `$this->imageGenerationService->resolveTimeoutFor($assistantUser) + 30`; `retryAttempts()` returns `1` — contracts/generate-image-tool.md (depends on T005, T001)
- [X] T009 [US1] Wire `app/Http/Controllers/Api/ConversationController.php@sendMessage`: conditionally add `new ImageGenerationTool(...)` to the tools array passed into `AgentLoopRunner` only when `ImageGenerationService::isAvailableFor($assistantUser)` returns `true` (FR-005) (depends on T008)

**Checkpoint**: User Story 1 is fully functional and independently testable — this is the MVP.

---

## Phase 4: User Story 2 - Consistent results with the existing manual command (Priority: P2)

**Goal**: Prove the reuse the feature exists for — the tool and the manual `/create-image` command resolve and call the exact same per-assistant image-gen configuration through one shared code path.

**Independent Test**: quickstart.md Scenario 2.

### Tests for User Story 2

- [X] T010 [US2] Feature test `tests/Feature/ImageGenerationToolConsistencyTest.php`: for an assistant with a specific `ImageGenModel` configured, assert both the manual `/create-image` path and a `generate_image` tool call resolve and invoke that same model/provider (FR-002), with the same prompt-enhancement call (FR-003) — quickstart.md Scenario 2

### Implementation for User Story 2

- [X] T011 [US2] Refactor `app/Http/Controllers/Api/ConversationController.php@generateImageMessage`: replace its inlined `resolveImageGenModel` → `ImageGenPromptEnhancer::enhance` → `fromModel`/`fromConfig` → `generate` sequence with a single call to `ImageGenerationService::generate($assistantUser, $conversation, $rawPrompt)`; its own message-creation, `reactToGeneratedImage` call, and `Image::storeFromBase64` stay unchanged — externally-visible behavior of `/create-image` is unaffected (research.md #1, contracts/image-generation-service.md) (depends on T005)

**Checkpoint**: User Stories 1 and 2 both work independently — the manual command and the tool now share one resolve/enhance/generate code path.

---

## Phase 5: User Story 3 - Graceful handling of slow or failed generation (Priority: P3)

**Goal**: A failing or slow generation degrades gracefully — a clear failure reaches the user instead of a hang, and a normal successful generation is never mistaken for a timeout.

**Independent Test**: quickstart.md Scenario 3.

### Tests for User Story 3

- [X] T012 [P] [US3] Feature test `tests/Feature/ImageGenerationToolFailureTest.php`: `Http::fake()` the image-gen provider call to fail (e.g. non-2xx response); assert the tool call fails cleanly through `AgentLoopRunner`'s existing `\Throwable` handling, the user receives a clear explanation, and no carrier message/image is left behind for the failed attempt (FR-006, Constitution Principle V)
- [X] T013 [P] [US3] Feature test `tests/Feature/ImageGenerationToolTimeoutTest.php`: assert `ImageGenerationTool::timeoutSeconds()` reflects a per-assistant `ImageGenModel->config['timeout']` override (not just the global `ai.image_gen.timeout` default), proving a normal, longer-than-60s successful generation is not killed by the loop's default tool timeout (FR-007, research.md #4)
- [X] T014 [P] [US3] Feature test `tests/Feature/ImageGenerationToolMultipleCallsTest.php`: two `generate_image` calls within one task each produce their own independent carrier message and image, with neither overwriting the other (FR-009, spec.md Edge Cases)

### Implementation for User Story 3

No new production code — this story validates behavior already delivered by Foundational (T001-T004) and User Story 1 (T008): `timeoutSeconds()`/`retryAttempts()` on `ImageGenerationTool`, and `AgentLoopRunner`'s existing failure-surfacing, both already in place before this phase starts.

**Checkpoint**: All three user stories are independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T015 [P] `vendor/bin/pint --test` passes with zero errors (Constitution Principle I) — **passing**
- [X] T016 [P] `npm run lint` passes with zero errors (Constitution Principle I) — **0 errors, 47 pre-existing warnings unrelated to this feature**
- [X] T017 **(automated portions done)** Full `php artisan test` suite: 28/28 passing (30/30 after T018/T019 below), no regressions — covers quickstart.md Scenarios 1-3 and the multi-image check via `ImageGenerationToolSingleCallTest`, `ImageGenerationToolConsistencyTest`, `ImageGenerationToolFailureTest`, `ImageGenerationToolTimeoutTest`, `ImageGenerationToolMultipleCallsTest`. The manual, live-provider verification against a real image-gen API remains undone — no live API key/provider available in this session (same limitation noted in 001-agentic-task-loop's tasks.md)
- [X] T018 **(added post-implementation — real gap found via live user testing)** The plan's claim of "zero new frontend code" was wrong: `ChatPage.jsx`'s agent-mode response handling only ever appended one synthetic message built from `data.content`/`data.tool_calls` (`ChatPage.jsx:322-325`, prior to this fix) — it never read an image URL, unlike the separate manual `/create-image` response shape (`data.image_url`, handled a few lines above). The tool's carrier message/image were created correctly server-side (T008) but never reached the screen until a full reload. Fixed: `ImageGenerationTool::handle()` now includes `image_url` in its returned result (a short string, not image bytes — doesn't reopen the no-raw-image-data-in-the-trace concern from research.md #3); `ChatPage.jsx` now maps any `generate_image` tool result carrying an `image_url` into its own image bubble, inserted before the final text reply. Regression coverage added to `ImageGenerationToolSingleCallTest.php`.
- [X] T019 **(added post-implementation — real gap found via live user testing)** Live testing against a real model surfaced it echoing a ReAct-style pseudo tool call (`{"action": "generate_image", "action_input": "..."}`) as plain text at the end of an otherwise-final answer — the JSON was never executed, just leaked into what the user saw. This never happens on `/create-image`, which pinpointed the actual cause: that command's reaction call never sends a `tools` param at all, while `AgentLoopRunner` sends the same `tools` definitions on every single iteration — including the wrap-up turn right after a tool result comes back — with nothing anywhere telling the model what to do with a result it already has. Root-cause fix, not output filtering: `AgentLoopRunner::withToolUsageInstructions()` appends an explicit instruction to the system prompt before the loop starts — use the real tool-calling mechanism, never describe a call as text, and treat an already-returned tool result as done. Applied generally (every request in the loop, not just when `generate_image` is involved), since the gap wasn't specific to this tool. New test: `AgentLoopToolUsageInstructionsTest.php`, asserting the instruction is actually present in the outgoing request and that it's appended to the existing system message rather than sent as a separate one.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: None — no tasks.
- **Foundational (Phase 2)**: Blocks all user stories — the `AgentTool` interface extension and `ImageGenerationService` must exist first.
- **User Stories (Phase 3-5)**: All depend on Foundational completion. Recommended order is priority order (P1 → P2 → P3) since US2's refactor (T011) and US3's tests (T012-T014) both exercise `ImageGenerationTool`/`ImageGenerationService` built in US1 — but each story is independently testable once Foundational is done.
- **Polish (Phase 6)**: Depends on whichever stories are complete.

### Within Each User Story

- Feature tests before/alongside implementation (Constitution Principle VI).
- Story complete before moving to the next priority.

### Parallel Opportunities

- T002/T003 (the two existing tools' new interface methods) can run in parallel within Phase 2.
- T006/T007 (US1's two test files) can be written in parallel once Foundational is done.
- T012/T013/T014 (US3's three test files) can all run in parallel — each targets a different file and none depends on the others.
- T015/T016 (lint) can run in parallel with each other.

---

## Parallel Example: Foundational Phase

```bash
# Once T001 lands, these can run together:
Task: "Implement timeoutSeconds()/retryAttempts() on GetCurrentDatetimeTool"
Task: "Implement timeoutSeconds()/retryAttempts() on BasicCalculatorTool"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 2: Foundational (critical — blocks everything).
2. Complete Phase 3: User Story 1.
3. **Stop and validate**: quickstart.md Scenario 1 against a real agent-mode assistant with image generation configured.

### Incremental Delivery

1. Foundational → the interface and shared service exist, nothing user-visible yet.
2. Add User Story 1 → an agent-mode assistant can generate and show an image mid-task (MVP).
3. Add User Story 2 → the manual command is refactored onto the same shared service, proving consistency.
4. Add User Story 3 → failure and timeout behavior confirmed correct under test.

## Notes

- No migrations in this feature — every change is a new or modified PHP class (Constitution Principle II, N/A).
- `ImageGenerationTool` resolves image-gen configuration only through the request's own `AssistantUser`, via `ImageGenManager::forAssistantUser()` — never an account-level default (Constitution Principle IV — see data-model.md).
- Generation failures must propagate as thrown exceptions through `AgentLoopRunner`'s existing handling, never swallowed (Constitution Principle V).
