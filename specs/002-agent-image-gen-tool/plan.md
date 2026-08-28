# Implementation Plan: Agent Image Generation Tool

**Branch**: `002-agent-image-gen-tool` | **Date**: 2026-08-27 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/002-agent-image-gen-tool/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Add a third built-in agent tool, `generate_image`, alongside the existing `get_current_datetime` and `basic_calculator` (`001-agentic-task-loop`), so agent-mode assistants can generate and show an image mid-task without the user typing the manual `/create-image` command. The reusable core of `/create-image`'s existing pipeline (resolve the assistant's image-gen configuration, enhance the prompt, call the provider) is extracted from `ConversationController::generateImageMessage` into a new `App\Services\ImageGenProviders\ImageGenerationService`, called by both the existing manual command and the new tool — the concrete abstraction the user asked for. Three problems specific to this tool are resolved: (1) the `AgentTool` interface gains a `timeoutSeconds()` method so a slow, I/O-bound tool can have a longer per-call timeout budget than the two existing instant, in-process tools, derived from the assistant's actual resolved image-gen timeout rather than a second, independently-set config value; (2) the interface also gains a `retryAttempts()` method so a failed generation against a paid, slow provider is not retried three times by default like the two free, instant tools — it fails on the first attempt; and (3) since the tool executes before the loop's own final assistant message exists, it creates its own minimal carrier message and attaches the generated image to it immediately, reusing the existing `Image::storeFromBase64`/`msg.image_url` rendering path with zero new frontend code — the image and the assistant's in-character comment about it land in two separate messages rather than one combined bubble.

## Technical Context

