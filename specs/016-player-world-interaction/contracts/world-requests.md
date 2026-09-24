# Contract: User State in World Requests

Two existing endpoints gain the user's state, and chat messages gain the occupancy list decisions already send.

## Endpoints

- `POST /api/assistants/{assistant}/conversations/{id}/messages` (world chat)
- `POST /api/worlds/{world}/sessions/{session}/residents/{resident}/decisions` (idle decisions)

## New fields

| Field | Rules | Meaning |
|-------|-------|---------|
| `userState` | nullable, array | The user's current state. Omitted means standing with no activity. |
| `userState.posture` | required with `userState`; one of `standing`, `sitting`, `lying`, `reclining`, `swimming` | |
| `userState.spotId` | nullable string, max 100; must be a spot in the world's layout | The spot the user holds. |
| `userState.activityId` | nullable string, max 100; must be an activity of that spot, or, without `spotId`, of a zone in the layout | What the user is doing. |
| `occupiedSpots` (messages only; decisions already accept it) | nullable array of strings, max 100 each | Spot ids taken by anyone other than this resident, including the user. |

An unknown `spotId`, or an `activityId` that does not belong to the spot or to any zone, returns `422` with the field named. Fields are ignored for worlds without zones.

## Effect on the resident's prompt

The `the user is` entry of the world state gains the user's activity, resolved from the layout:

| userState | Entry ends with |
|-----------|-----------------|
| standing, no activity | *(unchanged)* |
| sitting / lying / reclining on a spot | `, sitting on the Bar counter` · `, lying on the Bed` · `, reclining on the Pool loungers` |
| standing activity in progress | `, doing "Make coffee" at the Back counter` |
| zone activity in progress | `, doing "Look out at the city"` |
| swimming | `, swimming` |

Example: `the user is: in Pool terrace, about 3 m away, reclining on the Pool loungers`.

`occupiedSpots` on a chat message is passed to her world tools, so `use` and `plan` reject a spot the user or another resident holds with `spot taken`, as idle decisions already do.

## Action lines

While a conversation is open, the world page sends the user's activity changes as ordinary user messages whose whole content is one or more action lines in asterisks, for example `*sits down at the bar counter*` or `*gets up from the bar counter* *makes coffee at the back counter*`. No new field marks them; she reads them as she reads any roleplay action.
