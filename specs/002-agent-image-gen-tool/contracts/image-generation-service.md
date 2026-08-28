# Contract: `ImageGenerationService` (new)

Internal PHP service (`App\Services\ImageGenProviders\ImageGenerationService`). This is the shared class both `ConversationController::generateImageMessage` (manual `/create-image`) and `ImageGenerationTool` (agent-mode tool) call into — the concrete answer to "abstract the reusable part into its own class" (research.md #1).

## `generate()`

```php
generate(AssistantUser $assistantUser, Conversation $conversation, string $rawPrompt): array{enhancedPrompt: string, imageData: string}
```

Resolves the assistant's configured `ImageGenModel` (or falls back to `config('ai.image_gen')`), enhances `$rawPrompt` via the existing `ImageGenPromptEnhancer`, and calls the resolved provider's `generate()`. Does not create a `Message`, does not call any reaction/narration LLM, does not store an `Image` — those remain each caller's own responsibility, since they legitimately differ between the manual command and the tool (research.md #2, #3).

Throws whatever the underlying `ImageGenProvider::generate()` throws on failure (HTTP error, provider error response) — no new exception type, no swallowing (Constitution Principle V).

## `isAvailableFor()`

```php
isAvailableFor(AssistantUser $assistantUser): bool
```

`true` unless `ImageGenManager::forAssistantUser($assistantUser)` would throw `InvalidArgumentException` (no per-assistant model and no global `ai.image_gen.url` configured) — research.md #6. Used once, when `ConversationController::sendMessage` decides whether to include `ImageGenerationTool` in the tools array passed to `AgentLoopRunner` (spec.md FR-005).

## `resolveTimeoutFor()`

```php
resolveTimeoutFor(AssistantUser $assistantUser): int
```

Returns the assistant's resolved `ImageGenModel->config['timeout']` if one is configured, otherwise `config('ai.image_gen.timeout')` — the same value the underlying HTTP call itself is actually bounded by. Used by `ImageGenerationTool::timeoutSeconds()` (research.md #4); not used by the manual command, which has no `AgentLoopRunner`-style outer timeout to reconcile with.

## Callers

| Caller | Uses |
|---|---|
| `ConversationController::generateImageMessage` (existing, refactored) | `generate()` only — keeps its own message-creation, `reactToGeneratedImage` call, and `Image::storeFromBase64` exactly as today |
| `ImageGenerationTool::handle()` (new) | `generate()`, then its own carrier-message creation and `Image::storeFromBase64` (contracts/generate-image-tool.md) |
| `ConversationController::sendMessage` (existing, extended) | `isAvailableFor()` to decide whether to register the tool at all |
| `ImageGenerationTool::timeoutSeconds()` (new) | `resolveTimeoutFor()` |

## Backward compatibility

`generateImageMessage`'s externally-visible behavior (its return array, the HTTP response shape for `/create-image`) is unchanged — this is a pure internal refactor extracting three existing lines (resolve, enhance, generate) into a shared class it now calls instead of inlining.
