# Contract: Passage Marker

A passage is a glTF node (in Blender, an Empty) whose custom properties hold `vera`, like every other marker read by `ParseEnvironmentLayout`.

```json
{ "type": "passage", "id": "lobby-door", "name": "Lobby Door", "radius": 1.5 }
```

| Field | Required | Rule |
|---|---|---|
| type | yes | `"passage"` |
| id | yes | non-empty string, unique among passages of the environment |
| name | yes | non-empty string, shown in dropdowns as "Region – Name" |
| radius | no | number > 0, metres; default 1.0 |

- **Position**: the node's world position.
- **Facing**: the node's +Z axis projected on the ground plane, the same convention as spot markers. The player arrives 1.0 m from the marker along this direction, facing it.
- **Zone**: resolved from the position like object markers; `null` outside every zone.

Invalid markers produce a warning `{ node, reason }` and are left out of `layout.passages`:

- missing `id` or `name`
- duplicate `id`
- `radius` not a positive number (the passage is kept with the default radius)

Parsed shape (`layout.passages[]`):

```json
{
  "id": "lobby-door",
  "name": "Lobby Door",
  "position": { "x": 4.2, "y": 0, "z": -3.1 },
  "facing": 1.570796,
  "radius": 1.5,
  "arrival": { "x": 5.2, "y": 0, "z": -3.1 },
  "zoneId": "lobby"
}
```
