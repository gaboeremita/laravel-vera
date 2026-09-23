# Contract: Environment Markers

Markers are glTF nodes in the environment GLB whose `extras` contain a `vera` object. Blender writes these from custom properties on empties. Nodes may be nested; positions and facings are taken from each node's world transform (the composed translation, rotation and scale of the node and its ancestors). Marker nodes should have no mesh; if they do, the mesh is ignored for markers but still renders and collides like any other mesh.

All ids are lowercase slugs (`[a-z0-9-]+`), unique within their type across the file.

## Floor

```json
{ "vera": { "type": "floor", "id": "ground", "name": "Ground floor", "minY": -2, "maxY": 4.4 } }
```

| Field | Required | Meaning |
|-------|----------|---------|
| `id`, `name` | yes | Identity and display name. |
| `minY`, `maxY` | yes | World-space height range. A point belongs to the floor whose range contains its height; ranges must not overlap. |

A file with no floor markers has one implicit floor covering all heights.

## Zone

```json
{ "vera": { "type": "zone", "id": "music-studio", "name": "Music studio", "floor": "ground",
  "description": "The Creator's personal studio. Keyboards everywhere; Hammond and Rhodes on the back wall.",
  "parent": null, "private": false,
  "activities": [{ "id": "listen", "name": "Listen to music", "pose": "listen" }] } }
```

| Field | Required | Meaning |
|-------|----------|---------|
| `id`, `name`, `description` | yes | Identity and the text given to residents. |
| `floor` | when floors exist | Id of the floor the zone belongs to. |
| `parent` | no | Id of the enclosing zone. |
| `private` | no | When `true`, residents only enter when the user asks (FR-005). |
| `activities` | no | Zone-level activities (see Activity). |
| `outline` | no | Array of `[x, z]` points in the node's local space. When absent, the zone is the node's local box from −1 to 1 on x and z, which matches a Blender cube empty of size 1 scaled to the room. |
| `minY`, `maxY` | no | Local height range; defaults to −1 to 1 of the node's local box. |

Every zone needs exactly one child node with `{ "vera": { "type": "entry" } }`: the point residents walk to for `go_to <zone-id>` (FR-006).

## Object

```json
{ "vera": { "type": "object", "id": "pool-lounger-2", "name": "Pool lounger",
  "description": "A white in-water lounger on the sun ledge of the infinity pool." } }
```

The object's zone is whichever zone contains the node's position (FR-007). Its spots are child nodes.

## Spot

```json
{ "vera": { "type": "spot", "id": "pool-lounger-2-seat",
  "activities": [{ "id": "recline", "name": "Recline", "posture": "reclining" }] } }
```

- The node's world position is where the resident's root is placed while performing the activity.
- The node's local +Z axis, projected onto the ground plane, is the direction she faces.
- A spot must be a child of an object.

## Activity

| Field | Required | Meaning |
|-------|----------|---------|
| `id`, `name` | yes | Identity and wording used in prompts and action lines. |
| `posture` | no | `sitting`, `lying` or `reclining`. She plays the posture's get-in motion on arrival, holds its loop until she leaves, and plays its get-out motion when she does. Omitted means she stays standing. |
| `pose` | no | Name of a pose from her library, played once in her posture after arriving. Missing poses fall back to her default stance (FR-019). |

## Validation

A marker is skipped, and a warning naming its node and the reason is returned, when:

- a required field is missing, or an id is malformed or duplicated;
- a zone references an unknown floor or parent, has no entry child, or has an outline with fewer than three points;
- a spot is not inside an object, or has no activities;
- floor ranges overlap.

Valid markers are imported even when others are skipped. Worlds whose file has no markers get an empty layout, and behave as they do today (FR-010).
