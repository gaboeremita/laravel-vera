# Contract: HTTP API, broadcasts and LLM tools

All routes are under `/api`, behind the existing `auth:sanctum` group. Configuration routes use scoped route binding and the world policy, so another user's world returns 403, as the item and fact routes do; play routes resolve the session through the user's world membership, so another user's session, run or offer returns 404. Keys are camelCase.

Shapes used below:
- `Quest`: `{ id, key, title, campaignId, definition, sessionCount, problems: string[] }`; `sessionCount` counts sessions with a run of it; `problems` is the current result of the check (research R14).
- `Campaign`: `{ id, key, title, definition, questIds: int[] }`.
- `RunView`: `{ id, questId, key, title, description, campaignId, run, status, beats: [{ id, text, finished, current }], ending: ?Ending, endingStatus, startedAt, endedAt }`. `beats` never contains a hidden beat that isn't finished.
- `Ending`: `{ tier, title, epilogue, scores: [{ dimension, score, reason }] }`.
- `Notice`: `{ type: "beatFinished" | "questStarted" | "questEnded" | "questAvailable", questTitle, text }`.

## Configuration

| Method | Path | Name | Notes |
|---|---|---|---|
| GET | /worlds/{world}/quests | worlds.quests.index | `Quest[]` |
| POST | /worlds/{world}/quests | worlds.quests.store | `{ key, title, campaignId, definition }`; 422 with `errors` keyed by definition path; 201 `{ quest: Quest, warnings: string[] }` |
| PATCH | /worlds/{world}/quests/{quest} | worlds.quests.update | same fields and responses |
| DELETE | /worlds/{world}/quests/{quest} | worlds.quests.destroy | 204 |
| GET | /worlds/{world}/quest-options | worlds.quest-options | the form's picker data: `{ regions: [{ id, name, zones: [{ id, name }], objects: [{ id, name, activities: [{ id, name }] }] }], residents: [{ id, name, toolsUnsupported: bool }], items: [{ id, name }], facts: [{ id, topic, holderName }], quests: [{ key, title, flags: string[], tiers: string[] }], campaigns: [{ key, title, tiers: string[] }] }` |
| GET | /worlds/{world}/campaigns | worlds.campaigns.index | `Campaign[]` |
| POST | /worlds/{world}/campaigns | worlds.campaigns.store | `{ key, title, definition, questIds }`; 422 when a quest is already in another campaign |
| PATCH | /worlds/{world}/campaigns/{campaign} | worlds.campaigns.update | same fields |
| DELETE | /worlds/{world}/campaigns/{campaign} | worlds.campaigns.destroy | 204 |

The destroy endpoints for items, facts, regions and world NPCs, and removing a resident from a region, return 422 `{ message, quests: [{ id, title }] }` while a quest names them (research R15).

## Play

| Method | Path | Name | Notes |
|---|---|---|---|
| GET | /worlds/{world}/sessions/{session}/quests | worlds.sessions.quests.index | `{ runs: RunView[], campaigns: [{ id, title, questIds, ending: ?Ending, endingStatus }] }` |
| POST | /worlds/{world}/sessions/{session}/quest-runs/{run}/abandon | worlds.sessions.quest-runs.abandon | 422 unless active; `RunView` |
| POST | /worlds/{world}/sessions/{session}/quest-runs/{run}/assess | worlds.sessions.quest-runs.assess | queues the assessment again; 422 unless `endingStatus` is `failed`; 202 |
| POST | /worlds/{world}/sessions/{session}/quest-offers/{offer}/answer | worlds.sessions.quest-offers.answer | `{ accept: bool }`; `{ status, line, run: RunView }`; 409 when no longer pending |
| POST | /worlds/{world}/sessions/{session}/conversations/{conversation}/quest-offers/withdraw | worlds.sessions.conversations.quest-offers.withdraw | 204; called where handover requests are cancelled |
| GET | /worlds/{world}/sessions/{session}/quest-events | worlds.sessions.quest-events.index | `[{ id, questTitle, run, beat, type, payload, byCreator, createdAt }]`, oldest first, for the sessions page |

### Changed responses

- `POST /assistants/{assistant}/conversations/{id}/messages` (`conversations.sendMessage`) adds `questOffer: { id, questTitle, description, giver, beats: [{ text }] }` when the character offered a quest in this reply.
- `POST /worlds/{world}/sessions/{session}/resume` also withdraws pending offers and syncs the session's quests (research R4).

## Broadcasts

Private channel `world-session.{sessionId}`, authorised when the session belongs to the user's membership of its world.

| Event | Payload |
|---|---|
| `quests.updated` | `{ runs: RunView[], notices: Notice[] }`, only the runs that changed |
| `quests.ending` | `{ runId?, campaignId?, endingStatus, ending: ?Ending }` |

## LLM tools

| Tool | Offered | Arguments | Result |
|---|---|---|---|
| `grant_flag` | player conversation; named on `grants` of a current beat | `{ flag: enum, reason: string }` | `{ status: "granted" }` |
| `signal_question` | player conversation; named on a question of a current beat | `{ question: enum of texts, reason: string }` | `{ status: "signalled", note }` |
| `offer_quest` | player conversation; giver of an available `offer` quest | `{ quest: enum of titles }` | `{ status: "offered", note }` |
| `judgement` | forced, in `JudgeQuestionAction` only | `{ met: bool, messageIds: int[], reason: string }` | read by the action |
| `record_ending` | forced, in `AssessEnding` only | research R10 | read by the action |
| `start_quest`, `reset_quest`, `assess_quest` | creator turns | `{ quest: enum }` | `{ status }` |
| `end_quest` | creator turns | `{ quest: enum, outcome: "completed" \| "failed" }` | `{ status }` |
| `set_beat` | creator turns | `{ quest: enum, beat: string, finished: bool }` | `{ status, undone?: string[] }` |
| `set_quest_flag` | creator turns | `{ quest: enum, flag: string, on: bool }` | `{ status }` |
| `edit_quest` | creator turns | `{ quest: enum, definition: string }` (JSON text) | `{ status }` or an error listing every problem |
