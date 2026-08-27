# Data Model: Agentic Task Loop for Agent-Mode Assistants

All changes are additive migrations. No existing migration is edited (Constitution Principle II).

## Assistant (extended)

Existing table: `assistants`.

| Field | Type | Notes |
|---|---|---|
| `mode` | string, backed by `App\Enums\AssistantMode` (`Assistant`, `Agent`) | Default `assistant`. Determines whether `ConversationController@sendMessage` routes through the agent loop at all (FR-009: non-agent-mode assistants are provably unaffected). |
| `agent_config` | json, nullable | Present only when `mode = agent`. Holds the step limit and any other loop-tuning values (research.md #3), mirroring the existing `AiModel.config`/`additional_config` pattern rather than adding more flat columns. |

**Validation**: `agent_config` is only meaningful when `mode = agent`; enforced at the application layer (form request), not a DB constraint — same approach as other JSON-config columns in this codebase (`AiProvider.config_schema`).

## AiModel (extended)

Existing table: `ai_models`.

| Field | Type | Notes |
|---|---|---|
| `supports_tools` | boolean, default `false` | Set explicitly per model (research.md #6). Agent mode is unavailable for an assistant whose selected model has this `false` (FR-007). |

## Message (extended)

Existing table: `messages`.

| Field | Type | Notes |
|---|---|---|
| `tool_calls` | json, nullable | Normalized, provider-agnostic record of the tool call(s) requested and their result(s) for this turn (research.md #4). Null for every message outside the loop — existing conversations and non-agent-mode assistants are entirely unaffected. |

**Role values**: `role` (existing `varchar`) gains two values used only internally by the loop: `tool_call`, `tool_result`. These never reach the frontend chat transcript as separate bubbles for this feature (FR-011 — no persisted, user-facing run history); they exist so the loop can reconstruct message history for the next provider call within the same request.

**Relationships**: unchanged — `Message belongsTo Conversation`.

## Built-in tool (not a data entity)

The one tool this feature ships with, `get_current_datetime`, is a stateless PHP class, not a database row — there is no tools table for this feature (tool *registration* as data is MCP's job, out of scope here). Full contract: contracts/get-current-datetime-tool.md.

## Task (conceptual, not a new table)

Corresponds to a single `sendMessage` request/response cycle for an agent-mode assistant. Bounded by the existing `Conversation`/`Message` chain — a "task" is not a persisted entity of its own; it's the set of `tool_call`/`tool_result` messages generated between one user message and the resulting final-answer assistant message.

## Live progress state (not a table)

Ephemeral only (research.md #5): a cache key (e.g. `agent-progress:{conversation_id}`) holding the current step's status string, short TTL, overwritten each step, cleared when the loop ends. Never persisted to the database — this is deliberate, matching FR-011.

## Data isolation (Constitution Principle IV)

All of the above is reached exclusively through the requesting user's own `AssistantUser`/`Conversation`, via the existing `ResolvesAssistantUser` trait — the loop must not resolve an assistant's tools, config, or cache key from any account-level default. This applies directly to the historical bug this principle codifies (issue #44): the agent loop must not repeat the "first related record" shortcut.
