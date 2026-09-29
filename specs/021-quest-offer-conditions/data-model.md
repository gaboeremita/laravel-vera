# Data Model: Offer Conditions for Quests

## New table

### `item_transfers`

One item's movement between inventories, written by `TransferInventory` for every item it moves (research R4). It mirrors `credit_transactions`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `world_session_id` | FK `world_sessions`, cascade | |
| `from_inventory_id` | FK `inventories`, nullable, null on delete | null when the item is created |
| `to_inventory_id` | FK `inventories`, nullable, null on delete | null when the item is destroyed |
| `item_id` | FK `items`, cascade | |
| `quantity` | unsigned int | |
| `reason` | string | the same reason `TransferInventory` gives credits, e.g. `gift` |
| `by_creator` | boolean | |
| timestamps | | |

Index (`world_session_id`, `from_inventory_id`, `to_inventory_id`, `item_id`) for the `gaveTo` sum.

Model `ItemTransfer` with a factory. No existing table changes.

## Quest definition additions (`quests.definition`, JSON)

`start` gains two optional keys, allowed only when `start.mode` is `offer` (research R1):

| Key | Type | Notes |
|---|---|---|
| `offerWhen` | condition | any leaf except `beat` and `question`, including the moment leaves |
| `offerQuestion` | string | non-empty when present |

New condition leaves (research R2):

| Leaf | Value | Allowed in | Validation |
|---|---|---|---|
| `feeling` | `{ resident: int, kind: "romance"\|"trust"\|"liking", atLeast?: number, atMost?: number }` | everywhere | resident of the world; at least one bound; each bound from -10 to 10; `atLeast` ≤ `atMost` when both |
| `questState` | `{ quest: string, state: "offered"\|"active"\|"declined"\|"abandoned" }` | everywhere | a quest key of the world, this quest included |
| `declinedTimes` | `{ quest: string, atLeast: int }` | everywhere | a quest key of the world, this quest included; `atLeast` ≥ 1 |
| `gaveTo` | `{ resident: int, item: int, atLeast: int }` | everywhere | resident and item of the world; `atLeast` ≥ 1 |
| `spentWith` | `{ resident: int, atLeast: int }` | everywhere | resident of the world; `atLeast` ≥ 1 |
| `messagesWith` | `{ resident: int, atLeast: int }` | `start.offerWhen` only | resident of the world; `atLeast` ≥ 1 |
| `giverIn` | `{ region: int, zone: string }` | `start.offerWhen` only | a zone of that region, as `enterZone` |
| `othersInTheZone` | `{ resident: int }` or `{ nobody: true }` | `start.offerWhen` only | exactly one of the two; resident of the world |

## Run state

No change. The offerQuestion's answers are read from `quest_events` (research R6), and offer history from `quest_offers` (R5). Latched event leaves of offerWhen use the scope `offer` in `state.seen`.

## `quest_events` payload additions

| Event type | New payload keys |
|---|---|
| `beat_finished`, `started`, `completed`, `failed` | `because`: ?string, the trigger's sentence (research R9); `started` keeps its existing `cause` (session, condition, offer or creator) |
| `offered` | `lookups`: `[{ part, value, asks }]` in the order made this turn; `offerWhenHeld`: ?bool (null without offerWhen); `unmetParts`: string[] |
| `question_signalled`, `question_judged` for the offerQuestion | `question: ":offer"`, `textHash`: string |

## What conditions read (`QuestSessionState`)

Loaded once per trigger, in addition to what it reads today:

- feelings by resident id (`resident_feelings` of the session);
- item quantities the player gave, by resident id and item id (`item_transfers`);
- credits the player paid, by resident id (`credit_transactions`);
- per quest key: whether the latest run has a pending offer, the status of its latest answered offer, and the count of declined or withdrawn offers (`quest_offers`).

`OfferMoment` (not stored): the giver, the region, the request's positions and the session; it answers `messagesWith`, `giverIn` and `othersInTheZone` (research R7).

## State of a quest for `questState`

| State | Holds when |
|---|---|
| `offered` | latest run Available with a pending offer |
| `active` | latest run Active |
| `declined` | latest run Available, no pending offer, and its latest answered offer is declined or withdrawn |
| `abandoned` | latest run Abandoned |
