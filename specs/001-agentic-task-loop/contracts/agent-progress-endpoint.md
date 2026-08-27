# Contract: Agent Progress Endpoint

New HTTP endpoint satisfying FR-010 (research.md #5). Follows the existing `/api/assistants/{assistant}/conversations/{id}/...` nesting convention.

## `GET /api/assistants/{assistant}/conversations/{id}/agent-progress`

**Auth**: `auth:sanctum`, scoped to the requesting user's own `AssistantUser`/`Conversation` (Constitution Principle IV) — same resolution pattern as the existing `ConversationMemoryController`.

**Response 200**:

```json
{
  "in_progress": true,
  "status": "Calling tool: get_weather"
}
```

- `in_progress: false`, `status: null` when no agent-mode loop is currently running for this conversation (nothing cached, or the cache entry expired).

**Polling contract**: The frontend polls this endpoint only while a `sendMessage` request to an agent-mode assistant is in flight, on a short fixed interval, and stops polling as soon as that request resolves (success or error) — it never polls independent of an active send.

**Not provided by this endpoint**: any history of past steps or past runs (FR-011) — it reflects only the current, in-progress state, and only while the cache entry is live.
