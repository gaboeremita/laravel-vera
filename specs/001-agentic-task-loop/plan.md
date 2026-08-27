# Implementation Plan: Agentic Task Loop for Agent-Mode Assistants

**Branch**: `001-agentic-task-loop` | **Date**: 2026-08-26 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-agentic-task-loop/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Add a tool-calling loop to VERA's existing chat pipeline. When an agent-mode assistant handles a task, it can call a tool, receive the result, and continue — chaining further calls as needed — until it produces a final answer or hits a configurable step limit, without the user sending another message. This requires: (1) extending `LlmProvider`/`LlmResponse` so both `AnthropicProvider` (`tool_use` blocks) and `GenericProvider` (`tool_calls`) can send tool definitions and return structured tool-call requests, each in its own wire format; (2) an orchestrating loop above the providers that executes tools and re-calls the model until done; (3) extending `Message` to persist a tool-call turn, not just plain text; (4) a lightweight live-progress signal the frontend can poll while the loop runs, satisfying FR-010 without building new async/queue infrastructure; and (5) two built-in tools — `get_current_datetime` and `basic_calculator` (contracts/get-current-datetime-tool.md, contracts/basic-calculator-tool.md) — to prove the loop end-to-end, including genuine dependent chaining between them, since dynamically registering additional tools (MCP) is explicitly out of scope for this feature.

## Technical Context

**Language/Version**: PHP 8.4 (Laravel 13) backend; JavaScript/JSX (React 19) frontend for the live-progress display

**Primary Dependencies**: Existing `App\Contracts\LlmProvider`, `App\Services\LlmProviders\{LlmManager,AnthropicProvider,GenericProvider}`, `App\Http\Controllers\Api\ConversationController`, `App\Models\{Assistant,Conversation,Message}`. No new package required — both the Anthropic Messages API and OpenAI-compatible chat completions API support tool-calling natively over the same `Http::` client already in use.

**Storage**: PostgreSQL. Additive migrations only, extending `messages` (persist a tool-call turn) and `assistants` (agent mode toggle + step-limit config) — no existing migration is edited, per Constitution Principle II.

**Testing**: Pest v4 feature tests, factory-backed (`AssistantFactory`, `AssistantUserFactory`, `ConversationFactory`, `MessageFactory` already exist), per Constitution Principle VI.

**Target Platform**: Web — existing single Laravel + React application (Laravel Herd locally).

**Project Type**: Web service — existing single-project layout, not a new project or service.

