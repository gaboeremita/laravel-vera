# Contract: User State in World Requests

Two existing endpoints gain the user's state, chat messages gain the occupancy list decisions already send, and a new endpoint stores what a resident saw the user do.

## Endpoints

- `POST /api/assistants/{assistant}/conversations/{id}/messages` (world chat)
- `POST /api/worlds/{world}/sessions/{session}/residents/{resident}/decisions` (idle decisions)

## New fields

| Field | Rules | Meaning |
|-------|-------|---------|
| `userState` | nullable, array | The user's current state. Omitted means standing with no activity. |
| `userState.posture` | required with `userState`; one of `standing`, `crouching`, `sitting`, `lying`, `reclining`, `swimming` | |
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
| crouching | `, crouching` |

Example: `the user is: in Pool terrace, about 3 m away, reclining on the Pool loungers`.

`occupiedSpots` on a chat message is passed to her world tools, so `use` and `plan` reject a spot the user or another resident holds with `spot taken`, as idle decisions already do.

## Action lines

An action line is one or more roleplay actions in asterisks, for example `*sits down at the bar counter*` or `*gets up from the bar counter* *makes coffee at the back counter*`. The world page delivers each one to the residents who can see the user:

- **The resident of the open conversation** gets it as an ordinary user message through the messages endpoint above, and replies.
- **Every other onlooker** gets an observation through the endpoint below, silently: the same activity in her own voice, for example `*I see the user sit down at the bar counter*` or `*I see the user get up from the bar counter*`.

No field marks either kind; residents read them as they read any roleplay action.

## New endpoint: observations

`POST /api/worlds/{world}/sessions/{session}/residents/{resident}/observations`

Route name `worlds.sessions.residents.observations.store`, beside the existing decisions route and under the same middleware.

| Field | Rules |
|-------|-------|
| `line` | required string, max 500; her observation in the first person, between asterisks |

- `{world}` resolves through the requester's worlds, `{session}` through their `WorldUser` membership, and `{resident}` must belong to `{world}`; otherwise `404`.
- Finds or creates the resident's conversation for the session, the same way idle decisions do, and stores the line as an `assistant` message, her own. Every call adds a new message.
- No model call. Responds `201` with `{ "messageId": <id> }`.
- Worlds without zones accept it too; the line still becomes part of her history.

## Recent conversation in idle decisions

The decisions endpoint adds a `recent conversation` section to her prompt: the last 6 messages of her session conversation, oldest first, each as `you: …` or `the user: …`, cut to 300 characters. Her observations reach her next decision this way. The section is omitted when the conversation is empty.
