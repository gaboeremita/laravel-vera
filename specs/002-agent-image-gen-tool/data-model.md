# Data Model: Agent Image Generation Tool

No new tables and no schema changes at all — this feature is purely new service/tool classes wired into existing structures. Every entity below already exists (added by `001-agentic-task-loop` or earlier); this document only describes how this feature *uses* them.

## Message (existing, unchanged schema)

Each `generate_image` tool call creates one new `assistant`-role `Message` immediately when it runs (research.md #3), with `content` set to an empty string and no `tool_calls` value — this is a plain image carrier, not the `tool_call`-role trace row (`AgentLoopRunner::recordToolCall` already creates that separately, unchanged, per `001-agentic-task-loop`'s data-model.md). It is a normal, unfiltered `assistant` message: `ConversationController::show`'s existing `where('role', '!=', 'tool_call')` query surfaces it exactly like any other assistant reply.

**Relationship to Image**: uses the existing `Message::image(): MorphOne` relation — no change.

## Image (existing, unchanged schema)

Created via the existing `Image::storeFromBase64(string $base64, Model $imageable, string $storagePath): static` (`app/Models/Image.php`), attached to the tool's carrier `Message` instead of to a `generateImageMessage`-created one. Same disk, same mime-sniffing, same URL accessor — no change to this class.

**Multi-image tasks (FR-009)**: each `generate_image` call creates its own carrier `Message` + `Image` pair, so a task that calls the tool three times produces three ordinary assistant messages, each with its own image — no new relation type needed (a `Message` still has at most one `Image`, unchanged).

## ImageGenerationService (new, not a data entity)

A stateless service class, not a database row — same category as `ImageGenManager`/`ImageGenPromptEnhancer` it wraps. Two methods:

- `generate(AssistantUser $assistantUser, Conversation $conversation, string $rawPrompt): array{enhancedPrompt: string, imageData: string}` — resolve → enhance → generate (research.md #1).
- `isAvailableFor(AssistantUser $assistantUser): bool` — whether a usable image-gen configuration exists for this assistant (research.md #6).
- `resolveTimeoutFor(AssistantUser $assistantUser): int` — the resolved image-gen timeout (per-assistant `ImageGenModel` override, or the global config default) used by `ImageGenerationTool::timeoutSeconds()` (research.md #4).

## ImageGenerationTool (new, not a data entity)

Implements the (extended) `App\Contracts\AgentTool` interface — a stateless-per-request PHP class, constructed with the `AssistantUser` and `Conversation` for the current request (mirroring how `AgentLoopRunner` is already constructed fresh per request in `ConversationController::sendMessage`). Full contract: contracts/generate-image-tool.md.

## AgentTool (extended interface, not a data entity)

Existing interface (`app/Contracts/AgentTool.php`) gains one new required method:

| Method | Notes |
|---|---|
| `timeoutSeconds(): int` | Per-tool timeout budget for `AgentLoopRunner::executeWithTimeout` (research.md #4). `GetCurrentDatetimeTool` and `BasicCalculatorTool` both return `config('agent.tool_timeout')`, preserving today's behavior exactly. `ImageGenerationTool` returns the resolved image-gen timeout plus a fixed buffer. |

## Data isolation (Constitution Principle IV)

`ImageGenerationTool` is constructed with the specific `AssistantUser`/`Conversation` already resolved by `ResolvesAssistantUser` for the current request (same pattern `001-agentic-task-loop` established for the loop generally) — it never re-resolves the assistant's image-gen configuration from any account-level default, only through `ImageGenManager::forAssistantUser($assistantUser)`, exactly as the existing manual `/create-image` path already does.
