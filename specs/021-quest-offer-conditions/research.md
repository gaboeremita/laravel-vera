# Research: Offer Conditions for Quests

## R1. Where offerWhen and offerQuestion live

**Decision**: Both sit in the quest definition's `start` object, next to `mode` and `giver`, and are allowed only when `mode` is `offer`:

```json
"start": {
  "mode": "offer",
  "giver": 12,
  "offerWhen": { "all": [
    { "feeling": { "resident": 12, "kind": "trust", "atLeast": 3 } },
    { "giverIn": { "region": 4, "zone": "docks" } },
    { "othersInTheZone": { "nobody": true } }
  ] },
  "offerQuestion": "Has the user shown they can keep a secret?"
}
```

**Rationale**: Both only mean something for a quest that starts by offer, and `start` already groups how a quest starts. Paths such as `start.offerWhen.all.0.feeling.atLeast` fit the editor's errors-by-path.

## R2. The new condition leaves

**Decision**: Keys and shapes, in the definition's camelCase with database ids for world data and keys for quests:

| Leaf | Shape | Holds when |
|---|---|---|
| `feeling` | `{ resident, kind: romance\|trust\|liking, atLeast?, atMost? }` | the resident's feeling toward the player is within the bounds given; at least one bound, each from -10 to 10 |
| `questState` | `{ quest, state: offered\|active\|declined\|abandoned }` | the named quest's latest run is in that state (R5) |
| `declinedTimes` | `{ quest, atLeast }` | the player declined the named quest's offers at least that many times in the session, withdrawn offers included (R5) |
| `gaveTo` | `{ resident, item, atLeast }` | the item handovers from the player to the resident in the session add up to at least that quantity (R4) |
| `spentWith` | `{ resident, atLeast }` | the credits the player paid the resident in the session add up to at least that amount (R4) |
| `messagesWith` | `{ resident, atLeast }` | the player has sent the resident at least that many messages in the session (R7) |
| `giverIn` | `{ region, zone }` | the giver's zone, or a zone containing it, is that zone (R7) |
| `othersInTheZone` | `{ resident }` or `{ nobody: true }` | the named resident is in the giver's zone, or no resident other than the giver is (R7) |

`questState` and `declinedTimes` may name the quest they belong to. The last three read the moment of the player's message and are allowed only under `start.offerWhen`.

**Rationale**: Each shape mirrors an existing leaf (`has`, `credits`, `enterZone`), so the evaluator, the reference finder and the condition builder extend the way they already work. `othersInTheZone` takes an object in both forms so it stays one key with one value, as every leaf is.

## R3. How the giver decides

**Decision**: The game never evaluates offerWhen to allow or refuse an offer. In the player's conversation with the giver:

- `BuildQuestsPrompt` lists each quest the giver can offer (under the existing rules: Available run, requirements met, not already pending) with its offerWhen written in plain words (R8), its offerQuestion and where that stands (R6), and tells the giver to check the parts with `check_offer_condition` before offering. It tells them they may hint in character that they have something in mind, at their own discretion, and must never name or describe the quest, its conditions, or the values they looked up.
- `check_offer_condition` (new) takes a quest and one part of its offerWhen, both as enums of what this giver can check, and returns the value read now and what the quest asks for, e.g. `{ "part": "your trust toward the user", "value": "2", "asks": "at least 3" }`. The model can call it as many times as it needs within the turn; the agent loop already runs several tool rounds.
- `offer_quest` stays as it is: it refuses only for the existing reasons. It is given the turn's `check_offer_condition` instance, and records the lookups of the turn, whether offerWhen held at that moment, and the parts that didn't, on the `offered` event (R9).

**Rationale**: This is the clarified behaviour: the giver alone decides, informed by tools. Returning the value and the ask, without a verdict, leaves the comparison to the giver while keeping it a trivial one. Evaluating offerWhen once for the log gives the author a record of whether the giver judged well.

**Alternatives considered**: One tool returning every part of a quest at once. Rejected because a part-by-part lookup lets the giver check what matters to them, and each lookup is recorded as its own step.

## R4. Records of what the player handed over

**Decision**: Credits already leave a `credit_transactions` row per movement with both inventories and `by_creator`, so `spentWith` sums `amount` where `from_inventory_id` is the player's inventory and `to_inventory_id` is the named resident's. Items get the same kind of ledger: a new `item_transfers` table, one row per item per movement, written by `TransferInventory` next to `recordCredits`. `gaveTo` sums `quantity` from the player's inventory to the named resident's for the item.

**Rationale**: `TransferInventory` is the only way items and credits move, so one write there covers gifts, trades, handover requests, rewards and creator tools. Mirroring `credit_transactions` keeps the two ledgers alike. Counting only player-to-resident rows means what comes back never subtracts, and rewards (resident to player) never count.

## R5. Offer history for questState and declinedTimes

**Decision**: Both read `quest_offers` and the latest run, loaded once into `QuestSessionState`:

- `offered`: the latest run has a pending offer.
- `active`, `abandoned`: the latest run's status.
- `declined`: the latest run is Available, and its latest answered offer was declined or withdrawn.
- `declinedTimes`: the count of the quest's offers in the session, across runs, whose status is declined or withdrawn.

