# Contract: HTTP API

All routes are under `/api`, behind the existing `auth:sanctum` group. A world is reachable only through `$request->user()->worlds()`; a region, resident or session outside that world returns 404.

## Worlds

| Method | Path | Name | Notes |
|---|---|---|---|
| GET | /worlds | worlds.index | cards: `id, name, slug, description, cardImageUrl, regionCount, hasSpawn` |
| POST | /worlds | worlds.store | `name, slug, description, assistantContextPrompt, npcContextPrompt`; no environment |
| GET | /worlds/{world} | worlds.show | world fields + `spawnRegionId, spawnPassageId, hasSpawn`, `regions[]` (`id, name, passages[], warnings`), `residents[]` (existing resident shape + `regionId`) |
| PATCH | /worlds/{world} | worlds.update | world fields + `spawnRegionId, spawnPassageId` (both or neither; 422 if the passage is not in that region of this world) |
| DELETE | /worlds/{world} | worlds.destroy | deletes regions, environments, residents, sessions |
| POST/DELETE | /worlds/{world}/card-image, /portrait-image | worlds.image.* | as today |

`regions[].warnings`: `unlinkedPassages` (count) and `noPassages` (bool), used for the ⚠ indicator.

## Regions

| Method | Path | Name | Notes |
|---|---|---|---|
| POST | /worlds/{world}/regions | worlds.regions.store | today's world create payload (incl. `environment`, `settings`) |
| GET | /worlds/{world}/regions/{region} | worlds.regions.show | today's world resource shape: `environmentUrl, layout, trackUrl, settings, cardImageUrl, portraitImageUrl, prompts`, plus `links[]` |
| PATCH | /worlds/{world}/regions/{region} | worlds.regions.update | today's world update payload; returns `layoutWarnings` and `removedLinks[]` |
| DELETE | /worlds/{world}/regions/{region} | worlds.regions.destroy | clears the world spawn if it was here |
| POST/DELETE | /worlds/{world}/regions/{region}/card-image, /portrait-image | worlds.regions.image.* | moved from world |
| POST/DELETE | /worlds/{world}/regions/{region}/track | worlds.regions.track.* | moved from world |

`links[]`: `{ passageId, targetRegionId, targetRegionName, targetPassageId, targetPassageName }`.

## Passage links

| Method | Path | Name | Body / result |
|---|---|---|---|
| PUT | /worlds/{world}/regions/{region}/passages/{passage}/link | worlds.regions.passages.link.update | `{ targetRegionId, targetPassageId }`; 422 if either passage is missing, both are the same passage, or the target region is in another world. Replaces existing links of both passages. Returns both regions' `links[]`. |
| DELETE | /worlds/{world}/regions/{region}/passages/{passage}/link | worlds.regions.passages.link.destroy | removes both directions; 204 |

## Residents

| Method | Path | Name | Notes |
|---|---|---|---|
| PUT | /worlds/{world}/regions/{region}/residents/{assistant} | worlds.regions.residents.upsert | today's placement payload; 409 `{ regionId, regionName }` if the assistant lives in another region of this world |
| DELETE | /worlds/{world}/regions/{region}/residents/{assistant} | worlds.regions.residents.destroy | as today |
| POST | /worlds/{world}/regions/{region}/residents/{assistant}/move | worlds.regions.residents.move | moves the resident here with default placement; deletes their session state rows; 404 if not a resident of this world |

## Sessions

| Method | Path | Name | Change |
|---|---|---|---|
| GET | /worlds/{world}/sessions | worlds.sessions.index | each session adds `regionId`; `residentStates` entries add `regionId` |
| POST | /worlds/{world}/sessions | worlds.sessions.store | 422 `{ message: "Choose a spawn passage first." }` without a valid spawn; otherwise `regionId` and `position` from the spawn arrival |
| PUT | /worlds/{world}/sessions/{session}/position | worlds.sessions.position.update | position within the session's current region, unchanged shape |
| POST | /worlds/{world}/sessions/{session}/travel | worlds.sessions.travel | see below |
| POST | /worlds/{world}/sessions/{session}/resume | worlds.sessions.resume | called when entering a session whose `regionId` is null; moves it to the spawn arrival and returns `{ regionId, position }`; 422 without a valid spawn |

### POST /worlds/{world}/sessions/{session}/travel

Request:

```json
{ "regionId": 3, "passageId": "lobby-door", "followerIds": [12, 15] }
```

- `regionId` must equal the session's current region.
- `followerIds` are `world_residents.id` currently in that region for this session.

Response 200:

```json
{
  "regionId": 5,
  "position": { "x": 5.2, "y": 0, "z": -3.1 },
  "facing": 1.570796,
  "followers": { "12": { "position": { "x": 5.9, "y": 0, "z": -2.6 } } }
}
```

Errors: 422 when the passage has no link or a follower is not in the region.

## Session-scoped resident endpoints

Paths unchanged (`/worlds/{world}/sessions/{session}/residents/{resident}/...`, conversations, decisions, observations, activities, state). Each resolves the resident's current region (state row `region_id`, else the resident's `region_id`) and uses that region's layout. 422 when the resident is not in the session's current region.

## Chat (`ConversationController`)

`worldId` now identifies the container. A new `regionId` (required with `worldId`) identifies the region the conversation happens in; it must belong to the world and equal the session's current region when `worldSessionId` is sent.
