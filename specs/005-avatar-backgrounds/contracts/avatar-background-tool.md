# Contract: `AvatarBackgroundTool` (agent-mode)

Implements the existing `App\Contracts\AgentTool` interface (`app/Contracts/AgentTool.php`), registered in `ConversationController::sendMessage()` alongside `ImageGenerationTool`, but only when the assistant is eligible (`portrait_type === AssistantPortraitType::Avatar3D` — FR-014), the same way `ImageGenerationTool` is only registered `if ($imageGenerationService->isAvailableFor($assistantUser))`.

This is what lets a user in agent mode say "change the background to a futuristic park" (FR-003) — the tool-calling model invokes this tool directly rather than the request going through the `/change-background` slash-command path.

## `name()`

`change_avatar_background`

## `description()`

> "Changes the visible background scene behind your 3D avatar. Use when the user asks to change, set, or update the background/scene/setting, or when the conversation's setting has clearly moved somewhere new."

## `parameters()`

```json
{
  "type": "object",
  "properties": {
    "description": {
      "type": "string",
      "description": "A description of the new setting/location for the background."
    }
  },
  "required": ["description"]
}
```

## `handle(array $arguments)`

1. Validates `description` is present (same pattern as `ImageGenerationTool::handle()` validating `prompt`).
2. Dispatches `GenerateAvatarBackground` for the current `AssistantUser`/`Conversation` (constructor-injected, same as `ImageGenerationTool`) with the raw description — same job every other trigger surface uses.
3. Returns immediately (job is queued, not awaited — FR-017):

```json
{
  "status": "queued",
  "description": "a futuristic park"
}
```

Unlike `ImageGenerationTool`, this tool does **not** create a carrier message or return an `image_url` — there is nothing to show inline in the chat transcript. The frontend picks up the new background asynchronously via the polling endpoint ([avatar-background-api.md](avatar-background-api.md)), the same way it picks up any other automatically-triggered background change.

## `timeoutSeconds()` / `retryAttempts()`

Since `handle()` only dispatches a job rather than performing generation inline, this returns a small fixed timeout (e.g. a few seconds) rather than `ImageGenerationTool`'s pattern of deriving it from the image-gen provider's own timeout — there is no synchronous provider call in the tool call itself.