**Performance Goals**: Step limit defaults to 10 per task, tool-call timeout to 60s, tool-call retries to 3 — all centralized in a new `config/agent.php` (research.md #10), independent of the existing LLM request timeout (`config('ai.default.config.timeout')`).

**Constraints**: The loop executes synchronously within the existing `ConversationController@sendMessage` request — explicitly confirmed rather than defaulted to: VERA already runs a database queue worker (for `EmbedArchiveEntry`), so dispatching the loop as a background job was a real, viable option, not blocked by missing infrastructure. Synchronous was chosen anyway for simplicity now, accepting the known risk that a worst-case task (multiple steps, each near the LLM/tool timeout ceiling) could exceed typical web-server/PHP-FPM request timeouts — revisit as an async job if this becomes a real problem in practice. Multiple tool calls requested in a single step (`LlmResponse::$toolCalls` can hold more than one) are executed sequentially, not concurrently — both built-in tools are in-process with no I/O latency to overlap, so real concurrency would solve a performance problem that doesn't exist yet (Constitution Principle VII); revisit once an I/O-bound tool (e.g. web search) exists. Both provider implementations must support tool-calling with each handling its own wire format — no shared translation layer, per the existing architectural decision. Assistants not in agent mode must be provably unaffected (FR-009).

**Scale/Scope**: This feature only — the loop mechanism, provider contract changes, message persistence, and two built-in tools (`get_current_datetime`, `basic_calculator`). No MCP client, no subagents, no repo access, no persisted action log (all explicitly out of scope per spec Assumptions).

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
| VII. No Speculative Abstraction | Builds two real, minimal tools (`get_current_datetime`, `basic_calculator` — hand-rolled, no new dependency) and the loop only — no generic MCP client, no pluggable tool-registration system, no scientific-calculator scope creep | Pass — this is the deciding principle for the MCP-scope question below |
| VIII. State Derivation During Render | Live-progress polling state on the frontend must be derived during render, not set in a `useEffect` | Pass, called out explicitly for the frontend polling component |

No violations. Complexity Tracking is not needed.

**Post-Phase 1 re-check**: data-model.md, contracts/, and quickstart.md introduce no new tables, no new project/service boundary, and no speculative abstraction beyond what research.md justified (one normalized tool-call shape, one cache-backed progress signal, `get_current_datetime` plus a hand-rolled, dependency-free `basic_calculator`). Gate still passes.

## Project Structure

### Documentation (this feature)

```text
specs/001-agentic-task-loop/
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
│   ├── LlmProvider.php              # extend: tools param, structured tool-call return
│   └── AgentTool.php                # new: shared interface both built-in tools implement
├── DTOs/
│   ├── LlmResponse.php              # extend: carry tool-call requests, not just content
│   ├── ToolCallRequest.php          # new: normalized {id, name, arguments} shape
│   └── AgentRunResult.php           # new: content + tool-call summary returned by AgentLoopRunner::run()
├── Services/
│   ├── LlmProviders/
│   │   ├── AnthropicProvider.php    # tool_use / tool_result wire format
│   │   ├── GenericProvider.php      # tool_calls / role:tool wire format
│   │   └── LlmManager.php           # + resolveModelForAssistantUser() for supports_tools gating
│   └── AgentLoop/                   # new: orchestrates the tool-call loop
│       ├── AgentLoopRunner.php      # + pcntl_alarm-based per-call timeout enforcement (research.md #11)
│       └── Tools/
│           ├── GetCurrentDatetimeTool.php  # contracts/get-current-datetime-tool.md
│           └── BasicCalculatorTool.php     # contracts/basic-calculator-tool.md
├── Http/Controllers/Api/
│   ├── ConversationController.php   # sendMessage invokes the loop when assistant is agent mode
│   ├── AgentProgressController.php  # new: polled live-progress endpoint (FR-010)
│   ├── AiModelController.php        # + supports_tools on store/update (FR-016)
│   ├── AssistantController.php      # + mode validation on store/update
│   └── SettingsController.php       # + supports_tools gating in selectModel (FR-007)
├── Models/
│   ├── Assistant.php                # add mode + agent config
│   ├── AiModel.php                  # add supports_tools
│   └── Message.php                  # add tool-call turn persistence
└── Enums/
    └── AssistantMode.php            # new

config/
└── agent.php                        # new: step_limit, tool_timeout, tool_retry_attempts, progress_cache_ttl (research.md #10)

database/migrations/
├── ..._add_mode_and_agent_config_to_assistants_table.php
├── ..._add_supports_tools_to_ai_models_table.php
└── ..._add_tool_calls_to_messages_table.php

routes/api.php                       # + GET .../conversations/{id}/agent-progress

resources/js/
├── components/
│   ├── AgentProgressIndicator.jsx   # new: polls AgentProgressController while loop runs
│   ├── AgentToolCallsTrace.jsx      # new: session-visible tool-call summary after completion (FR-010a)
│   ├── ChatMessage.jsx              # + tool_call role filtering safety, code/pre split, renders AgentToolCallsTrace
│   └── ModelAccordion.jsx           # + supports_tools checkbox (FR-016)
├── hooks/useProviders.js            # + supports_tools in the model save payload
└── pages/
    ├── ChatPage.jsx                 # renders progress indicator + attaches tool-call summary to new messages
    ├── CreateAssistantPage.jsx      # + Agent Mode toggle
    └── EditAssistantPage.jsx        # + Agent Mode toggle

tests/Feature/
├── AgentLoopSingleToolCallTest.php        # User Story 1
├── AgentLoopChainedToolCallsTest.php      # User Story 2
├── AgentLoopStepLimitTest.php             # User Story 3
├── AgentLoopToolTimeoutTest.php           # FR-015 enforcement (research.md #11)
├── AiModelSupportsToolsTest.php           # FR-016
└── ConversationShowExcludesToolCallsTest.php  # FR-011 (tool_call rows never reach the UI listing)
```

**Structure Decision**: Existing single Laravel + React project — no new project or service boundary. The loop lives in a new `App\Services\AgentLoop` namespace alongside the existing `LlmProviders` namespace it depends on, following the project's existing service-class convention rather than introducing a new architectural layer.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
