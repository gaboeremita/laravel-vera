# API Contract: Creator Menu

All routes are under `/api/worlds/{world}/sessions/{session}/creator` and the `auth:sanctum` group. Every route except `unlock` answers **403** `{"message": "Unlock the Creator menu first."}` while the session isn't unlocked. A session or world of another user answers **404**. An action's `RuntimeException` answers **422** `{"message": "..."}`.

## Unlock

### POST `/unlock` — `worlds.sessions.creator.unlock`

Request: `{"password": "string"}`

- **204**: correct password; `creator_mode_at` set.
- **422** `{"message": "Set a creator password in Settings first."}`: no password set.
- **422** `{"message": "That password is wrong."}`: wrong password.

## Overview

### GET `/` — `worlds.sessions.creator.show`

```json
{
  "regions": [{
    "id": 1, "name": "Undercroft",
    "zones": [{"id": "z1", "name": "Market", "parentId": null, "outline": [[0,0],[10,0],[10,8],[0,8]]}],
    "passages": [{"id": "p1", "name": "North gate"}],
    "objects": [{"id": "o1", "name": "Door", "blocks": true, "passable": false,
                 "activities": [{"id": "a1", "name": "Open", "responses": [{"index": 0, "summary": "The door opens..."}]}]}]
  }],
  "spawn": {"regionId": 1, "passageId": "p1"},
  "residents": [{
    "id": 4, "name": "Rin", "portraitUrl": null, "regionId": 1, "position": {"x":2,"y":0,"z":3}, "zoneName": "Market", "frozen": false,
    "sentiments": {"trust": 2, "liking": 0},
    "recentActivities": [{"verb": "go", "target": "stall", "reason": "...", "outcome": "completed", "at": "..."}]
  }],
  "sentimentNames": ["trust", "liking"],
  "quests": [{"id": 7, "title": "Lost lantern", "status": "active", "beats": [{"id": "b1", "finished": true}], "flags": ["metRin"]}],
  "facts": [{"id": 3, "label": "Rin: the vault code", "known": false}],
  "items": [{"id": 9, "name": "Lantern", "cardImageUrl": null}],
  "credits": 120
}
```

`zoneName` is the zone holding the resident's saved position, or null. `status` is the latest run's status, or `"none"`.

## Travel

### POST `/travel` — `worlds.sessions.creator.travel`

Request: `{"regionId": 2, "zoneId": "z5"}`, `{"regionId": 2, "passageId": "p3"}` or `{"spawn": true}`, plus `"followerIds": [4]`.

**200**: `{"regionId": 2, "position": {"x":0,"y":0,"z":0}, "facing": 0, "landInZone": "z5", "followers": {"4": {"position": {...}}}}`

`landInZone` is the zone id when the page must still land the player with the navigation grid; otherwise null. Jumps within the player's region use the existing `PUT /worlds/{world}/sessions/{session}/position`.

## Residents

| Method | Path | Name | Body | Response |
|--------|------|------|------|----------|
| POST | `/residents/{resident}/summon` | `...creator.residents.summon` | `{"position": {x,y,z}, "facing": 1.2}` | 204 |
| POST | `/residents/{resident}/send` | `...creator.residents.send` | `{"regionId": 2, "zoneId": "z5", "position": {x,y,z}?}` | 200 `{"regionId": 2, "position": {...}}` |
| DELETE | `/residents/{resident}/state` | `...creator.residents.state.destroy` | — | 204 |
| PUT | `/residents/{resident}/frozen` | `...creator.residents.frozen.update` | `{"frozen": true}` | 204 |
| PUT | `/residents/{resident}/sentiments` | `...creator.residents.sentiments.update` | `{"values": {"trust": 5}}` | 200 `{"sentiments": {...}}` |

`send` without `position` uses the zone's centroid at its `minY`.

## Quests

| Method | Path | Body | Response |
|--------|------|------|----------|
| POST | `/quests/{quest}/start` | — | 204 |
| POST | `/quests/{quest}/end` | `{"outcome": "completed"\|"failed"}` | 204 |
| POST | `/quests/{quest}/reset` | — | 204 |
| PUT | `/quests/{quest}/beats/{beat}` | `{"finished": true}` | 200 `{"undone": ["b2"]}` |
| PUT | `/quests/{quest}/flags/{flag}` | `{"on": true}` | 204 |

Route names: `worlds.sessions.creator.quests.start|end|reset`, `.beats.update`, `.flags.update`. Quest run changes also go out through the existing quest broadcast.

## Facts

### PUT `/facts/{fact}` — `worlds.sessions.creator.facts.update`

Request: `{"known": true}`. **200** `{"learnedFact": {...}|null}`, using `KnownFact::toPayload()`.

## Credits and items

A holder is `{"type": "player"}`, `{"type": "resident", "residentId": 4}` or `{"type": "object", "regionId": 1, "objectId": "o1"}`.

| Method | Path | Name | Body | Response |
|--------|------|------|------|----------|
| POST | `/credits` | `...creator.credits.store` | `{"amount": -50}` | 200 `{"inventory": {...}, "changes": {...}}` |
| GET | `/inventories?holder[type]=object&holder[regionId]=1&holder[objectId]=o1` | `...creator.inventories.show` | — | 200 `{"credits": null, "items": [{"itemId": 9, "name": "Lantern", "quantity": 2, "takeable": true}]}` |
| POST | `/inventories/grant` | `...creator.inventories.grant` | `{"holder": {...}, "itemId": 9, "quantity": 3}` | 200 player `{"inventory", "changes"}` |
| POST | `/inventories/move` | `...creator.inventories.move` | `{"from": {...}, "to": {...}, "itemId": 9, "quantity": 1}` | 200 player `{"inventory", "changes"}` |

`inventory` and `changes` match `ActivityUseController::store`, so the page updates the player's inventory with its existing code. Moving more than the source holds answers 422 with `InsufficientInventory`'s message.

## Objects

| Method | Path | Name | Body | Response |
|--------|------|------|------|----------|
| PUT | `/objects/passable` | `...creator.objects.passable.update` | `{"regionId": 1, "objectId": "o1", "passable": true}` | 200 `{"objectState": {"regionId", "objectId", "state"}}` |
| POST | `/activity-uses` | `...creator.activity-uses.store` | `{"regionId": 1, "objectId": "o1", "activityId": "a1", "response": 0}` | 200, same shape as `POST .../activity-uses` |

## Changed existing routes

- `GET /worlds/{world}/sessions`: each session gains `creatorUnlocked` and `frozenResidentIds`.
- `POST /worlds/{world}/sessions/{session}/residents/{resident}/decisions`: **409** `{"message": "This resident is frozen."}` for a frozen resident.
