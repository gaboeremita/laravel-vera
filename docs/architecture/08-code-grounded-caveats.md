# Code-grounded caveats

These notes call out architecture-relevant discrepancies and boundaries discovered while tracing the implementation. They are not speculative roadmap items.

## Documentation and configuration differences

1. **Database requirement versus example configuration.** The README names PostgreSQL, and archive migrations use a 768-dimension vector column, vector indexing/similarity, and full-text search. However, `.env.example` currently defaults to MySQL. The complete archive search path should be treated as PostgreSQL-oriented; a fresh install that copies `.env.example` verbatim does not match the documented database choice.

2. **Embedding contract versus README setup.** `AppServiceProvider` binds `EmbeddingProvider` directly to `OllamaEmbeddingProvider`, which posts to Ollama's `/api/embed` and expects an `embeddings` array. The README currently calls this an OpenAI-compatible endpoint and shows an OpenRouter base URL. That sample does not match the implemented wire contract.

3. **Discord bridge boundary.** README and architecture prose correctly describe `node-discord-api`, but no bridge source or deployment manifest exists in this repository. Diagrams therefore model it as an external service. Its Gateway reconnect, trigger, delivery retry, and bot-token behavior cannot be verified here.

4. **No production topology is checked in.** There are no Docker, Kubernetes, supervisor, Horizon, or platform manifests. `composer run dev` starts a PHP development server, a `queue:listen` process, Pail, and Vite; it does not start Telegram, Discord, databases, Ollama, STT, TTS, or image services.

5. **No scheduled application work.** `routes/console.php` registers only the stock `inspire` command. Queue workers and the Telegram poller are long-running processes, not scheduled tasks.

## Runtime behavior worth preserving in future diagrams

1. **Conversation RAG is not hybrid.** `SearchArchiveEntries` combines PostgreSQL full-text and vector lists with reciprocal-rank fusion for the archive search endpoint. The full-text branch orders matching IDs by ID rather than an explicit relevance rank. `PromptDirector::withRetrieval` uses vector similarity only for prompt injection.

2. **Embedding refresh is narrower than embedded content.** The embedding job composes title, tag names, and content, but `ArchiveController` dispatches it only for a new entry or changed `content`. Changing only a title or tags can leave the stored embedding out of sync.

3. **Telegram is not feature-parity chat.** It performs archive retrieval and standard LLM chat, but it does not inject conversation long-term memory, trigger auto-summarization, run agent mode, execute the image-generation command/tool path, synthesize speech, or append world/Discord context.

4. **Agent mode has no config fallback.** Standard LLM calls may fall back to `AI_DEFAULT_*`; agent mode requires an explicitly selected `AiModel` with `supports_tools = true`.

5. **Model ownership validation is asymmetric.** Image-model selection and resolution constrain the provider to the authenticated user. LLM and TTS selection validate only that the model ID exists, and their managers load the selected ID globally. Voice providers are intentionally global in the schema; LLM providers are user-owned.

6. **Browser retries are not idempotent.** The frontend may retry timeout-like send failures up to three times. The endpoint persists the last user message before calling external providers and has no idempotency key, so retries can duplicate user messages.

7. **World resident motion is ephemeral.** A roaming resident's live position exists only in the browser and returns to its configured origin on reload. Only the player's session camera position is persisted, every ten seconds and on exit/unmount.

8. **Position meaning is camera-based.** The session saves `camera.position` including eye height. Restoration passes that value through collision-aware `restorePlayerPosition`; it is not a raw persisted foot position.

9. **The `User::conversations()` relation does not match the migrated schema.** Migrations replace `conversations.user_id` with `assistant_user_id`, while `User` still declares a conventional direct `hasMany(Conversation::class)`. Telegram's `/switch` command calls this relation, so that path appears inconsistent with the final schema. The canonical ownership path elsewhere is `User -> AssistantUser -> Conversation`.

10. **Global fallback wording needs nuance.** LLM, TTS, and image catalogs are UI-managed when selected, but their fallback endpoints and credentials still come from environment configuration. Embeddings and STT are config-only.

## Verified source boundaries

- Migrations and Eloquent models were used together: migrations establish columns/constraints; relations establish polymorphic and navigational intent.
- Route definitions were used instead of copied endpoint lists in older prose.
- Queue attempts, backoff, timeouts, locks, and cleanup come from the job classes and `AgentLoopRunner`.
- World states come from actual React effects/frame loops, not the feature specifications under `specs/`.
- Provider formats come from enums and manager implementations, not merely seeded provider names.

---

[Previous](07-deployment-and-operations.md) · [Index](README.md)
