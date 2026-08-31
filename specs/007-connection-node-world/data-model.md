# Data Model: Connection Node World

## Assistant

Existing entity extended with `kind`, cast to `AssistantKind` and defaulting to `Companion`.

- `Companion` assistants appear in the normal Assistants area.
- `WorldNpc` assistants are created and managed through the Connection Node editor.
- Both kinds use existing `prompt`, `archive_id`, `vrm`, `poses`, `users`, and conversation relations.
- A resident requires `portrait_type = avatar3d` and a usable VRM asset.

## World

One user-owned Connection Node configuration.

| Field | Purpose |
|---|---|
| `user_id` | World owner. |
| `key` | Canonical `connection-node` key. |
| `name`, `description` | Display copy. |
| `environment_path`, `environment_disk`, `environment_original_name` | Approved runtime room asset. |
| `settings` | Ambient audio and visual quality preferences. |

Has many `WorldResident` records.

## WorldResident

Connects an assistant to a world and owns placement behavior.

| Field | Purpose |
|---|---|
| `world_id`, `assistant_id` | Owner and resident identity. |
| `position_x`, `position_y`, `position_z`, `rotation_y` | Persistent location and facing. |
| `behavior` | `Stationary` or `Roam`. |
| `roaming_bounds` | Validated local bounds and waypoints. |
| `interaction_radius` | Proximity-chat distance. |

Rules: one assistant is resident once per world; all records are user-scoped; placements stay inside the approved layout and outside collision or protected areas. Removing a companion resident does not delete the assistant. Deleting an NPC uses the existing assistant cleanup path.
