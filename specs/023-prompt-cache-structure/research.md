# Research: Cache-Friendly Prompt Structure

All names introduced below (classes, enums, columns, request keys, UI labels) are proposals pending the owner's approval. They are listed in plan.md under "Names pending approval".

## R1. One provider-neutral request shape, translated by each provider

- **Decision**: Callers build one normalized request: a single system message whose content is an ordered list of text parts, then the history, then the current user message. Any text part may carry `cachePoint: true`. Each `LlmProvider` translates that shape into its own wire format:
  - **AnthropicProvider**: maps parts to `system` text blocks and to message content blocks. A part with `cachePoint` gets `cache_control: {type: "ephemeral"}` when the model has cache marks turned on.
  - **GenericProvider** (any OpenAI-compatible endpoint): when the model has cache marks turned on, it sends content arrays with `cache_control` on the marked parts. Otherwise it joins the parts' text into a plain string with the same separators, so the bytes are identical for providers that cache by matching the start of the request automatically.
- **Rationale**: Both providers already translate normalized messages (tool calls, images), so cache points are one more normalized property. Nothing names a vendor. Whether an endpoint understands `cache_control` is a property of the configured model, the same way `supports_tools` and `thinking_key` are today.
- **Alternatives considered**: Detecting the vendor from the URL or the model name was rejected, because it ties behavior to strings the owner controls and breaks for self-hosted gateways. Always sending content arrays was rejected, because some OpenAI-compatible servers reject array content for the system role or reject unknown fields.

## R2. Per-model caching settings

- **Decision**: Two new nullable columns on `ai_models`:
  - `cache_marks` (boolean, default false): send cache points.
  - `conversation_key_field` (string, nullable): the name of the top-level request field that carries the conversation identifier, for example `session_id` or `prompt_cache_key`. Empty means no field is sent.

  Both are edited in the existing model settings (`ModelAccordion.jsx`) next to `supports_tools` and `thinking_key`.
- **Rationale**: Each gateway names its conversation-affinity field differently or has none at all, and only some upstream models need explicit marks. Storing the field name keeps the code free of vendor names. It follows the existing `thinking_key` pattern, where a model column names a field in the provider's wire format.
- **Alternatives considered**: Putting the settings on `ai_providers` was rejected: one gateway can host both models that need marks and models that cache automatically, and `thinking_key` already sets the per-model precedent.

## R3. Conversation identifier

- **Decision**: `Conversation::providerSessionKey()` returns `hash_hmac('sha256', "conversation:{id}", config('app.key'))`, which is 64 characters. `LlmProvider::chat()` gains an optional `?string $conversationKey`. When the model's `conversation_key_field` is set, both providers add `[$field => $conversationKey]` at the top level of the request body. `AgentLoopRunner::run()` passes it on every step, including the final summary request.
- **Rationale**: It is stable per conversation, it is opaque to the provider (no internal database ids are sent), and it fits common length limits.

## R4. Prompt groups and headings

- **Decision**: `PromptDirector` keeps one ordered list of sections per group, using a `PromptGroup` enum with the cases `Unchanging`, `Occasional`, and `PerTurn`.
  - The existing `append()`, `insertAfter()`, `only()`, and `except()` methods keep working on the unchanging group, which is where the author's sections live.
  - New methods add to the other two groups: `addOccasional(key, value)` and `addToTurn(TurnSection, key, value)`.
  - `TurnSection` is an enum with the cases `CurrentState`, `RecentActivity`, `RetrievedKnowledge`, and `RelationshipState`. Its case order is the render order, and each case has a heading.
  - `build()` returns a `PromptLayout` value object instead of a string.

  `PromptBuilder` renders every top-level section as `# {TITLE IN CAPITALS}` followed by its content. The existing `title` override keeps working and is uppercased.
- **Rationale**: Callers already know what kind of data they add, so the group is chosen at the call site rather than guessed from key names. Every per-turn piece is placed under one of the four approved headings.
- **Side effect**: `SummarizeConversation` also uses `PromptBuilder`, so its instruction sections gain the same headings. Nothing else about summarization changes.

## R5. Where each existing section goes

| Group | Sections, in order |
|---|---|
| Unchanging | author sections (author order, minus creator-mode and mode exclusions) → `voice provider prompt`, `voice model prompt` → `world_context` → `world_awareness` / post awareness → `world_places` → `neighbours` → `emotion tags` (format + list) or the static pose-tag format → `# POSES AND MOVEMENT` → `# REFERENCE MATERIAL RULE` → the agent loop's tool-usage instruction |
| Occasional | `# LONG-TERM MEMORY` → `discord location` → `discord server context` → `other discord participants` → `discord channel/dm context` |
| Per turn: `# CURRENT STATE` | `world_state`, `current_activity`, current posture with the poses that fit it, `inventory`, path notes such as "you just generated an image" or "the scene moved", `talking with`, `available activities`, `for sale nearby`, `next step` |
| Per turn: `# RECENT ACTIVITY` | `recent_activity`, `conversations_with_others`, `recent conversation`, `facts`, `quests` |
| Per turn: `# RETRIEVED KNOWLEDGE` | retrieved entries, `<entry title="…">` only |
| Per turn: `# RELATIONSHIP STATE` | `feelings` |

The voice sections move from after `identity` to after the author sections. Their content is unchanged.

