# Contract: `generate_image` Tool

The built-in image-generation tool for agent-mode assistants (spec.md User Story 1). Registered directly in code, alongside `get_current_datetime` and `basic_calculator` — not MCP-sourced. Only offered to assistants for whom `ImageGenerationService::isAvailableFor()` returns `true` (research.md #6, spec.md FR-005).

## Definition

- **Name**: `generate_image`
- **Description**: "Generates an image from a text description and shows it to the user. Use when the user asks to see, generate, draw, or create a picture or image of something."
- **Parameters**: `{prompt: string}` — a free-text description of the desired image, enhanced internally the same way `/create-image`'s argument already is (research.md #1).

## Construction

Unlike `GetCurrentDatetimeTool`/`BasicCalculatorTool` (parameterless constructors), `ImageGenerationTool` is constructed per-request with the resolved `AssistantUser` and `Conversation` already available in `ConversationController::sendMessage` at the point the tool list is built (`ConversationController.php:254-257`), plus an `ImageGenerationService` instance:

```php
new ImageGenerationTool($imageGenerationService, $assistantUser, $conversation)
```

## Behavior

1. Reject a missing or empty `prompt` argument with a thrown `\RuntimeException` (FR-008), the same pattern `BasicCalculatorTool` uses for a missing `expression` — never generate a placeholder image.
2. Call `ImageGenerationService::generate($assistantUser, $conversation, $arguments['prompt'])` (research.md #1) to get `{enhancedPrompt, imageData}`.
3. Create a new `assistant`-role `Message` on `$conversation` with empty `content`, then `Image::storeFromBase64($imageData, $carrierMessage, $storagePath)` using the same `"messages/{$assistantUser->user_id}/{$conversation->id}"` storage-path shape `sendMessage`/`generateImageMessage` already use (research.md #3).
4. Return a short confirmation to the model, plus the stored image's URL — never the image bytes themselves (research.md #3; a URL is a short string, not the payload the research note warns against embedding):

```json
{ "status": "success", "enhanced_prompt": "...", "image_url": "..." }
```

`image_url` was added post-implementation (found via live testing): the frontend has no other way to learn about the carrier message/image created in step 3 within the same response — see plan.md's "Post-implementation additions."

Any failure in steps 2-3 (unresolvable provider, HTTP failure, storage failure) MUST propagate as a thrown exception rather than being swallowed — `AgentLoopRunner`'s existing `\Throwable` catch around each tool call already surfaces it to the model and to the retry/failure-summary path (Constitution Principle V), so no new error-handling logic is needed inside the tool itself beyond letting real exceptions bubble up.

## Timeout

`timeoutSeconds()` returns `ImageGenerationService::resolveTimeoutFor($assistantUser) + 30` (research.md #4) — not the global `config('agent.tool_timeout')` the other two tools use.

## Retries

`retryAttempts()` returns `1` — no retry (research.md #5) — not the global `config('agent.tool_retry_attempts')` (3) the other two tools use. A failed generation is surfaced to the model immediately rather than retried against a paid, slow provider.

## Output shape sent to the model

```json
{ "status": "success", "enhanced_prompt": "a detailed, enhanced description of the generated image", "image_url": "https://.../messages/1/2/uuid.png" }
```

On failure, the existing `AgentLoopRunner` failure path already wraps the exception message as `{"error": "..."}` — the tool does not need to produce its own error shape.

## Behavior notes

- Non-deterministic and I/O-bound, unlike the other two built-in tools — Pest tests fake the outbound HTTP call (`Http::fake()`) the same way `001-agentic-task-loop`'s tests fake the LLM call, per research.md #8.
- Calling this tool more than once in the same task is allowed (FR-009) and produces one carrier `Message`/`Image` pair per call, each independent of the others (data-model.md).
