# Implementation Plan: Agentic Task Loop for Agent-Mode Assistants

**Branch**: `001-agentic-task-loop` | **Date**: 2026-08-26 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-agentic-task-loop/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Add a tool-calling loop to VERA's existing chat pipeline. When an agent-mode assistant handles a task, it can call a tool, receive the result, and continue — chaining further calls as needed — until it produces a final answer or hits a configurable step limit, without the user sending another message. This requires: (1) extending `LlmProvider`/`LlmResponse` so both `AnthropicProvider` (`tool_use` blocks) and `GenericProvider` (`tool_calls`) can send tool definitions and return structured tool-call requests, each in its own wire format; (2) an orchestrating loop above the providers that executes tools and re-calls the model until done; (3) extending `Message` to persist a tool-call turn, not just plain text; (4) a lightweight live-progress signal the frontend can poll while the loop runs, satisfying FR-010 without building new async/queue infrastructure; and (5) exactly one minimal built-in tool to prove the loop end-to-end, since which tools exist long-term (MCP) is explicitly out of scope for this feature.

## Technical Context

**Language/Version**: PHP 8.4 (Laravel 13) backend; JavaScript/JSX (React 19) frontend for the live-progress display

**Primary Dependencies**: Existing `App\Contracts\LlmProvider`, `App\Services\LlmProviders\{LlmManager,AnthropicProvider,GenericProvider}`, `App\Http\Controllers\Api\ConversationController`, `App\Models\{Assistant,Conversation,Message}`. No new package required — both the Anthropic Messages API and OpenAI-compatible chat completions API support tool-calling natively over the same `Http::` client already in use.

**Storage**: PostgreSQL. Additive migrations only, extending `messages` (persist a tool-call turn) and `assistants` (agent mode toggle + step-limit config) — no existing migration is edited, per Constitution Principle II.

**Testing**: Pest v4 feature tests, factory-backed (`AssistantFactory`, `AssistantUserFactory`, `ConversationFactory`, `MessageFactory` already exist), per Constitution Principle VI.

**Target Platform**: Web — existing single Laravel + React application (Laravel Herd locally).

**Project Type**: Web service — existing single-project layout, not a new project or service.

**Performance Goals**: No numeric target is fixed by the spec (documented as an Assumption — the step limit's exact value is a planning decision). A conservative default step limit and per-step timeout are proposed in `research.md`, bounded by the request timeout already used for one LLM call today (`config('ai.default.config.timeout')`).

**Constraints**: The loop executes synchronously within the existing `ConversationController@sendMessage` request — the spec requires live progress visibility (FR-010) but does not require background/async execution, so no new job/queue/broadcast infrastructure is introduced for this feature (confirmed no Reverb/Pusher is installed; `broadcasting.default` is `log`). Both provider implementations must support tool-calling with each handling its own wire format — no shared translation layer, per the existing architectural decision. Assistants not in agent mode must be provably unaffected (FR-009).

**Scale/Scope**: This feature only — the loop mechanism, provider contract changes, message persistence, and one minimal built-in test tool. No MCP client, no subagents, no repo access, no persisted action log (all explicitly out of scope per spec Assumptions).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Check | Result |
|---|---|---|
| I. Lint-Enforced Code Style | New PHP/JSX code must pass Pint/ESLint | Pass — standard for all changes, no exception needed |
| II. Append-Only Migrations | `messages`/`assistants` changes are new migrations, no edits to existing ones | Pass |
| III. Comments Justify Only Non-Obvious Decisions | No speculative documentation planned | Pass |
| IV. Data Isolation by Ownership | The loop must resolve tools/state through the requesting assistant's own `AssistantUser`/`Conversation`, via the existing `ResolvesAssistantUser` trait — never inferred from account-level defaults | Pass, called out explicitly in data-model.md |
| V. Errors Fail Loudly | Tool-call failures must be surfaced to the model (FR-008) and logged, never silently swallowed | Pass |
| VI. Feature-Test-First, Factory-Backed | Pest feature tests planned for all three user stories, using existing factories | Pass |
| VII. No Speculative Abstraction | Builds one minimal test tool and the loop only — no generic MCP client, no pluggable tool-registration system, since the spec explicitly defers that | Pass — this is the deciding principle for the MCP-scope question below |
| VIII. State Derivation During Render | Live-progress polling state on the frontend must be derived during render, not set in a `useEffect` | Pass, called out explicitly for the frontend polling component |

No violations. Complexity Tracking is not needed.

**Post-Phase 1 re-check**: data-model.md, contracts/, and quickstart.md introduce no new tables, no new project/service boundary, and no speculative abstraction beyond what research.md justified (one normalized tool-call shape, one cache-backed progress signal, one minimal test tool). Gate still passes.

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Contracts/
│   └── LlmProvider.php              # extend: tools param, structured tool-call return
├── DTOs/
│   └── LlmResponse.php              # extend: carry tool-call requests, not just content
├── Services/
│   ├── LlmProviders/
│   │   ├── AnthropicProvider.php    # tool_use / tool_result wire format
│   │   ├── GenericProvider.php      # tool_calls / role:tool wire format
│   │   └── LlmManager.php           # unchanged entry point
│   └── AgentLoop/                   # new: orchestrates the tool-call loop
│       ├── AgentLoopRunner.php
│       └── Tools/
│           └── (one minimal built-in test tool)
├── Http/Controllers/Api/
│   ├── ConversationController.php   # sendMessage invokes the loop when assistant is agent mode
│   └── AgentProgressController.php  # new: polled live-progress endpoint (FR-010)
├── Models/
│   ├── Assistant.php                # add mode + agent config
│   └── Message.php                  # add tool-call turn persistence
└── Enums/
    └── AssistantMode.php            # new

database/migrations/
├── ..._add_mode_to_assistants_table.php
└── ..._add_tool_call_data_to_messages_table.php

resources/js/
├── components/
│   └── AgentProgressIndicator.jsx   # new: polls AgentProgressController while loop runs
└── pages/ChatPage.jsx               # renders progress indicator during agent-mode sends

tests/Feature/
├── AgentLoopSingleToolCallTest.php   # User Story 1
├── AgentLoopChainedToolCallsTest.php # User Story 2
└── AgentLoopStepLimitTest.php        # User Story 3
```

**Structure Decision**: Existing single Laravel + React project — no new project or service boundary. The loop lives in a new `App\Services\AgentLoop` namespace alongside the existing `LlmProviders` namespace it depends on, following the project's existing service-class convention rather than introducing a new architectural layer.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
