# Contract: World Agency API

All routes are behind the existing `auth:sanctum` group. `{world}` and `{session}` are resolved through the requesting user's own `WorldUser` membership and that membership's sessions, as in `WorldSessionController`. `{resident}` must belong to `{world}`. Anything else returns 404.

## Positions payload

Used by several endpoints below.

```json
{ "user": { "x": 1.2, "y": 0, "z": -3.4 },
  "residents": { "12": { "x": -8.1, "y": 0, "z": 10.2 } } }
```

## Changed: upload or replace an environment

`POST /worlds` and `PUT /worlds/{world}` (existing). When an environment file is included, markers are parsed and stored. The response gains:

```json
{ "layoutWarnings": [{ "node": "Zone.Studio", "reason": "zone has no entry child" }] }
```

## Changed: world resource

`GET /worlds/{world}` returns `layout`, the parsed floors, zones (with world-space outlines and entry points), objects and spots with their activities, for the map, name tags, route targets and spot placement.

## Changed: send a message

`POST /assistants/{assistant}/conversations/{id}/messages` (existing) accepts optional `worldSessionId`, `positions` and `residentPosture` (`standing`, `sitting`, `lying` or `reclining`; defaults to `standing`). When present, the prompt gains the world state (FR-008) and the resident's recent activity (FR-022). The response gains:

```json
{ "action": { "verb": "go_to", "target": "bar", "activity": null } }
```

`action` is the action tool she called this turn, or `null` when she called none. Tool calls naming things that do not exist are returned to her as errors within the turn and never reach the page.

## New: request an idle decision

`POST /worlds/{world}/sessions/{session}/residents/{resident}/decisions`

Request: `positions`, `residentPosture`, `occupiedSpots` (spot ids currently taken by other residents), and optionally `previous`, the outcome of her last step (`{ "activityId": 55, "outcome": "failed", "reason": "spot taken" }`).

Response `201`:

```json
{ "line": "(I want to forget about today for a while) *walks to the bar to get a drink*",
  "action": { "verb": "use", "target": "bar-counter-3", "activity": "drink" },
  "pose": null,
  "activityId": 56,
  "messageId": 912 }
```

- The line is stored as an assistant message in the resident's conversation for this session, creating that conversation when none exists.
- `429` when the previous decision for this resident was under 8 seconds ago (FR-029).
- `422` when the resident's behavior is not `autonomous`.

## New: report an outcome

`PATCH /worlds/{world}/sessions/{session}/residents/{resident}/activities/{activity}`

Request: `{ "outcome": "completed" | "failed" | "interrupted", "reason": "…" }`. Records the outcome and finish time. Returns `204`.

Actions the user requested in conversation are recorded too: the page creates the activity with `POST …/residents/{resident}/activities` (`{ "verb", "target", "activity", "reason": null }`), then reports its outcome through the PATCH route.

## New: save resident state

`PUT /worlds/{world}/sessions/{session}/residents/{resident}/state`

Request: `{ "position": {x,y,z}, "rotation": {y}, "spotId": "pool-lounger-2-seat" | null, "activityId": "recline" | null, "posture": "reclining", "exitPosition": {x,y,z} | null }`. Stored so she is where the user left her on return (FR-032). The page saves on the same 10-second cadence as the user's position and on exit. `GET /worlds/{world}/sessions` (existing) includes `residentStates` for each session, keyed by resident id.
