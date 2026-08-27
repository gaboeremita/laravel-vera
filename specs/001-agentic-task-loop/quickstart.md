# Quickstart: Validating the Agentic Task Loop

Manual end-to-end validation for the three user stories in [spec.md](spec.md), once implemented.

## Prerequisites

- Migrations run (`php artisan migrate`) — adds `assistants.mode`, `assistants.agent_config`, `ai_models.supports_tools`, `messages.tool_calls`.
- An assistant with `mode = agent`, whose configured `AiModel` has `supports_tools = true`.
- The `get_current_datetime` tool (contracts/get-current-datetime-tool.md) is registered for that assistant — no MCP setup needed, it's built into this feature.

## Scenario 1 — Single tool call (User Story 1, P1)

1. Start a conversation with the agent-mode assistant.
2. Ask it what today's date or the current time is.
3. **Expected**: one assistant response arrives with a correct answer, incorporating the tool's result. No second user message was needed. `messages.tool_calls` on the intermediate turn is populated; the final message's `content` reflects the tool result.
4. Send the same kind of question to a non-agent-mode assistant. **Expected**: identical behavior to before this feature existed — no tool call, no `tool_calls` data, per FR-009.
5. Ask the agent-mode assistant something that needs no tool at all (e.g. a simple opinion question). **Expected**: it answers directly, no tool call — per FR-001a and User Story 1's third acceptance scenario.

## Scenario 2 — Chained tool calls (User Story 2, P2)

`get_current_datetime` takes no parameters, so it cannot itself produce a genuinely dependent second call (research.md #8, known limitation). This scenario is validated by the automated Pest suite (`AgentLoopChainedToolCallsTest.php`) using `Http::fake()`-sequenced responses that simulate a model calling a tool twice, the second call informed by the first's result — not by a manual chat interaction in this quickstart. Run:

```bash
php artisan test --compact --filter=AgentLoopChainedToolCallsTest
```

**Expected**: the test passes, proving the loop mechanism itself correctly chains multiple tool-call/result round-trips before returning a final answer — independent of which specific tool triggers it.

## Scenario 3 — Step limit reached (User Story 3, P3)

Same constraint as Scenario 2 — a parameterless tool can't be coaxed into an open-ended chain in a live conversation. Validated by `AgentLoopStepLimitTest.php`, which fakes a sequence of tool-call responses longer than a configured step limit.

```bash
php artisan test --compact --filter=AgentLoopStepLimitTest
```

**Expected**: the test passes, confirming the loop stops at the configured limit and returns a clear partial result rather than continuing indefinitely or erroring (FR-005).

## Live progress (FR-010)

While Scenario 1's request is in flight, poll `GET /api/assistants/{assistant}/conversations/{id}/agent-progress` (contracts/agent-progress-endpoint.md). **Expected**: `in_progress: true` with a status string while the tool call is being made, then `in_progress: false` once the send completes.

## Out of scope for this quickstart

Verifying MCP tool sources, subagent delegation, repo access, or a persisted/queryable run history — none of these exist yet; they're separate, later specs.
