# Research: Agentic Task Loop for Agent-Mode Assistants

## 1. Anthropic tool-calling wire format

**Decision**: `AnthropicProvider` sends a `tools` array (`{name, description, input_schema}`) and `tool_choice` on the request. When the model wants to call a tool, the response has `stop_reason: "tool_use"` and one or more `{type: "tool_use", id, name, input}` blocks inside `content`. The loop continues by appending the full `content` array back as an `assistant` message, then a `user` message containing `{type: "tool_result", tool_use_id, content, is_error?}`. The loop ends when `stop_reason` is anything other than `"tool_use"`.

**Rationale**: Confirmed directly against current Anthropic API docs (platform.claude.com) rather than assumed from prior knowledge, since the API surface changes over time.

**Alternatives considered**: Anthropic's own MCP connector (`mcp_servers` param) was considered as a way to skip building tool-call plumbing entirely, but it only proxies *MCP* tools specifically and requires a publicly reachable HTTPS server — not applicable here, since this feature's one built-in tool (`get_current_datetime`, contracts/get-current-datetime-tool.md) is a local PHP function, and MCP itself is out of scope for this spec.

## 2. OpenAI-compatible tool-calling wire format

**Decision**: `GenericProvider` sends `tools` as `[{type: "function", function: {name, description, parameters}}]`. A tool-call response has `finish_reason: "tool_calls"` and `choices[0].message.tool_calls` (`{id, function: {name, arguments}}`, `arguments` as a JSON string). The loop continues by appending the assistant message as-is, then one `{role: "tool", tool_call_id, content}` message per call. Loop ends when `finish_reason` isn't `"tool_calls"`.

**Rationale**: This is the standard OpenAI Chat Completions function-calling shape, which OpenRouter and other OpenAI-compatible endpoints (what `GenericProvider` targets) implement consistently.

**Alternatives considered**: None — this is the de facto standard for this API family; there is no viable alternative wire format for OpenAI-compatible endpoints.

## 3. Normalizing the two wire formats for the loop

**Decision**: No shared translation layer (per the existing architectural decision). `LlmResponse` gains a normalized, provider-agnostic shape for a requested tool call (id, name, arguments) regardless of source; each provider is responsible for producing that shape from its own wire format and for re-serializing tool results back into its own format on the next call. The orchestrating loop only ever sees the normalized shape.

**Rationale**: Keeps provider-specific parsing localized to each provider class (consistent with how `formatMessage()` already handles image formatting differently per provider today), rather than introducing a new abstraction layer the constitution's "No Speculative Abstraction" principle would flag.

**Alternatives considered**: A shared `ToolCallTranslator` service was considered and rejected — two call sites don't justify an abstraction, and the two wire formats diverge enough (block-array vs. flat list, string content vs. array content) that a shared translator would mostly be conditional branches per provider anyway.

## 4. Persisting a tool-call turn on `Message`

**Decision**: Add a nullable `tool_calls` JSON column to `messages`, storing the normalized (provider-agnostic) tool-call request(s) and their result(s) for that turn. `role` gains two new values used only internally by the loop (`tool_call`, `tool_result`) alongside the existing `user`/`assistant`/`system`. Existing rows and existing single-turn conversations are entirely unaffected (column is nullable, additive migration).

**Rationale**: Reuses the existing single `messages` table rather than introducing a new one, consistent with `thinking` already being an optional per-message column for provider-specific data. Storing the normalized shape (not raw Anthropic/OpenAI JSON) means the loop can replay a conversation's history without re-deriving which provider generated which turn.

