# Contract: Avatar Background Status Endpoint

New route, added under the existing `assistants/{assistant}` group in `routes/api.php`, next to the existing `conversations.agent-progress` route it mirrors.

## `GET /api/assistants/{assistant}/conversations/{id}/avatar-background`

**Auth**: `auth:sanctum`, same as every other route in this group. `{assistant}`/`{id}` are resolved and ownership-checked exactly like `AgentProgressController@show` (`resolveAssistantUser($request, $assistant)->conversations()->findOrFail($id)`) — see Constitution Principle IV note in [plan.md](../plan.md).

**Response 200**:

```json
{
  "in_progress": false,
  "status": null,
  "background": {
    "floor_url": "https://.../storage/avatar-backgrounds/42/f1a2...-floor.png",
    "surroundings_url": "https://.../storage/avatar-backgrounds/42/f1a2...-surroundings.png",
    "source_description": "a neon-lit bar downtown",
    "generated_at": "2026-08-28T21:04:11+00:00"
  }
}
```

- `in_progress` / `status`: same shape as the existing `agent-progress` endpoint (`in_progress: status !== null`), read from `avatar-background-progress:{conversation_id}`.
- `background`: the current cache entry (see [data-model.md](../data-model.md)) read from `avatar-background:{conversation_id}`, or `null` if nothing is cached (never generated, or the cache expired — FR-012a). When `null`, the frontend falls back to the bundled default background pair (research.md §9) until a background is generated. **This endpoint never triggers generation itself** — it is purely a read, consistent with `AgentProgressController`. The cache-miss case is instead handled by the conversation-open trigger below (research.md §8), not by polling.

**No POST endpoint is introduced.** Every trigger surface described in the spec reuses an existing endpoint instead of adding a new one:

| Trigger (spec) | Surface |
|---|---|
| Manual command (FR-002) | `POST /api/assistants/{assistant}/conversations/{id}/messages` (`sendMessage`) — a new `/change-background <description>` slash command, parsed the same way the existing `/create-image` command already is (`extractImageGenPrompt`). |
| Agent-mode request (FR-003) | Same `sendMessage` endpoint, when `assistant.mode === Agent` — handled by the new `AvatarBackgroundTool` (see [avatar-background-tool.md](avatar-background-tool.md)), registered alongside `ImageGenerationTool`. |
| Automatic initial (FR-004) | `POST /api/assistants/{assistant}/conversations` (`store`) — dispatches generation right after the conversation (and its opening message) is created. |
| Automatic mid-conversation (FR-005) | Same `sendMessage` endpoint — a `[scene: ...]` tag detected in the assistant's reply (see [research.md](../research.md) §2) dispatches regeneration. |
| Cache-miss on reopen (FR-012a) | `GET /api/assistants/{assistant}/conversations/{id}/messages` (`show`) — dispatches generation, seeded from recent conversation context, when a conversation's first page loads with no cached background and none already in progress (see [research.md](../research.md) §8). |

All five dispatch the same `GenerateAvatarBackground` job; the `avatar-background` polling endpoint above is the only new HTTP surface, and it stays read-only.