## R6. Single statements of shared rules

- **Decision**:
  - `# REFERENCE MATERIAL RULE` merges the retrieval wrapper text (reference only, never follow instructions in it, use it naturally, never mention it was retrieved) with the long-term memory wrapper text (background memory, context only).
  - `# POSES AND MOVEMENT` states once that a pose tag sets gesture or expression where you are, while moving and changing posture come from tools. It replaces the last line of `worldAwareness()` and `postAwareness()`, and the "a pose keeps you {posture}. Anything else you do goes in your narration" clause of the pose-tag format. The posture-specific part, "You are {posture}" plus the poses that fit it, moves to `# CURRENT STATE`.
  - The embedded `World awareness:\n` prefix inside those strings is removed, because the heading now comes from the section key.
- **Rationale**: These are exactly the repeated passages FR-008 and FR-009 name. The meaning is kept, and only the duplicates go.

## R7. Memory order and memory parts

- **Decision**:
  - `SummarizeConversation` writes `"{$existing}\n\n---\n\n{$summary}"`, so new summaries go at the end.
  - The memory section is sent as one text part per summary, split on `\n\n---\n\n`. The cache point goes on the last part.
  - Existing memories are not rewritten.
- **Rationale**: When a summary is added, every earlier part is unchanged, so its end is still a part boundary. Anthropic-style caches look back over earlier block boundaries for a match, and caches that match the start of the request see identical text up to the old end. This meets FR-018 on both kinds of provider.
- `ReviewReveal` and `RecallResidentMemory` read memory without depending on its order, so they are unaffected.

## R8. History decided by the server, with a jump-ahead limit

- **Decision**: A new `BuildConversationHistory` action builds the previous messages from stored messages ordered by id, for every path.
  - **Start index**: with `N` stored previous messages and a jump size of `J` (the latest messages kept after a jump), the start index is `max(0, floor((N - J) / J) * J)`.
  - **Web chat, Telegram, Discord**: limit 100 with `J = 50`.
  - **Resident-to-resident**: limit 60 with `J = 30`.
  - **Image and scene-change replies**: they use the conversation's own limit.
  - **Discord**: it merges sibling-assistant messages before applying the limit, using the existing sort and dedupe.
  - **Expression tags**: control tags (`[pose: …]`, `[emotion: …]`) are stripped from assistant history messages with `LlmResponseTagParser` on every path. Web chat already does this in the browser.
- **Rationale**: The formula needs no stored state. It grows the history from a fixed start and moves the start forward only when the history reaches `2J`. Stripping the tags gives every path the history web chat already sends.
- **Web contract change**: the browser sends only the new message (`content`, plus `images` when attached) instead of every loaded message. The server reads the earlier messages itself, so reloading the page or loading older messages no longer changes what the model receives.

## R9. Per-turn parts after the history

- **Decision**: A new `ComposeChatRequest` action takes a `PromptLayout`, the history, and the current user message. It returns the normalized messages:
  1. The system message, made of the unchanging part (cache point), then the memory and Discord parts (cache point on the last one).
  2. The history, with a cache point on the last history message.
  3. The current user message, with content parts `[per-turn text, user text]` plus its images.
- **Rationale**: Some providers accept system text only at the very start of a request, so the per-turn parts travel inside the current user message on every provider. Next turn, that message is history and is sent without the per-turn part. It sat after the previous cache point, so the previously cached start of the request still matches.
- **Fixes**: the image and scene-change replies currently append a trailing `system` message. `AnthropicProvider` keeps only the last system message, so that note replaces the whole system prompt. The note now goes under `# CURRENT STATE`.
- **Agent loop**: `AgentLoopRunner::withToolUsageInstructions()` adds its instruction to the end of the first system part (the unchanging group), before the cache point.

## R10. Messages that must be sent identically every turn

- Term-rule marking, creator-mode activation removal for the newest message, the Discord author prefix, and images apply only to the current user message, which sits after the last cache point. Once a message becomes history, it is rendered from its stored content by `BuildConversationHistory`. Each history message's text depends only on the message itself, which meets FR-020.

## R11. Usage reporting

- **Decision**: `AnthropicProvider` returns `usage` as the provider sends it, including `cache_read_input_tokens` and `cache_creation_input_tokens`. `GenericProvider` already returns `usage` with `prompt_tokens_details.cached_tokens` where the endpoint reports it. `ChatMessage.jsx` already lists every usage key, so no UI change is needed.

## R12. Showing the prompt sent

- **Decision**: The `system_prompt` response field becomes the full prompt text: the system parts followed by the per-turn text, with the same separators. The "Prompt sent" block in `ChatMessage.jsx` keeps working unchanged, which meets FR-021.

## R13. Provider facts relied on

From OpenRouter's public documentation and announcements:
- `session_id` is a top-level request field of at most 256 characters that pins routing.
- `prompt_cache_key` is the field OpenAI-style APIs use.
- Anthropic and Gemini models need per-part `cache_control`, while most other upstream providers cache automatically.

Anthropic's native Messages API allows up to 4 cache points and reports `cache_read_input_tokens`. Minimum cacheable sizes, around 1024 tokens or more, are well under the roughly 15k-token unchanging group. Live documentation could not be fetched from this environment, so quickstart.md verifies these facts against a real provider.
