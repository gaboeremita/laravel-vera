# Contract: HTTP API

All routes are under `/api`, behind the existing `auth:sanctum` group. A world is reachable only through `$request->user()->worlds()`; a resident, fact, session or conversation outside it returns 404. Keys are camelCase.

Shapes used below:
- `Fact`: `{ id, topic, content, disclosure, relayResidentIds: int[], usage }`; `usage` counts sessions where the player knows it.
- `KnownFact`: `{ factId, topic, summary, sourceName, learnedAt }`.

## Configuration

### Facts

| Method | Path | Name | Notes |
|---|---|---|---|
| GET | /worlds/{world}/residents/{resident}/facts | worlds.residents.facts.index | `{ facts: Fact[], toolsUnsupported: ?string }`; `toolsUnsupported` is the reason when the resident's model no longer supports tools |
| POST | /worlds/{world}/residents/{resident}/facts | worlds.residents.facts.store | `{ topic, content, disclosure, relayResidentIds }`; 422 when the resident's model can't call tools (FR-002), on a duplicate topic, or a relay resident outside the world or equal to the holder |
| PATCH | /worlds/{world}/residents/{resident}/facts/{fact} | worlds.residents.facts.update | same fields |
| DELETE | /worlds/{world}/residents/{resident}/facts/{fact} | worlds.residents.facts.destroy | 204 |

`GET /worlds/{world}` also returns `reviewReveals`, and `PATCH /worlds/{world}` accepts it. The item and activity terms endpoints accept and return `revealsFactId`; 422 when the fact is of another world.

### Creator password

| Method | Path | Name | Notes |
|---|---|---|---|
| GET | /creator-password | creator-password.show | `{ isSet: bool }` |
| PUT | /creator-password | creator-password.update | `{ password: ?string }`; `null` clears it; 204; never returns the password |

## Play

| Method | Path | Name | Notes |
|---|---|---|---|
| GET | /worlds/{world}/sessions/{session}/known-facts | worlds.sessions.known-facts.index | `KnownFact[]`, newest first |
| GET | /worlds/{world}/sessions/{session}/reveal-attempts | worlds.sessions.reveal-attempts.index | `[{ id, factTopic, holderName, source, reason, reviewed, approved, verdict, createdAt }]`, oldest first |

### Changed responses

- `POST /assistants/{assistant}/conversations/{id}/messages` (`conversations.sendMessage`) adds:
  - `userContent`: the player's message as stored, with any activation removed;
  - `creatorMode`: `{ active: bool, notice: ?string }`, `notice` set after an activation attempt;
  - `learnedFacts`: `KnownFact[]` learned during this reply (world sessions only).

  After a failed activation with nothing else in the message, the response has `creatorMode` and `userContent` and no reply (`content: null`).
- `GET …/items/{item}/examine` and `POST …/activity-uses` add `learnedFacts`.

## LLM tools

| Tool | Offered | Arguments | Result |
|---|---|---|---|
| `reveal` | player conversation, resident holds a fact, model supports tools; on creator turns lists every fact | `{ fact: enum of topics, reason: string }` | `{ status: "revealed", content, note }` or `{ status: "not_now", note }` |
| `acknowledge` | player conversation, resident relays a fact the player knows | `{ fact: enum of topics }` | `{ status: "acknowledged" }`; error when the player doesn't know it |
| `verdict` | forced, in the review call only | `{ approved: bool, verdict: string }` | read by `ReviewReveal` |
| `set_fact_known` | creator turns | `{ fact: enum "holder: topic", known: bool }` | `{ status }` |
| `grant` | creator turns | `{ holder: enum, credits?: int, items?: [{ item, quantity }] }` | `{ status, note }` |
| `remove` | creator turns | same as `grant` | `{ status, note }`; error when the holder has less |
