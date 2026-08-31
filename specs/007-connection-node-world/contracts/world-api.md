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
  "assistant_context_prompt": "You are in the Connection Node, a 3D space where the user can move freely.",
  "npc_context_prompt": "You are a resident of the Connection Node. Keep your replies grounded in the room.",
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

Deletes the world and its resident placements according to the confirmed lifecycle policy. It never deletes underlying assistant records unless the user separately deletes an NPC assistant through the approved flow.

## Companion Residents

### `PUT /api/worlds/{world}/residents/{assistant}`

Adds or updates a normal assistant resident.

```json
{
  "position": { "x": 1.2, "y": 0, "z": -2.8 },
  "rotation": { "x": 0, "y": 2.3, "z": 0 },
  "behavior": "stationary",
  "behavior_settings": null
}
```

The server rejects assistants not owned by the user, assistants without a 3D avatar, and `WorldNpc` assistants on this companion endpoint.

### `DELETE /api/worlds/{world}/residents/{assistant}`

Removes the placement only; it preserves the assistant and prior conversations.

## World NPC Residents

### `POST /api/worlds/{world}/npcs`

Creates an `AssistantKind::WorldNpc` and its initial `WorldResident` placement. The request reuses the existing assistant editor fields for name, base prompt, archive, VRM, animations, poses, and avatar settings, plus the placement fields above.

### `PATCH /api/worlds/{world}/npcs/{assistant}`

Updates the NPC using the same assistant configuration capabilities, then updates its resident placement if included.

### `DELETE /api/worlds/{world}/npcs/{assistant}`

Removes the NPC's placement. Deleting the underlying assistant record requires an explicit separately-confirmed deletion action consistent with the existing assistant editor.

## In-World Conversation Context

Existing conversation create/send operations accept an optional `world_id` only when invoked by `WorldPage`.

```json
{
  "content": "What is this place?",
  "world_id": 42
}
```

When present, the server must verify that the requested assistant is a resident of world `42` and that world belongs to the authenticated user. It appends `assistant_context_prompt` for companion assistants or `npc_context_prompt` for `WorldNpc` assistants to that request's composed prompt. Invalid or unauthorized world context returns the application's standard validation/authorization response. When absent, existing conversation behavior is unchanged.
