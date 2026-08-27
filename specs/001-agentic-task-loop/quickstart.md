# Quickstart: Validating the Agentic Task Loop

Manual end-to-end validation for the three user stories in [spec.md](spec.md), once implemented.

## Prerequisites

- Migrations run (`php artisan migrate`) — adds `assistants.mode`, `assistants.agent_config`, `ai_models.supports_tools`, `messages.tool_calls`.
- An assistant with `mode = agent`, whose configured `AiModel` has `supports_tools = true`.
- The one minimal built-in test tool from research.md #7 is registered for that assistant (no MCP setup needed — out of scope for this feature).

## Scenario 1 — Single tool call (User Story 1, P1)

1. Start a conversation with the agent-mode assistant.
2. Send a message that requires the built-in test tool to answer (e.g., a question only the test tool can resolve).
3. **Expected**: one assistant response arrives containing the answer, incorporating the tool's result. No second user message was needed. `messages.tool_calls` on the intermediate turn is populated; the final message's `content` reflects the tool result.
4. Send the same kind of task to a non-agent-mode assistant. **Expected**: identical behavior to before this feature existed — no tool call, no `tool_calls` data, per FR-009.

## Scenario 2 — Chained tool calls (User Story 2, P2)

1. Send a message requiring two dependent calls to the test tool (second call's input derived from the first's result).
2. **Expected**: the final answer incorporates both results, in one exchange. Inspecting the conversation's messages shows two `tool_call`/`tool_result` pairs before the final assistant message.
3. While the request is in flight, poll `GET /api/assistants/{assistant}/conversations/{id}/agent-progress` (contracts/agent-progress-endpoint.md). **Expected**: `in_progress: true` with a changing `status` string across the two steps, then `in_progress: false` once the send completes.

## Scenario 3 — Step limit reached (User Story 3, P3)

1. Configure a low `agent_config` step limit (e.g., 2) for the test assistant.
2. Send a task engineered to need more steps than the limit allows.
3. **Expected**: the assistant stops at the configured limit and returns a clear partial result or explanation — not an error, not an indefinite wait (FR-005). Verify no more than the configured number of `tool_call` messages were created for that task.

## Out of scope for this quickstart

Verifying MCP tool sources, subagent delegation, repo access, or a persisted/queryable run history — none of these exist yet; they're separate, later specs.