**Alternatives considered**: A separate `tool_calls` table was considered (closer to the deferred agent-run action log's eventual shape) but rejected for this feature — the spec explicitly defers persisted run history; this column exists only so the loop can construct the next request's message history within the same request, not for later querying.

## 5. Live progress signal (FR-010)

**Decision**: While the loop runs (synchronously, inside the existing `sendMessage` request), each step writes a short-lived status string (e.g., "Calling tool: get_weather") to cache, keyed by conversation ID, with a short TTL. A new lightweight endpoint (`GET /api/assistants/{assistant}/conversations/{id}/agent-progress`) returns the current cached status. The frontend polls this endpoint on a short interval only while an agent-mode send is in flight, and stops polling once the main request resolves.

**Rationale**: The spec requires live visibility (FR-010) but not persistence (FR-011), and not any particular delivery mechanism. VERA has no broadcasting infrastructure today (`broadcasting.default` is `log`, no Reverb/Pusher installed) — introducing one solely for this feature would be exactly the kind of scope growth Constitution Principle VII rules out. Polling a cache-backed value is the smallest mechanism that satisfies FR-010 as written.

**Alternatives considered**: A WebSocket/broadcast channel (Laravel Reverb) was considered — rejected as new infrastructure disproportionate to what FR-010 actually asks for, and better suited to when the deferred, persisted action log is eventually built (which will likely need real-time delivery for a full run history anyway). Server-Sent Events on the main `sendMessage` request was also considered — rejected because it would require restructuring the existing synchronous controller action into a streamed response, a larger change than this feature's scope.

## 6. Detecting whether a configured model supports tool-calling (FR-007)

**Decision**: Add a `supports_tools` boolean to `ai_models` (default `false`, set explicitly per model). Agent mode is blocked at the UI/validation layer for an assistant whose selected model has `supports_tools = false`, satisfying FR-007 without a request round-trip. As a safety net, a tool-enabled request that the provider rejects specifically for unsupported tool-calling is still caught and surfaced as a clear error (Constitution Principle V — errors fail loudly), rather than silently retried without tools.

**Rationale**: Some OpenAI-compatible endpoints silently ignore an unsupported `tools` param rather than erroring, which would make runtime-only detection unreliable for FR-07's "clearly indicate" requirement. An explicit, admin-set flag is a small, honest data point rather than a fragile capability-sniffing mechanism.

**Alternatives considered**: Runtime-only detection (attempt the call, catch failure) was considered and rejected as the sole mechanism, for the silent-ignore reason above. A maintained static list of known tool-capable models (by provider/model name) was also considered and rejected as unnecessary bookkeeping given the explicit-flag approach already solves this.

## 7. Testing strategy for the loop

**Decision**: Pest feature tests fake the outbound LLM HTTP calls with `Http::fake()`, sequencing responses (e.g., a `tool_use` response followed by a final-answer response) to deterministically exercise each user story without calling a real model. The one built-in tool, `get_current_datetime` (contracts/get-current-datetime-tool.md), is used across all three test scenarios; Carbon's test clock (`Carbon::setTestNow()`) makes its output deterministic.

**Rationale**: Matches Constitution Principle VI (feature-test-first, factory-backed) and the project's existing Pest convention; `Http::fake()` is already how this kind of external-call testing is done elsewhere in a Laravel app of this shape.

**Alternatives considered**: None for the faking approach — this is the standard, already-idiomatic approach for this codebase. For the tool itself, several genuinely useful options were weighed (web search, VERA's own archive search, a calculator, weather lookup, reminders/notes) before settling on date/time lookup — see #8 below.

## 8. The built-in tool: `get_current_datetime`

**Decision**: A single, parameterless tool that returns the current date/time in the server's configured timezone. Full contract in contracts/get-current-datetime-tool.md.

**Rationale**: Genuinely useful to a companion assistant (a real, everyday question, not a manufactured fixture) rather than the internal-only stand-in ("lookup_record" over fake fixture data) considered first. Requires no new external dependency or credential — unlike a web search or weather API, it needs no HTTP call at all.

**Alternatives considered**: Weather lookup (Open-Meteo, free/keyless) and a calculator/expression evaluator were the closest runners-up — both are real and simple, but were passed over once date/time was explicitly chosen. Web search was rejected as introducing a new external dependency and behaving inconsistently across providers (Anthropic has a native, provider-side web-search tool; generic OpenAI-compatible endpoints do not, which would conflict with FR-006's cross-model consistency requirement). VERA's own archive search (exposing `RetrievalService` as an agent-callable tool instead of always-on retrieval) and a reminders/notes tool were both rejected as larger in scope than a single proving-the-loop tool warrants — the latter would need new persistence, which Constitution Principle VII cautions against for this feature.

**Known limitation**: no parameters means this tool cannot itself force a dependent second call or a long step-limit-exhausting chain — User Story 2 and User Story 3 are validated through Pest test sequencing (#7 above), not a real multi-step interaction with this specific tool in the product. This was an accepted tradeoff, not an oversight.
