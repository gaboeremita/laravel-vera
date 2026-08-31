# API Contract: Configurable Worlds

All endpoints require the current application's normal authenticated API session. All resources are scoped to the current user.

## Worlds

### `GET /api/worlds`

Returns the user's world cards for the Assistants/Worlds area.

### `POST /api/worlds`

Creates a world.

Request body:

```json
{
  "name": "Connection Node",
  "description": "A polished sci-fi communication room.",
  "environment": "uploaded-world-glb",
  "assistantContextPrompt": "You are in the Connection Node, a 3D space where the user can move freely.",
  "npcContextPrompt": "You are a resident of the Connection Node. Keep your replies grounded in the room.",
  "settings": {
    "player_spawn": { "x": 0, "y": 0, "z": 0 },
    "collision_map": ["Collision_Walls", "Collision_Furniture"]
  }
}
```

Returns `201 Created` with the world resource.

### `GET /api/worlds/{world}`

Returns editor and runtime configuration, including resident entries and asset URLs appropriate to the authenticated user.

### `PATCH /api/worlds/{world}`

Updates the editable world metadata, environment, settings, and the two context prompts. Validation never permits raw unowned storage references.

### `DELETE /api/worlds/{world}`

Permanently deletes the world, its world-owned environment asset, and its resident placements. It never deletes underlying assistant or NPC records or their owned assets.

## Companion Residents

### `PUT /api/worlds/{world}/residents/{assistant}`

Adds or updates an existing normal assistant or NPC resident.

```json
{
  "position": { "x": 1.2, "y": 0, "z": -2.8 },
  "rotation": { "x": 0, "y": 2.3, "z": 0 },
  "behavior": "stationary",
  "behaviorSettings": null,
  "openingMessage": "Oh, hey — you found this room?",
  "customPrompt": "You are especially wary of strangers near the archive."
}
```

`openingMessage` and `customPrompt` are optional per-placement overrides: `openingMessage` replaces the assistant's own opening message only for conversations started from this world, and `customPrompt` is appended on top of the world's kind-level context prompt only for this placement. Both are `null` by default, in which case the assistant's own opening message and the world's kind-level context prompt apply unchanged.

The server rejects assistants or NPCs not owned by the user and characters without a usable 3D avatar.

### `DELETE /api/worlds/{world}/residents/{assistant}`

Removes the placement only; it preserves the assistant or NPC and prior conversations.

## NPC Library

### `GET /api/npcs`

Returns the current user's `AssistantKind::WorldNpc` records for the NPC section.

### `POST /api/npcs`

Creates an `AssistantKind::WorldNpc` using the existing assistant configuration subset: name, base prompt, archive, VRM, animations, poses, and avatar settings. It does not create a world placement.

### `GET /api/npcs/{assistant}` and `PATCH /api/npcs/{assistant}`

Returns or updates an owned NPC. These operations never modify world placements.

### `DELETE /api/npcs/{assistant}`

Permanently deletes an owned NPC using the confirmed NPC CRUD action. Its world placements are removed by the configured relationship lifecycle; this operation is not available from a world editor.

## In-World Conversation Context

Existing conversation create/send operations accept an optional `worldId` only when invoked by `WorldPage`.

### `POST /api/assistants/{assistant}/conversations`

```json
{
  "worldId": 42
}
```

When `worldId` is present, the created conversation's opening message is the resident's `openingMessage` override if one is set on that placement, falling back to the assistant's own opening message otherwise. When absent, existing conversation behavior is unchanged (the assistant's own opening message).

### `POST /api/assistants/{assistant}/conversations/{conversation}/messages`

```json
{
  "content": "What is this place?",
  "worldId": 42
}
```

When present, the server must verify that the requested assistant is a resident of world `42` and that world belongs to the authenticated user. It appends `assistantContextPrompt` for companion assistants or `npcContextPrompt` for `WorldNpc` assistants, plus that resident's `customPrompt` when set, to that request's composed prompt. Invalid or unauthorized world context returns the application's standard validation/authorization response. When absent, existing conversation behavior is unchanged.
