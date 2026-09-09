# VERA architecture diagrams

This suite is the visual, code-grounded map of VERA. It reflects the repository at commit `68c00ca`; routes, migrations, models, jobs, and runtime code take precedence over narrative claims in the root documentation.

## Reading order

1. [System context and containers](01-system-context.md) — boundaries, actors, channels, runtimes, and storage.
2. [Conversation runtime](02-conversation-runtime.md) — the end-to-end web, voice, image, agent, Telegram, and Discord paths.
3. [Data model](03-data-model.md) — overview and domain ERDs derived from migrations and Eloquent relationships.
4. [RAG and long-term memory](04-rag-and-memory.md) — archive ingestion, retrieval, hybrid search, and summarization.
5. [Provider resolution](05-provider-resolution.md) — LLM, embedding, STT, TTS, and image-generation selection.
6. [Avatar and World runtime](06-avatar-and-world.md) — VRM assets, poses, world loading, movement, sessions, music, and persistence.
7. [Deployment and operations](07-deployment-and-operations.md) — runtime topology, queue behavior, retries, and failure handling.
8. [Code-grounded caveats](08-code-grounded-caveats.md) — meaningful gaps between implementation, configuration, and README wording.

## Diagram legend

The diagrams use the same visual vocabulary throughout:

- blue: people and clients
- violet: VERA application/runtime components
- green: persisted state
- amber: asynchronous work
- rose: external services
- dashed arrows: optional, asynchronous, or fallback paths

Mermaid is intentionally limited to broadly supported `flowchart`, `sequenceDiagram`, `stateDiagram-v2`, and `erDiagram` syntax. Labels avoid HTML, icons, and renderer-specific extensions.

## Source-of-truth map

| Concern | Primary implementation |
| --- | --- |
| HTTP surface and auth boundary | `routes/web.php`, `routes/api.php`, `AuthController`, `ResolvesAssistantUser`, `WorldPolicy` |
| Conversation orchestration | `ConversationController`, `PromptDirector`, `AgentLoopRunner` |
| RAG and memory | `ArchiveController`, `SearchArchiveEntries`, `EmbedArchiveEntry`, `SummarizeConversation` |
| Provider selection | `LlmManager`, `TtsManager`, `ImageGenManager`, `AppServiceProvider` |
| Channel adapters | `TelegramPollCommand`, `TelegramService`, `DiscordController`, `ConversationController::sendDiscordMessage` |
| 3D runtime | `WorldPage`, `WorldScene`, `WorldEnvironment`, `FirstPersonController`, `ResidentController`, `VrmAvatar` |
| Persistence | `database/migrations`, `app/Models` |
| Runtime configuration | `config/ai.php`, `config/agent.php`, `config/queue.php`, `config/filesystems.php`, `.env.example` |

---

[Next: System context and containers](01-system-context.md)
