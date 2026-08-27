# Feature Specification: Agentic Task Loop for Agent-Mode Assistants

**Feature Branch**: `001-agentic-task-loop`

**Created**: 2026-08-26

**Status**: Draft

**Input**: User description: "Add an agentic task loop for agent-mode assistants in VERA. Today, every assistant is single-turn: a user sends a message and gets one response, with no way for the assistant to call a tool, see the result, and keep working before answering. An assistant in agent mode should keep working on a task — calling one or more tools, checking their results, and continuing — until it produces a final answer or reaches a safety step limit, without the user needing to send another message in between. This should work the same regardless of which AI model is configured for that assistant. Out of scope: which tools are available (MCP servers), delegating to other assistants (subagents), repo access, and a log of what the agent did during a run — those are separate, later work."

## Clarifications

### Session 2026-08-26

- Q: When an agent-mode assistant is given a task that doesn't actually need any tool, should it just answer directly like it does today, or must it always attempt at least one tool call? → A: Assistant decides per task whether a tool is needed; answers directly with no tool call when none is needed.
- Q: Does the user see any indication the assistant is still working while it's mid-loop, or does the user wait with no visible update until the final answer arrives? → A: Full live, step-by-step visibility into what the assistant is doing while it works — but not persisted; a durable, queryable record of past runs stays out of scope for this feature (a separate, later spec).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Single tool call to completion (Priority: P1)

A user gives an agent-mode assistant a task that requires looking something up or acting before it can answer. The assistant calls the tool, gets the result, and answers — without the user sending a second message.

**Why this priority**: Without this, there is no agentic capability at all. Every other capability in this feature builds on a single tool call working correctly.

**Independent Test**: Give an agent-mode assistant a task that requires exactly one tool call. Confirm the assistant calls the tool and answers using its result, without the user needing to send another message.

**Acceptance Scenarios**:

1. **Given** an agent-mode assistant and a task that requires one tool call, **When** the user submits the task, **Then** the assistant calls the tool, incorporates the result, and returns a final answer without requiring another user message.
2. **Given** an assistant not in agent mode, **When** the user submits any message, **Then** the assistant responds exactly as it does today, with no tool call and no change in behavior.
3. **Given** an agent-mode assistant and a task that does not require any tool, **When** the user submits it, **Then** the assistant answers directly without calling a tool, the same way it would today.
4. **Given** an agent-mode assistant, **When** the user asks what today's date or the current time is, **Then** the assistant calls the date/time tool and answers correctly using its result.

---

### User Story 2 - Chaining multiple tool calls (Priority: P2)

The assistant calls a tool, and based on what comes back, determines it needs to call a second tool before it can answer — a genuine chain where each step depends on the last, not a fixed two-step sequence.

**Why this priority**: Most real tasks are not solved by a single lookup. Without chaining, this capability is not meaningfully different from one hardcoded integration — sequential, dependent steps are what make this a loop rather than a single request/response exchange.

**Independent Test**: Give the assistant a task where a second tool call's input depends on the first tool call's result. Confirm both calls run in sequence and the assistant finishes with one final answer incorporating both.

**Acceptance Scenarios**:

1. **Given** a task requiring two dependent tool calls, **When** the user submits it, **Then** the assistant calls the first tool, uses its result to call the second, and returns a final answer incorporating both results.
2. **Given** a task requiring more than two dependent tool calls, **When** the user submits it, **Then** the assistant continues calling tools in sequence, each informed by the prior result, until it reaches a final answer or the safety step limit.
3. **Given** a task in progress with multiple tool calls, **When** the assistant is working through them, **Then** the user sees a live indication of what the assistant is currently doing, updated as each step happens.

---

### User Story 3 - Graceful stop at the safety step limit (Priority: P3)

If the assistant has not finished a task after a set number of steps, it stops and reports what it accomplished so far, instead of continuing indefinitely or failing silently.

**Why this priority**: This is a safety and control behavior, not core value — User Stories 1 and 2 already deliver a working agent without it. But a loop with no ceiling is a real reliability and cost risk once this is used for real tasks, so it must exist before this ships broadly.

