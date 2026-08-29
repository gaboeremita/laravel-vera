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
| `source_description` | string | The raw setting description this pair was generated from — kept as context/debugging metadata, not compared against future requests. |
| `generated_at` | ISO 8601 timestamp | When this pair finished generating. |

**Lifecycle**:
- Written once generation completes successfully (by `GenerateAvatarBackground`).
- Read by `AvatarBackgroundController@show` (polling).
- Expires via cache TTL. Absence (never generated, or expired) is a valid state — the UI shows the bundled default background (see below) until the next automatic or manual generation completes (FR-012a).
- Cleared and replaced, never accumulated: exactly one entry exists per `conversation_id` at a time. Never keyed by `assistant_id` alone or shared across conversations (clarification, 2026-08-28; FR-015). Every trigger unconditionally generates a fresh pair — there is no reuse/dedup check against the previous value.

## Generation status (cache-backed, ephemeral)

Mirrors the existing `agent-progress:{conversation_id}` key used by `AgentProgressController`.

**Cache key**: `avatar-background-progress:{conversation_id}`

**Value shape**: a short human-readable status string (e.g. `"Generating scene..."`) while `GenerateAvatarBackground` is running for that conversation; the key is absent when idle. Presence/absence is what `in_progress` reflects in the polling response (see [contracts/](contracts/)).

## Default Background (static bundled asset, not cache-backed)

Per research.md §9, a fixed pair of default images (floor + surroundings) ships with the frontend build and is imported directly in `resources/js/components/VrmAvatar.jsx`, the same way it already imports its static avatar-load-error fallback. Used whenever the Avatar Background cache entry above is absent (`background: null` from the polling endpoint) and no generation has completed yet. This is not part of the cache-backed value shape — it has no `conversation_id`, no TTL, and involves no backend storage or database access at all.

## Relationships to existing entities

- **Conversation** (existing, `app/Models/Conversation.php`) — the sole ownership boundary for both cache keys above. One conversation has at most one active Avatar Background at a time.
- **Assistant** (existing) — read-only, to determine eligibility (`portrait_type === AssistantPortraitType::Avatar3D`, FR-014) and to source persona/context for prompt-shaping (FR-006).
- **Archive** / **ArchiveEntry** (existing) — read-only, consulted via `PromptDirector::withRetrieval()` during prompt-shaping (FR-007). No new relationship or foreign key; the existing `Assistant::archive()` link is reused exactly as `ImageGenPromptEnhancer` already uses it.

No fields are added to any existing model, and no migration is required for this feature.
