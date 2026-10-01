# Data Model: Cache-Friendly Prompt Structure

Names are listed in plan.md under "Names".

## Stored data

### `ai_models` (new migration, existing rows unchanged)

| Column | Type | Default | Meaning |
|---|---|---|---|
| `cache_marks` | boolean | `false` | Send cache points to this model's endpoint |
| `conversation_key_field` | string, nullable | `null` | Name of the top-level request field that carries the conversation identifier. Empty means no field is sent. |

Validation in `AiModelController`:
- `cache_marks`: boolean.
- `conversation_key_field`: nullable string, max 64 characters, matching `^[A-Za-z_][A-Za-z0-9_]*$`.

A new `AiModelFactory` (none exists yet) has two states, one with `cache_marks` on and one with a `conversation_key_field` set.

### `conversations.long_term_memory` (no schema change)

- New summaries are appended as `{existing}\n\n---\n\n{summary}`.
- Summaries written before this feature stay as they are.
- In the prompt, the text is split on `\n\n---\n\n` into one part per summary.

## Values built per request (not stored)

### `PromptGroup` (enum)

`Unchanging`, `Occasional`, `PerTurn`. The case order is the render order.

### `TurnSection` (enum)

| Case | Heading |
|---|---|
| `CurrentState` | `# CURRENT STATE` |
| `RecentActivity` | `# RECENT ACTIVITY` |
| `RetrievedKnowledge` | `# RETRIEVED KNOWLEDGE` |
| `RelationshipState` | `# RELATIONSHIP STATE` |

The case order is the render order. A turn section with no content is not rendered.

### `PromptLayout` (value object returned by `PromptDirector::build()`)

| Member | Meaning |
|---|---|
| `unchanging(): string` | Rendered unchanging group |
| `occasionalParts(): string[]` | Memory parts (one per summary, the first carrying the `# LONG-TERM MEMORY` heading), then Discord sections. Empty when there are none. |
| `turn(): string` | Rendered per-turn sections, or `''` |
| `fullText(): string` | Everything joined with `\n\n`. Used for one-shot callers and for the `system_prompt` response field. |

Rule: `unchanging()` depends only on the author prompt, mode exclusions, voice model, world, region, resident, and assistant kind. Lists inside it are ordered by stable keys: places in layout order, poses by id.

### Normalized message (extends the `LlmProvider` contract)

```text
role: 'system' | 'user' | 'assistant' | 'tool'
content: string | null | list<{type: 'text', text: string, cachePoint?: bool}>
images?: list<base64>          (unchanged)
tool_calls?, tool_call_id?     (unchanged)
```

Provider translation:
- `cachePoint` becomes `cache_control: {type: 'ephemeral'}` only when the model's `cache_marks` is on.
- Otherwise, text parts are joined with `\n\n` into a string.
- A request has at most 3 cache points.

### History limit

| Path | Limit | Kept after a jump (`J`) |
|---|---|---|
| Web chat, Telegram, Discord, image and scene-change replies | 100 | 50 |
| Resident-to-resident turns | 60 | 30 |

The start index is `max(0, floor((N - J) / J) * J)` over the `N` previous messages ordered by id.

### Conversation identifier

`Conversation::providerSessionKey()` is an HMAC-SHA256 of `conversation:{id}` using the app key, as 64 hex characters.