**Independent Test**: Give the assistant a task that cannot resolve within the configured step limit. Confirm it stops at the limit and returns a clear result describing what it accomplished, rather than continuing indefinitely or returning an unexplained error.

**Acceptance Scenarios**:

1. **Given** a task that exceeds the safety step limit, **When** the limit is reached, **Then** the assistant stops and returns its best partial result or a clear explanation of what it attempted, instead of continuing or crashing.

---

### Edge Cases

- What happens when a tool call itself fails or returns an error? The assistant is informed of the failure and can react to it — retry, try a different approach, or explain to the user why it could not complete the task — rather than the whole task failing outright.
- How does the system handle an assistant whose configured AI model does not support tool-calling at all? Agent mode is unavailable for that assistant/model combination, and this is communicated clearly rather than failing silently or confusingly mid-task.
- What happens if a single tool call takes an unusually long time to return? It is bounded by the same request-timeout handling already used for a single model call today — this feature does not introduce a new indefinite-hang risk.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow an agent-mode assistant to determine, per task, whether a tool is needed, and when it is, call the tool mid-task and receive its result before producing a final answer.
- **FR-001a**: System MUST allow an agent-mode assistant to answer a task directly, without calling any tool, when it determines none is needed — behaving the same as a non-agent-mode assistant for that task.
- **FR-002**: System MUST allow an agent-mode assistant to chain multiple tool calls in sequence within a single task, where a later call can depend on an earlier call's result.
- **FR-003**: System MUST NOT require the user to send an additional message for the assistant to continue working after a tool call.
- **FR-004**: System MUST enforce a configurable safety limit on the number of steps an assistant can take within a single task.
- **FR-005**: System MUST, when the step limit is reached before the task is complete, return the assistant's best partial result or a clear explanation instead of continuing indefinitely or failing silently.
- **FR-006**: System MUST support this behavior consistently regardless of which AI model is configured for the assistant, provided that model supports tool-calling.
- **FR-007**: System MUST clearly indicate when an assistant cannot operate in agent mode because its configured AI model does not support tool-calling.
- **FR-008**: System MUST inform the assistant when a tool call it made fails, so it can react instead of the whole task failing outright.
- **FR-009**: Assistants not in agent mode MUST be unaffected by this feature and continue to operate as a single message-response exchange.
- **FR-010**: System MUST show the user a live indication of what the assistant is currently doing at each step while it works through a task (e.g., which tool is being called), updated as the task progresses.
- **FR-011**: System MUST NOT persist a queryable record of a task's steps beyond this live, in-progress display — a durable history of past agent runs is out of scope for this feature.

### Key Entities

- **Task**: A unit of work a user gives an agent-mode assistant, which may take multiple steps before it produces a final answer.
- **Tool Call**: A single action the assistant takes while working on a task, together with the result it produced.
- **Step Limit**: The maximum number of tool calls an assistant may make while working on one task before it must stop and report its progress.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An agent-mode assistant completes a task requiring at least one tool call in a single exchange, with no follow-up message needed from the user.
- **SC-002**: An agent-mode assistant completes a task requiring multiple, sequentially dependent tool calls in a single exchange.
- **SC-003**: 100% of tasks that reach the safety step limit return a clear, readable result to the user rather than an unexplained failure or an indefinite wait.
- **SC-004**: Assistants not in agent mode show zero behavior change from before this feature existed.
- **SC-005**: At every step of a multi-step task, a user can tell the assistant is actively working and roughly what it's doing, without needing to wait for the final answer to find out.

## Assumptions

- The user sees live visibility into what the assistant is doing at each step, but this display is not persisted — a durable, queryable record of past runs (an agent-run action log) is separate, later work.
- The user does not send a new message to steer or interrupt the assistant mid-task as part of this feature — mid-run steering is out of scope.
- The safety step limit's exact numeric value is an implementation decision to be made during planning, not fixed by this specification.
- This feature includes exactly one concrete, built-in tool — a current date/time lookup — to prove the loop end-to-end. Dynamically registering additional tools (MCP servers) remains separate, later work.
- Delegating to other assistants (subagents), granting an assistant access to a code repository, and recording a detailed log of what the assistant did during a run are explicitly out of scope for this feature.
