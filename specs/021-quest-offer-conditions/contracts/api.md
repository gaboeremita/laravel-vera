# Contract: HTTP API and LLM tools

Changes to the contract of [feature 020](../../020-world-quests/contracts/api.md). No new routes.

## Configuration

| Method | Path | Change |
|---|---|---|
| POST, PATCH | /worlds/{world}/quests, /worlds/{world}/quests/{quest} | `definition.start` accepts `offerWhen` and `offerQuestion` and the new leaves ([data-model.md](../data-model.md)). 422 `errors` keyed by path as today, with these new messages among others: "Only a quest that starts by offer can have an offer condition.", "This condition only works in Offer when.", "Choose a quest of this world.", "Must be from -10 to 10.", "Give at least one bound.", "Must be a whole number of at least 1." |
| DELETE | /worlds/{world}/quests/{quest} | 422 `{ message, quests: [{ id, title }] }` while another quest's `questState` or `declinedTimes` names it |
| GET | /worlds/{world}/quest-options | unchanged; regions already carry their zones, and quests their keys and titles |

The destroy endpoints for items and world NPCs, and removing a resident from a region, also return 422 while a new leaf names them, as for every existing reference.

## Play

| Method | Path | Change |
|---|---|---|
| GET | /worlds/{world}/sessions/{session}/quests | `runs` leaves out Available runs; the `quests.updated` broadcast leaves out Available runs and `questAvailable` notices. A quest's title and description reach the player's page only once it is offered (FR-024) |
| GET | /worlds/{world}/sessions/{session}/quest-events | payloads carry the keys in [data-model.md](../data-model.md): `because`; on `offered`, `lookups`, `offerWhenHeld` and `unmetParts` |
| POST | /worlds/{world}/sessions/{session}/quest-offers/{offer}/answer | unchanged shape; a decline dispatches `QuestStateChanged` |
| POST | /worlds/{world}/sessions/{session}/conversations/{conversation}/quest-offers/withdraw | unchanged shape; each withdrawn offer counts as a decline for `questState` and `declinedTimes` |

Nothing reaches the player's page before an offer: the offer card carries the quest's details, and the run joins the list once it is accepted.

## LLM tools

### `check_offer_condition` (new)

In the player's conversation with a giver, while at least one quest they can offer has an `offerWhen`.

```json
{
  "type": "object",
  "properties": {
    "quest": { "type": "string", "enum": ["<titles of the quests this giver can offer that have offerWhen>"] },
    "part": { "type": "string", "enum": ["<each leaf of those offerWhen trees, in plain words>"] }
  },
  "required": ["quest", "part"]
}
```

Returns `{ "part": "your trust toward the user", "value": "2", "asks": "at least 3" }`. A part that isn't a leaf of that quest's offerWhen is refused with the parts it has. Values:

| Leaf | `value` |
|---|---|
| `feeling` | the feeling, one decimal |
| `questState` | offered, active, declined, abandoned, or another word for the run's status (available, completed, failed), or "not available" |
| `declinedTimes`, `gaveTo`, `spentWith`, `messagesWith` | the count or sum |
| `giverIn` | the zone the giver is in, or "no zone"; when the zone the quest names no longer exists in the region's layout, "{zone} no longer exists in {region}" |
| `othersInTheZone` | the names of the other residents in the giver's zone, or "nobody" |
| `has`, `credits` | what the player holds |
| `knows`, `acknowledged`, `flag` | "yes" or "no" |
| `enterRegion`, `enterZone`, `talkTo`, `use`, `residentDid` | "has happened" or "hasn't happened" |

### `offer_quest` (changed)

Same parameters and refusals. Its `offered` event records the turn's lookups, `offerWhenHeld` and `unmetParts`. It never refuses because offerWhen doesn't hold or the offerQuestion isn't met.

### `signal_question` (changed)

Also lists, for the giver only, the offerQuestion of each quest they can offer while it isn't met and no offer of it is pending. Signalling it starts the existing judged check with the question id `:offer`.

## Prompt (`quests` section)

For each quest the giver can offer, after its title and description:

- with `offerWhen`: "Offer it only once this holds: {offerWhen in plain words}. Check each part with check_offer_condition before you offer."
- with `offerQuestion`: "Also wait until you are sure of this: {question}. When you believe the user has shown it, call signal_question." followed by "It has been confirmed." or "Not confirmed yet: {latest reason}." when there is an answer.
- once, when any listed quest has either: "Until you offer, you may hint in character that you have something in mind, if you judge it fits. Never name or describe the task, its conditions, or what you checked."