**Rationale**: `quest_offers` already keeps every offer with its status, and `WithdrawQuestOffers` already marks the unanswered ones withdrawn when a conversation ends, so counting withdrawn as declined (clarified) needs no new data.

## R6. The offerQuestion

**Decision**: It reuses the judged-question path with the reserved question id `:offer`, which the id pattern for beat questions can never produce:

- `SignalQuestionTool` also offers, to the giver only, the offerQuestion of each Available run they could offer while it isn't met and no offer is pending.
- `Quest::question(':offer')` returns `{ id: ':offer', text: start.offerQuestion }`, so `JudgeQuestion` and `JudgeQuestionAction` run unchanged. `JudgeQuestion` records the answer with a hash of the question's text, and doesn't write run state for `:offer`.
- Whether it is met is read from the log: a `question_judged` event for `:offer` with `met: true` and the current text's hash, on any run of the quest in the session. The prompt tells the giver it is met, or gives the latest answer's reason when it isn't yet.

**Rationale**: The judge, the one-check-at-a-time rule, the citations and the OOC and creator-command stripping come for free. Reading from the append-only log keeps a yes for the rest of the session across runs, and the text hash makes a changed question start over (spec edge case).

## R7. The moment of the conversation

**Decision**: An `OfferMoment` value carries what only the current turn knows: the giver, the region, the positions the client sent with the message, and the session. It answers:

- **messagesWith**: the count of `role = user` messages in the session's conversations between the player and the named resident's assistant. The player's message is stored before the turn runs, so it counts. Every message counts, OOC and creator commands included (clarified).
- **giverIn**: `ResolveWorldState::locate()` on the giver's position; the zone matches if it is anywhere in the giver's zone chain.
- **othersInTheZone**: the giver's innermost zone; another resident is in it when that zone is in their own zone chain. Residents without a position in the request are elsewhere.

`QuestConditions::holds()` takes an optional `OfferMoment`; the three moment leaves are false without one.

**Rationale**: Resident positions exist only in the request (the client simulates movement), exactly as the world toolbox and fact tools read them today. A zone chain match lets "the docks" include a pier inside it.

## R8. Conditions in plain words

**Decision**: A new `DescribeCondition` action turns a condition tree or one leaf into plain English with names from the world ("your trust toward the user is at least 3", "Mara is in the Docks", "nobody else is in your zone"). It is written from the giver's point of view when given the giver. It is used by the prompt (offerWhen), by `check_offer_condition` (the enum labels and the `part` field), and by the `offered` event (the parts that didn't hold).

**Rationale**: Three real callers need the same wording, and giving the model the same phrasing in the prompt and in the tool's enum makes the lookup obvious to it.

## R9. Triggers and causes

**Decision**:

- `ResidentFeelingsChanged` (new, leaves `feeling`), dispatched by `ResidentFeeling::adjust()`, the one place feelings change (the adjust tool in every mode and quest rewards).
- `PlayerInventoryChanged` also lists `gaveTo` and `spentWith`.
- `QuestStateChanged` (new, leaves `questState` and `declinedTimes`), dispatched when an offer is made, accepted, declined or withdrawn, and when a run starts or ends.
- `QuestTrigger` gets `cause(): ?string`, a sentence in plain words; `QuestTriggerEvent` returns null, and the three triggers above return one ("Mara's trust is now 3", "The user gave Mara 2 bread", "The user declined The Lost Ledger"). `AdvanceQuests` adds it to the `beat_finished`, `started`, `completed` and `failed` payloads.
- `AdvanceQuests` also latches event leaves of `start.offerWhen` for Available runs of offered quests, under the scope `offer`, so `enterRegion` and the like hold there once they happen.

**Rationale**: Every condition part reacts through the same listener as today (FR-012). Putting the sentence on the trigger, where the names are at hand, gives the event log readable causes without the log having to reconstruct them (FR-023).

## R10. The editor

**Decision**:

- `questConditionTypes.js` gains the eight leaves with a label, a one-line description, and `offerOnly` for the three moment leaves.
- `ConditionBuilder` gets fields for each (R2), with number inputs clamped to their ranges, "This quest" in the quest pickers, and the description shown under each row. It takes a base `path` and `errorsAt`, and each row shows its own errors under it.
- `QuestEditor` shows "Offer when" (a `ConditionBuilder` allowing every leaf except `beat` and `question`) and "Offer question" when the quest starts by offer, with a line saying the giver checks these and decides alone.
- `ValidateQuestDefinition` checks the new leaves, their ranges and references, refuses the moment leaves outside `start.offerWhen`, and refuses `offerWhen` and `offerQuestion` on quests that don't start by offer.
- `FindQuestReferences` counts residents and items named by the new leaves, and gains a `quests` kind; `QuestController::destroy` refuses to delete a quest another quest's `questState` or `declinedTimes` names.

**Rationale**: The builder already filters types per place through `context.types` and already has region, zone, resident and item pickers, so the new leaves slot in. Errors by row use the paths the validator already returns.

## R11. The event log

**Decision**: `QuestEventLog.jsx` shows a payload's `cause` as a sentence, and for an `offered` event lists each lookup ("your trust toward the user: 2, asks at least 3") and, when `offerWhenHeld` is false, "Offered while these didn't hold:" with the parts. Those keys leave the generic key-value summary.

**Rationale**: FR-022 and FR-023 ask for plain words; the rest of the log keeps its current look.
