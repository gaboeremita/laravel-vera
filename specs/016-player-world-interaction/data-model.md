# Data Model: Player World Interaction

No schema changes. The user's state lives on the world page for the length of a visit and travels to the server with each chat message and idle decision. The layout shapes (floors, zones, objects, spots, activities) are unchanged; see [015 data-model](../015-resident-world-agency/data-model.md).

## Player state (client, per visit)

| Field | Type | Notes |
|-------|------|-------|
| `footPosition` | `{x, y, z}` | Standing foot position; on the pool floor while swimming. While holding a spot, the spot's approach point. |
| `movement` | `walking` \| `running` \| `swimming` | Derived each frame from Shift and water depth. |
| `posture` | `standing` \| `sitting` \| `lying` \| `reclining` \| `swimming` | `swimming` whenever `movement` is `swimming` and no spot is held. |
| `spotId` | string \| null | Spot held for a resting activity, or during a standing activity's progress ring. |
| `activityId` | string \| null | Activity being done at that spot or in the zone. |
| `zoneId`, `floorId` | string \| null | From the location tracker ([research R1](research.md)). |

State transitions:

```text
standing ──E/1–9 resting activity──▶ settling (0.8 s glide) ──▶ on spot (sitting | lying | reclining)
on spot ──WASD/Space──▶ getting up (0.8 s glide) ──▶ standing at approach point
standing ──1–9 standing/zone activity──▶ in progress (3 s ring) ──fills──▶ standing (action line)
in progress ──WASD──▶ standing (cancelled, no line)
walking/running ──depth > 1.1 m──▶ swimming ──depth < 0.9 m──▶ walking
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

## Occupancy map (client, existing)

`occupiedSpots: Map<spotId, residentId | 'user'>`, shared by residents and the user. Sent to the server as the list of spot ids taken by anyone other than the resident being asked ([contracts/world-requests.md](contracts/world-requests.md)).

## User state on the wire

`userState` on chat messages and idle decisions. Validation and the resulting prompt text are in [contracts/world-requests.md](contracts/world-requests.md).
