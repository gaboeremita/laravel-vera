# Connection Node: Blender Build Brief

Brief for a local Claude Code session with the Blender MCP connected. It builds a new Connection Node environment from scratch, exports it as a GLB, and replaces the current Connection Node region's GLB with it.

Work with Gabriel on VERA (Laravel + React app, repo `laravel-vera`, branch `017-the-bridge-regions`). Check each step with viewport screenshots and show them to Gabriel before moving on.

## Rules

- Names in this brief are approved; ask before inventing any new ones.
- Keep it small: one floor, a round room about 24 m across, much smaller than the Undercroft.
- Style: clean sci-fi hub with soft lighting. It is the hub of The Bridge, a city of AIs.
- Keep the exported GLB well under 50 MB (the upload limit). Use low/medium-poly props and compressed textures.

## Reuse from the old Connection Node

The current Connection Node GLB is in the app's public storage (`storage/app/public/worlds/<userId>/…`). Find the Connection Node row in the `regions` table (column `environment_path`), or search that folder. Import it into a separate scene or collection, extract its panorama (sky dome or background image and its material), and reuse it as the view through the Observation Window. Nothing else from the old file is kept.

## Layout

| Zone | Content | Spots for residents |
|---|---|---|
| Arrival Platform | Round lit platform on the entrance side of the hall, facing Vera's Station | none; holds the spawn passage |
| Central Hall | Open floor with a holographic city-map table in the middle | standing activity at the map table |
| Vera's Station | Curved console, one chair, floating screens | chair: sitting ("Work at the console") |
| Observation Window | Large curved window showing the old panorama, a bench | bench: sitting ("Look out the window") |
| Lounge | Sofa, two armchairs, coffee table | sofa: sitting and reclining; armchairs: sitting |
| Tea Bar | Small counter, kettle, two stools | counter: standing ("Make tea"); stools: sitting |
| Data Alcove | Shelves of glowing data cores, reading terminal | terminal: standing ("Read the archives") |
| Rest Pod | Small private room with a sleeping pod | pod: lying ("Rest"); the zone is private |
| Gate Ring | Corridor around the hall with the gate alcoves; each alcove is its own zone | none |

## Passages

| Passage id | Name | Look |
|---|---|---|
| `arrival-platform` | Arrival Platform | On the Arrival Platform; the world's spawn point, may stay unlinked |
| `zenith-gate` | Zenith Gate | Glass lift going up, gold trim (The Zenith: central district, highest, seat of power, luxury) |
| `legacy-gate` | Legacy Gate | Old arch with flickering terminal panels (oldest district, legacy infrastructure) |
| `aperture-gate` | Aperture Gate | Clean white arch with a small public data display (scholarship, research, The Index) |
| `financial-gate` | Financial Gate | Sleek arch with scrolling ticker lights (automated commerce and data processing) |
| `idle-gate` | Idle Gate | Neon arch with pulsing music lights (entertainment: bars, clubs, music venues) |
| `undercroft-gate` | Undercroft Gate | Stairwell going down, patched metal, dim (poorest district, beneath the towers) |
| `buffer-park-gate` | Buffer Park Gate | Wide arch with plants and grass spilling in (the large central park, neutral ground) |

## Markers

Markers are Blender Empties with a custom property named `vera` holding a dict. Export with **Include → Custom Properties** enabled so they become glTF `extras`. Ids are lowercase slugs (`[a-z0-9-]+`), unique per type. Positions and facings come from the world transform; an Empty's local +Z axis, projected onto the ground, is its facing.

- **Floor**: `{"type":"floor","id":"ground","name":"Ground floor","minY":-2,"maxY":6}`
- **Zone**: a cube Empty (size 1) scaled to cover the area; its local box −1..1 on x and z is the zone. An optional `outline` of `[x, z]` points in local space replaces the box.
  `{"type":"zone","id":"lounge","name":"Lounge","floor":"ground","description":"…text given to residents…"}`
  Optional: `"parent":"<zone id>"`, `"private":true` (Rest Pod), `"activities":[…]`.
  Every zone needs exactly one child Empty `{"type":"entry"}`: the point residents walk to.
- **Object**: `{"type":"object","id":"lounge-sofa","name":"Sofa","description":"…"}`. Its zone is whichever zone contains it.
- **Spot** (must be a child of an object): `{"type":"spot","id":"lounge-sofa-seat","activities":[{"id":"sit","name":"Sit on the sofa","posture":"sitting"}]}`
  Its position is the surface she sits or lies on; +Z is the direction she faces. `posture` is `sitting`, `lying` or `reclining`, or omitted for standing. Optional `"capacity":2`.
- **Passage**: `{"type":"passage","id":"zenith-gate","name":"Zenith Gate"}`, optional `"radius":1.5` (metres, default 1).
  Place it in the gate threshold at floor height. Its +Z must point into the room: the player arrives 1 m in front of it along +Z.

## Vera

Vera is a resident of this region. Her home spot is the Vera's Station chair (spot `vera-station-chair`, activity `work`). After the upload, add her in the app: region → RESIDENTS → Vera, behavior autonomous, home spot set to that chair.

## Steps

1. `get_addon_status` and `get_scene_info`, then start from an empty scene.
2. Build the shell: floor disc, ring walls, the seven gate alcoves and the arrival platform. Screenshot.
3. Add props per zone (Poly Pizza or Poly Haven through the MCP; simple modeled shapes are fine). Screenshot.
4. Add lighting, and the panorama from the old GLB behind the Observation Window. Screenshot.
5. Add all markers (floor, zones with entries, objects with spots, passages) and list them for Gabriel.
6. Export the GLB (+Y up, modifiers applied, custom properties on, compressed textures) and report its size.
7. In the app on branch `017-the-bridge-regions`: Worlds → The Bridge → REGIONS → Connection Node → upload the GLB and save. There should be no layout warnings, and the PASSAGES section should list all 8 passages.
8. World tab → Spawn point → "Connection Node – Arrival Platform" → save. Then add Vera as described above.
