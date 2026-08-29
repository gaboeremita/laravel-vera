# Data Model: 3D Avatar Scene Backgrounds

Per [research.md](research.md) decision 3, this feature introduces **no new database tables**. The one new conceptual entity from the spec's Key Entities section is represented as a cache value plus disk files, not an Eloquent model.

## Avatar Background (cache-backed, not a database entity)

The scene currently cached for one conversation.

**Cache key**: `avatar-background:{conversation_id}`

**Value shape**:

| Field | Type | Description |
|---|---|---|
| `conversation_id` | int | The owning conversation. MUST be the id of a `Conversation` already resolved through the ownership-checked `$assistantUser->conversations()->findOrFail($id)` path (Constitution Principle IV) — never written from a bare route parameter. |
| `floor_url` | string | Public URL of the floor image (`Storage::disk('public')->url(...)`), from `avatar-backgrounds/{conversation_id}/`. |
| `surroundings_url` | string | Public URL of the surrounding-environment image, same directory. |
| `source_description` | string | The raw setting description this pair was generated from — used to decide whether a new request "closely matches" the current background (FR-015) instead of regenerating. |
| `generated_at` | ISO 8601 timestamp | When this pair finished generating. |

**Lifecycle**:
- Written once generation completes successfully (by `GenerateAvatarBackground`).
- Read by `AvatarBackgroundController@show` (polling) and by the trigger paths (`ConversationController::store`/`sendMessage`, `AvatarBackgroundTool`) to decide whether FR-015's reuse applies.
- Expires via cache TTL. Absence (never generated, or expired) is a valid state — the UI shows no background/a default until the next automatic or manual generation completes (FR-012a).
- Cleared and replaced, never accumulated: exactly one entry exists per `conversation_id` at a time. Never keyed by `assistant_id` alone or shared across conversations (clarification, 2026-08-28; FR-015).

## Generation status (cache-backed, ephemeral)

Mirrors the existing `agent-progress:{conversation_id}` key used by `AgentProgressController`.

**Cache key**: `avatar-background-progress:{conversation_id}`

**Value shape**: a short human-readable status string (e.g. `"Generating scene..."`) while `GenerateAvatarBackground` is running for that conversation; the key is absent when idle. Presence/absence is what `in_progress` reflects in the polling response (see [contracts/](contracts/)).

## Relationships to existing entities

- **Conversation** (existing, `app/Models/Conversation.php`) — the sole ownership boundary for both cache keys above. One conversation has at most one active Avatar Background at a time.
- **Assistant** (existing) — read-only, to determine eligibility (`portrait_type === AssistantPortraitType::Avatar3D`, FR-014) and to source persona/context for prompt-shaping (FR-006).
- **Archive** / **ArchiveEntry** (existing) — read-only, consulted via `PromptDirector::withRetrieval()` during prompt-shaping (FR-007). No new relationship or foreign key; the existing `Assistant::archive()` link is reused exactly as `ImageGenPromptEnhancer` already uses it.

No fields are added to any existing model, and no migration is required for this feature.
