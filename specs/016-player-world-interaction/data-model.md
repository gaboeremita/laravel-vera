# Data Model: Player World Interaction

No schema changes. The user's state lives on the world page for the length of a visit and travels to the server with each chat message and idle decision. The layout shapes (floors, zones, objects, spots, activities) are unchanged; see [015 data-model](../015-resident-world-agency/data-model.md).

## Player state (client, per visit)

| Field | Type | Notes |
|-------|------|-------|
| `footPosition` | `{x, y, z}` | Standing foot position; on the pool floor while swimming. While holding a spot, the spot's approach point. |
| `movement` | `walking` \| `running` \| `crouching` \| `swimming` | Derived each frame from Shift, the crouch toggle and water depth, while on the ground. |
| `airborne` | `{ velocityY }` \| null | Set by a jump or by walking off a ledge; cleared on landing. |
| `posture` | `standing` \| `crouching` \| `sitting` \| `lying` \| `reclining` \| `swimming` | `swimming` whenever `movement` is `swimming` and no spot is held; `crouching` while the crouch toggle is on. |
| `spotId` | string \| null | Spot held for a resting activity, or during a standing activity's progress ring. |
| `activityId` | string \| null | Activity being done at that spot or in the zone. |
| `zoneId`, `floorId` | string \| null | From the location tracker ([research R1](research.md)). |

State transitions:

```text
standing ──card: Enter on a resting activity──▶ settling (0.8 s glide) ──▶ on spot (sitting | lying | reclining)
on spot ──WASD/Space──▶ getting up (0.8 s glide) ──▶ standing at approach point
standing ──card: Enter on a standing/zone activity──▶ in progress (3 s ring) ──fills──▶ standing (action line)
in progress ──WASD──▶ standing (cancelled, no line)
walking/running ──depth > 1.1 m──▶ swimming ──depth < 0.9 m──▶ walking
walking ──Q──▶ crouching ──Q──▶ walking
crouching ──Shift──▶ running
crouching ──depth > 1.1 m──▶ swimming
walking/running/crouching ──Space──▶ airborne ──lands──▶ walking/running
walking/running ──off a ledge ≤ 2 m──▶ airborne
```

Rules:
- A spot is claimed in `occupiedSpots` with holder `'user'` on entering *settling* or *in progress*, and released on reaching *standing*.
- An activity can only start on a spot whose holder is absent or `'user'`.
- Leaving the world releases the spot; the saved position is the approach point ([research R3](research.md)).

## Zone crossing (client)

| Field | Type | Notes |
|-------|------|-------|
| `zoneId` | string | Innermost zone at the crossing. |
| `leftAt` | map zoneId → timestamp | When each zone was last left; a crossing into a zone left under 2 s ago shows no card (FR-004). |

## Card selection (client)

| Field | Type | Notes |
|-------|------|-------|
| `card` | `{ kind: 'object' \| 'zone', id }` \| null | The open card; at most one. |
| `highlightedIndex` | integer | Row highlighted in the card's activity list; 0 on open, wraps at both ends. |

## Occupancy map (client, existing)

`occupiedSpots: Map<spotId, residentId | 'user'>`, shared by residents and the user. Sent to the server as the list of spot ids taken by anyone other than the resident being asked ([contracts/world-requests.md](contracts/world-requests.md)).

## Observation (server, existing table)

What a resident saw the user do, in her own voice (`*I see the user sit down at the bar counter*`), stored as a `messages` row with `role = assistant` in her conversation for the world session (`conversations.world_session_id`). Every observation is its own row. No schema change.

## User state on the wire

`userState` on chat messages and idle decisions. Validation and the resulting prompt text are in [contracts/world-requests.md](contracts/world-requests.md).