**Language/Version**: PHP 8.4 (Laravel 13) backend. No frontend changes required (research.md #3) — this feature reuses the existing image-on-message rendering path unchanged.

**Primary Dependencies**: Existing `App\Contracts\AgentTool`, `App\Services\AgentLoop\AgentLoopRunner` (`001-agentic-task-loop`), `App\Services\ImageGenProviders\{ImageGenManager,ImageGenPromptEnhancer}`, `App\Models\{Image,Message,AssistantUser,Conversation}`, `App\Http\Controllers\Api\ConversationController`. No new package.

**Storage**: PostgreSQL. No schema changes — no migration in this feature (data-model.md).

**Testing**: Pest v4 feature tests, factory-backed, following `001-agentic-task-loop`'s established pattern of `Http::fake()`-sequenced LLM responses; this feature additionally fakes the image-gen provider's own HTTP call (research.md #8).

**Target Platform**: Web — existing single Laravel + React application (Laravel Herd locally).

**Project Type**: Web service — existing single-project layout, not a new project or service.

**Performance Goals**: The image tool's per-call timeout is derived from the assistant's actual resolved image-gen configuration (`ImageGenModel->config['timeout']` or `config('ai.image_gen.timeout')`, default 120s) plus a fixed 30-second buffer, not the global `config('agent.tool_timeout')` (60s) the two existing tools keep using unchanged (research.md #4).

**Constraints**: Executes inside the same synchronous `AgentLoopRunner`/`sendMessage` request `001-agentic-task-loop` already accepted the risk for. Unlike the two existing free, instant tools, a failed image generation is not retried (`retryAttempts()` returns `1`, research.md #5) — bounding the worst case for one failed call to a single `timeoutSeconds()` window instead of compounding it across multiple attempts against a paid, slow provider. The tool is only registered for an assistant when `ImageGenerationService::isAvailableFor()` is true (FR-005); it is never offered otherwise, with no user-facing error about a "missing" tool.

**Scale/Scope**: This feature only — one new tool, one new shared service class, one new `AgentTool` interface method, and a refactor of `generateImageMessage` to call the new shared service instead of inlining the same three steps. No changes to `/create-image`'s externally-visible behavior, no new configuration keys, no new tables.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Check | Result |
|---|---|---|
| I. Lint-Enforced Code Style | New PHP code must pass Pint | Pass — standard for all changes, no exception needed |
| II. Append-Only Migrations | No migrations at all in this feature | Pass (N/A) |
| III. Comments Justify Only Non-Obvious Decisions | No speculative documentation planned | Pass |
| IV. Data Isolation by Ownership | `ImageGenerationTool` is constructed with the request's own resolved `AssistantUser`/`Conversation` and only ever calls `ImageGenManager::forAssistantUser($assistantUser)` — never an account-level default (data-model.md) | Pass |
| V. Errors Fail Loudly | Generation failures propagate as exceptions through `AgentLoopRunner`'s existing `\Throwable` handling — no new swallowing (contracts/generate-image-tool.md) | Pass |
| VI. Feature-Test-First, Factory-Backed | Pest feature tests planned for all three user stories, following `001-agentic-task-loop`'s established `Http::fake()` pattern, extended to also fake the image-gen HTTP call | Pass |
| VII. No Speculative Abstraction | `ImageGenerationService` is extracted because there are now two real callers needing the same three steps (research.md #1) — not built in anticipation of a third. `timeoutSeconds()` and `retryAttempts()` are added to `AgentTool` for the same reason: a second real caller with genuinely different timing and retry-cost needs than the two existing free, instant tools (research.md #4, #5) | Pass — this is the deciding principle for both the service-extraction and interface-extension questions |
| VIII. State Derivation During Render | No frontend changes in this feature | Pass (N/A) |

No violations. Complexity Tracking is not needed.

**Post-Phase 1 re-check**: data-model.md, contracts/, and quickstart.md introduce no new tables, no new project/service boundary, and no abstraction beyond what research.md justified (one shared service with three narrow methods, one new interface method with a documented, non-speculative rationale). Gate still passes.

## Project Structure

### Documentation (this feature)

```text
specs/002-agent-image-gen-tool/
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
│   └── AgentTool.php                       # + timeoutSeconds(), retryAttempts() (research.md #4, #5)
├── Services/
│   ├── AgentLoop/
│   │   ├── AgentLoopRunner.php             # executeWithTimeout()/executeWithRetries() call $tool->timeoutSeconds()/$tool->retryAttempts() instead of config('agent.tool_timeout')/config('agent.tool_retry_attempts') directly
│   │   └── Tools/
│   │       ├── GetCurrentDatetimeTool.php  # + timeoutSeconds()/retryAttempts() returning the existing global config values (unchanged behavior)
│   │       ├── BasicCalculatorTool.php     # + timeoutSeconds()/retryAttempts() returning the existing global config values (unchanged behavior)
│   │       └── ImageGenerationTool.php     # new: contracts/generate-image-tool.md
│   └── ImageGenProviders/
│       ├── ImageGenManager.php             # unchanged
│       ├── ImageGenPromptEnhancer.php      # unchanged
│       └── ImageGenerationService.php      # new: contracts/image-generation-service.md
└── Http/Controllers/Api/
    └── ConversationController.php          # generateImageMessage() calls ImageGenerationService::generate() instead of inlining resolve+enhance+generate; sendMessage() conditionally registers ImageGenerationTool

tests/Feature/
├── ImageGenerationToolSingleCallTest.php     # User Story 1
├── ImageGenerationToolConsistencyTest.php    # User Story 2 — same provider/model config as /create-image
├── ImageGenerationToolFailureTest.php        # User Story 3 — failure surfaced, not a hang
├── ImageGenerationToolTimeoutTest.php        # research.md #4 — timeoutSeconds() reflects resolved config, not the global default
├── ImageGenerationToolAvailabilityTest.php   # FR-005 — tool absent when unconfigured
└── ImageGenerationToolMultipleCallsTest.php  # FR-009 — each call gets its own carrier message/image
```

**Structure Decision**: Existing single Laravel + React project — no new project or service boundary. The new tool lives in `App\Services\AgentLoop\Tools`, alongside the existing two, and the new shared service lives in `App\Services\ImageGenProviders`, alongside the existing image-gen classes it wraps — both follow the project's existing service-class and tool-registration conventions rather than introducing a new architectural layer.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

No violations to track.
