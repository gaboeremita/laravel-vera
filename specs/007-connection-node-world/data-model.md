# Data Model: Configurable Worlds

## Existing Entity Reused: Assistant

`Assistant` remains the source of truth for a character's name, base prompt, archive access, VRM asset, poses, animations, provider setup, ownership, and conversations.

### Enum Extension: `AssistantKind`

| Value | Meaning |
|---|---|
| `Assistant` | Existing configurable companion assistant |
| `WorldNpc` | An assistant configured as an NPC through a world's editor |

An NPC follows all normal assistant ownership and data rules; its kind controls selection eligibility and the applicable world context prompt.

## New Entity: World

| Field | Type | Rules |
|---|---|---|
| `id` | primary key | Internal identifier |
| `user_id` | foreign key | Required; owner relationship and authorization scope |
| `name` | string | Required, user-visible |
| `slug` | string | Required, unique per user, generated or validated for readable routes |
| `description` | text | Required user-visible setting and editor context |
| `environment_disk` | string | Required existing storage disk convention |
| `environment_path` | string | Required runtime GLB environment path |
| `environment_original_name` | string | Required asset traceability |
| `assistant_context_prompt` | text | Required editable context appended only to companion resident chats in this world |
| `npc_context_prompt` | text | Required editable context appended only to NPC resident chats in this world |
| `settings` | JSON nullable | Spawn, collision map, loader/presentation options, and forward-compatible world settings |
| timestamps | timestamps | Standard Laravel timestamps |

Relationships:

- `belongsTo(User::class)`
- `hasMany(WorldResident::class)`

Domain behavior:

- `contextPromptFor(AssistantKind $kind): ?string` returns `assistant_context_prompt` for companion assistants and `npc_context_prompt` for `WorldNpc`.
- Prompt selection is centralized here; callers do not rely on string values or duplicated conditionals.

Constraints:

- `(user_id, slug)` unique.
- A user may create multiple worlds. No canonical world key exists.
- The environment file is owned by the world and is permanently removed when the world is deleted; assistant and NPC-owned assets are never removed by this lifecycle.

## New Entity: WorldResident

| Field | Type | Rules |
|---|---|---|
| `id` | primary key | Internal identifier |
| `world_id` | foreign key | Cascades on world deletion |
| `assistant_id` | foreign key | References an owned existing assistant; restrict deletion or use existing assistant lifecycle convention |
| `position` | JSON | Required x/y/z world coordinates |
| `rotation` | JSON nullable | Optional x/y/z orientation |
| `behavior` | enum | `WorldResidentBehavior` cast; defaults to `Stationary` |
| `behavior_settings` | JSON nullable | Roam bounds, idle timing, and future tuning |
| `opening_message` | text nullable | Overrides the assistant's own opening message for this placement only |
| `custom_prompt` | text nullable | Added on top of the world's kind-level context prompt, for this placement only |
| timestamps | timestamps | Standard Laravel timestamps |

Relationships:

- `belongsTo(World::class)`
- `belongsTo(Assistant::class)`

Constraints:

- `(world_id, assistant_id)` unique.
- The assistant must be owned by the world owner and have a valid 3D avatar.
- Both normal assistants and `WorldNpc` assistants may be added to a world and may be attached to multiple worlds.

## New Enum: WorldResidentBehavior

| Value | Runtime meaning |
|---|---|
| `Stationary` | Idle/pose behavior at its configured position |
| `Roam` | Random bounded movement defined by `behavior_settings` |

## Chat Context Boundary

`worldId` is ephemeral request context supplied by the in-world chat UI. It is not persisted on `conversations`, because the same assistant or NPC can participate in normal conversations and may reside in multiple worlds.

The server validates:

1. The world belongs to the authenticated user.
2. The assistant is an active resident of that world.
3. The assistant kind maps to the correct world context field.

It then appends the selected prompt to the existing assistant prompt for that one provider request. Archive retrieval, assistant base prompts, and ordinary conversations remain unchanged.
