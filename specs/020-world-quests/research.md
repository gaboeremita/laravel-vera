# Research: Quests for Worlds

## R1. Where quests and campaigns live

**Decision**: A `quests` table per world with a `key` (unique per world, what other quests' requirements name), a `title`, an optional `campaign_id`, and a `definition` JSON column holding everything else (R2). A `campaigns` table per world has the same shape: `key`, `title` and a `definition` holding its description and rubric. A quest belongs to at most one campaign through `quests.campaign_id` (FR-018a).

Per session, a `world_session_quests` row is one run of a quest (R4), `quest_events` is each run's append-only log (R5), `quest_offers` holds a giver's pending offers (R9), and `world_session_campaigns` holds a campaign's ending once it is over (R11).

**Rationale**: The definition is edited as one document in two modes (FR-002), so it's stored as one. Title, key and campaign are columns because lists, pickers, requirements and the one-campaign rule query them.

## R2. The definition

**Decision**: Keys are camelCase. References to world data use database ids, the same ids the form's pickers return; beats use author-chosen string ids unique within the quest; other quests and campaigns are referenced by `key`.

```json
{
  "description": "The miller's mill flooded, and the village blames them.",
  "start": { "mode": "offer", "giver": 12 },
  "requires": [{ "quest": "the-drowned-ledger", "outcome": "completed" }],
  "repeatable": false,
  "beats": [
    {
      "id": "hear-the-miller",
      "text": "Hear the miller's side of the story.",
      "hidden": false,
      "requires": [],
      "when": { "talkTo": 12 },
      "knowledge": [{ "resident": 12, "prose": "You are ashamed and defensive; you want someone to believe you." }],
      "grants": [],
      "questions": []
    },
    {
      "id": "clear-their-name",
      "text": "Convince the elder the flood wasn't the miller's fault.",
      "requires": ["hear-the-miller"],
      "when": { "any": [{ "flag": "elderConvinced" }, { "question": "cleared" }] },
      "grants": [{ "resident": 7, "flag": "elderConvinced" }],
      "questions": [{ "id": "cleared", "text": "Has the player convinced the elder that the flood wasn't the miller's fault?", "residents": [7] }]
    }
  ],
  "complete": null,
  "fail": { "not": { "has": { "item": 31, "atLeast": 1 } } },
  "rubric": {
    "guidance": "Judge how kindly and honestly the player treated the miller.",
    "dimensions": [{ "name": "honesty", "description": "Did the player tell the truth?" }],
    "tiers": ["vindicated", "bittersweet", "scapegoated"]
  }
}
```

- `start.mode` is `auto` (starts with the session), `condition` (`start.when` is a condition) or `offer` (`start.giver` is a resident).
- `requires` entries are `{ "quest": key, "outcome": … }` or `{ "campaign": key, "outcome": … }`. The outcome is `ended`, `completed`, `failed`, `abandoned`, or `tier:<name>`.
- `complete: null` means the quest completes when every beat is finished (spec edge case). `fail: null` means it can't fail by condition.
- `knowledge` is the prose a named resident gets while the beat is current (FR-010). `grants` lists the flags a named resident may grant (FR-011). `questions` are judged questions and the residents who can signal them (FR-012).

**Conditions** are a tree:

| Node | Holds when | Kind |
|---|---|---|
| `{ "all": [C…] }`, `{ "any": [C…] }`, `{ "not": C }` | combinators | — |
| `{ "enterRegion": regionId }` | the player arrived in the region | event, latched |
| `{ "enterZone": { "region": id, "zone": zoneId } }` | the player crossed into the zone | event, latched |
| `{ "talkTo": residentId }` | the player talked with the resident | event, latched |
| `{ "use": { "region": id, "object": objectId, "activity": activityId } }` | the player used the activity and it succeeded | event, latched |
| `{ "residentDid": { "resident": id, "region": id, "object": objectId, "activity": activityId } }` | the resident used the activity | event, latched |
| `{ "has": { "item": id, "atLeast": n } }` | the player holds at least n | state |
| `{ "credits": { "atLeast": n } }` | the player holds at least n credits | state |
| `{ "knows": factId }` | the player knows the fact | state |
| `{ "acknowledged": { "fact": id, "resident": id } }` | the resident learned the fact from the player | state |
| `{ "flag": name }` | a flag of this run is set | state |
| `{ "flag": { "quest": key, "name": name } }` | the latest run of that quest ended with the flag among its resulting flags | state |
| `{ "question": questionId }` | the question was judged met in this run | state |
| `{ "beat": beatId }` | the beat is finished (for `complete` and `fail`) | state |

An **event** leaf latches: once its event happens while the node is being watched (the beat is current, or the quest is active for `complete`/`fail`, or available for `start.when`), the run records it in `state.seen` and it holds from then on. A **state** leaf reads the session as it is when checked. So `all: [enterRegion, has]` holds whether the item came before or after arriving (spec assumption).

**Rationale**: Every condition the spec lists maps to one leaf, and the latch lets combinators mix things that happen with things that are held.

## R3. Progress driven by triggers

**Decision** (clarification Q1): Every place that changes something a condition can read dispatches a Laravel event, and quest listeners react. Nothing polls and nothing re-checks every turn.

| Event (in `app/Events/Quests/`) | Dispatched by | Leaves it can affect |
|---|---|---|
| `PlayerEnteredRegion` | `TravelThroughPassage`, after its transaction; `WorldSessionController::store`, for the spawn region | `enterRegion` |
| `PlayerEnteredZone` | `WorldSessionController::updatePosition`, once per zone in the new position's zone chain (`ResolveWorldState::locate`) that wasn't in the stored position's chain, so entering a room inside a building also enters the building; `WorldSessionController::store`, for the spawn position's zones | `enterZone` |
| `PlayerTalkedTo` | `ConversationController::sendMessage`, after the reply is stored, in world sessions | `talkTo` |
| `PlayerUsedActivity` | `ActivityUseController`, when the outcome is allowed | `use` |
| `ResidentUsedActivity` | a `RecordResidentActivity` action shared by `ResidentActivityController::store` and `ResidentDecisionController::recordDecision`, the two places a resident's activity is recorded, when it names an activity | `residentDid` |
| `QuestStarted` | wherever a run becomes active: `SyncSessionQuests` for `auto` quests, an accepted offer, `start.when`, `start_quest`, `reset_quest`; and `set_beat` when it undoes beats | every leaf of that run's watched conditions |
| `PlayerInventoryChanged` | `TransferInventory`, after commit, when either side is a player inventory | `has`, `credits` |
| `FactLearned` | `LearnFact::handle`, when the fact is newly known | `knows` |
| `FactAcknowledged` | `AcknowledgeTool`, when the row is new | `acknowledged` |
| `QuestFlagChanged` | the grant tool and creator flag tool | `flag` |
| `QuestQuestionJudged` | `JudgeQuestion`, when the answer is yes | `question` |
| `QuestEnded` | `AdvanceQuests` and creator tools | `flag` of other quests, `requires`, campaigns |

Each event implements a small `QuestTrigger` interface (`sessionId()`, `leaf()`, and a `matches(array $leaf)` check). The `AdvanceQuests` listener handles every `QuestTrigger`: it loads the session's available and active runs, and for each one only the conditions being watched that contain a leaf of that kind. `QuestStarted` is the exception: it names one run, and that run's watched conditions are all evaluated, so a first beat about something the player already holds finishes the moment the quest starts.

The client saves the player's position every 10 seconds, which is too slow for zone crossings (SC-003) and misses short visits. `WorldPage` therefore also saves the position immediately when its location tracking reports a new zone (`handleLocationChange`, the same moment `ZoneTitleCard` appears). It latches matching event leaves, then evaluates in this order:

1. `fail`, then `complete`, for active runs (fail wins when both hold, spec edge case);
2. current beats, repeatedly, so a beat whose requirements just finished can finish in the same pass (spec US2 scenario 4);
3. `start.when` for available runs with `start.mode = condition`.

The listener runs synchronously, after the triggering transaction commits (`ShouldHandleEventsAfterCommit`), under a per-session lock (`Cache::lock("quests:{session}")->block(5, …)`) so two triggers can't finish the same beat twice. If the lock can't be taken within 5 seconds, the `LockTimeoutException` is reported and rethrown, so a trigger is never dropped silently (Principle V). Changes are written to the run's `state`, logged as `quest_events`, and broadcast (R12).

A `QuestConditions` class evaluates a tree against a `QuestSessionState` snapshot (the player's inventory, known facts, acknowledgements, the run's state, other quests' latest runs), loaded once per trigger.

**Rationale**: This is the event/listener model the user asked for, and one listener keeps the order of evaluation in one place. Synchronous handling keeps a finished beat within the request that caused it (SC-003); only the model calls are queued (R7, R10).

**Alternatives considered**: One listener per leaf kind, which spreads the fail-before-complete and beat-chaining order over several classes; queued listeners, which add a queue hop to every trigger for work that is a few queries.

## R4. Runs and their lifecycle

**Decision**: `world_session_quests` holds one row per run: `run` (1, 2, …), `status`, `state` (`finishedBeats`, `flags`, `seen`, `questions`), `ending` (the assessment's result), `ending_status`, `started_at`, `ended_at`.

```text
             requirements met                start (auto, condition, accepted offer, creator)
  (no row) ─────────────────────▶ available ─────────────────────────────────────────▶ active
                                      ▲                                                  │
                                      │ repeatable: a new run is created available       │ complete / fail / abandon / creator
                                      └──────────────────── completed / failed / abandoned ◀┘
```

- A `SyncSessionQuests` action creates rows for quests whose requirements hold and that have no open run: `auto` ones start active, the rest are available. It runs when a session is created, when a session is resumed (so quests added to the world reach sessions in progress, US2 scenario 9), and after every `QuestEnded` (so chains unlock).
- A repeatable quest gets a new available run when its latest run ends. An `auto` repeatable quest's later runs are available only, so they start through a condition or an offer and never restart on their own (spec edge case).
- A quest that isn't repeatable never gets a second run; the unique index is (`world_session_id`, `quest_id`, `run`), and `SyncSessionQuests` checks `repeatable` before creating one.
- Requirements read the latest run of the named quest (clarification).

**Rationale**: Keeping `available` as a row means start conditions and offers have something to attach to, and the quest log can show what can be started.

## R5. The event log

**Decision**: `quest_events` rows belong to a run and carry `beat` (nullable), `type` (the `QuestEventType` enum), `payload` (JSON: the trigger, reason, citations, verdict, the definition diff summary…), `by_creator` and `created_at`. The model has no `updated_at`, and its `updating` and `deleting` hooks throw, so the log can't be changed through Eloquent (FR-009). Rows go with their run when a session or quest is deleted.

Types: `started`, `beatFinished`, `beatUndone`, `flagSet`, `flagCleared`, `questionSignalled`, `questionJudged`, `offered`, `offerDeclined`, `offerWithdrawn`, `completed`, `failed`, `abandoned`, `reset`, `definitionEdited`, `endingWritten`, `endingFailed`.

**Rationale**: The log is the evidence the assessment reads (R10) and what the user reads to debug (FR-020).

## R6. Granting flags

**Decision**: A `grant_flag(flag, reason)` tool, offered in the player's conversation to a resident named in `grants` of at least one current beat, with `flag` an enum of exactly those flags. It refuses anything else (US3 scenarios 3–4), sets the flag in the run's state with who granted it and why, logs `flagSet`, and dispatches `QuestFlagChanged`. Decisions and conversations between residents never get the tool (US3 scenario 5).

**Rationale**: Granting is the main path (spec US3), and it's the agent architecture's native move: the resident decides in character through a tool.

## R7. Judged questions

**Decision** (clarification Q1): A `signal_question(question, reason)` tool is offered in the player's conversation to residents named on a question of a current beat; `question` is an enum of those questions' texts. It logs `questionSignalled` and queues a `JudgeQuestion` job for (run, question, conversation), unless one is already queued or running for that run and question (`ShouldBeUnique`, spec edge case). No signal, no check (SC-004).

`JudgeQuestion` calls a `JudgeQuestionAction` on the world's narrator model (`ResolveNarratorModel`) with a forced `judgement` tool returning `{ met: bool, messageIds: int[], reason: string }`. It reads the question, the beat's text, and the conversation's last 30 messages as stored, each prefixed with its id, with OOC spans and creator-mode tags removed (`LlmResponseTagParser::stripOutOfCharacter`, and a new `CreatorModeTags::withoutCommands` beside its existing `withoutActivations`), inside a block described as data to judge. A `met: true` whose `messageIds` are empty or not all in that conversation counts as no (US4 scenario 6). The result is logged as `questionJudged` with the answer, ids and reason; a yes sets `state.questions[id] = true` and dispatches `QuestQuestionJudged`. If the run is no longer active by then, the answer is logged and changes nothing (spec edge case). A failed call is logged as `questionJudged` with `met: false` and the error, and reported (Principle V).

**Rationale**: The resident is already reading the conversation in character; letting them raise the flag and a separate model verify it keeps cost to one call per claim.

## R8. What residents are told

**Decision**: A `BuildQuestsPrompt` action adds a `quests` section to a resident's prompt, built from the session's runs:

| Part | In the player's conversation | Decisions and between residents | Creator turns |
|---|---|---|---|
| `knowledge` prose of current beats naming them | yes | yes | yes |
| flags they may grant, as "You decide in character whether the user has earned these; when they have, call grant_flag with your reason" | yes | no | yes |
| questions they may signal, as "When you believe the user has done this, call signal_question" | yes | no | yes |
| quests they can offer, with title and description | yes | no | yes |
| endings of this session's quests they were involved in: title, tier and epilogue | yes | yes | yes |
| every quest's status, beats and flags | no | no | yes |

It's appended wherever `BuildFactsPrompt` is: `sendMessage`, `ResidentDecisionController` and `GenerateResidentConversationTurn`. Prose states the behaviour directly.

The endings part is how residents remember a quest (FR-016, clarification Q4): it lives in the session's quest data and is added only to prompts in that session, so other sessions, `RecallResidentMemory` and chats outside the world never see it. Involved residents are the giver, residents named anywhere in the definition, and residents who granted a flag or signalled a question in the run.

**Rationale**: `long_term_memory` is summarised per conversation and read by `RecallResidentMemory` across every conversation with the user, which would leak an ending into other sessions.

## R9. Offers

**Decision** (clarification Q3): An `offer_quest(quest)` tool, offered in the player's conversation to the giver of at least one available quest with `start.mode = offer`, `quest` an enum of those titles. It refuses a quest that is active, ended and not repeatable, or already offered in this conversation (spec edge case). It creates a pending `quest_offers` row, logs `offered`, and returns `{ status: "offered", note: "They will answer." }`.

`sendMessage` returns the pending offer as `questOffer` (like `handoverRequest`), and `WorldChat` shows it as a request line in the conversation; opening it shows the offer card (title, description, giver, the first visible beats, accept and decline). Answering posts to the offer's answer endpoint:
- **Accept**: the run becomes active, `started` is logged, and the response returns a line for the player's next message, e.g. `[You accept "The Flooded Mill"]`, so the giver is told (US6 scenario 3).
- **Decline**: `offerDeclined` is logged, the run stays available, and the line is `[You decline "The Flooded Mill" for now]`.

Pending offers are withdrawn (`offerWithdrawn`) where pending handover requests are cancelled: when the chat closes and when a session is resumed (US6 scenario 2a).

**Rationale**: It reuses the handover-request flow players already know, with its own table because the answer starts a quest instead of moving items.

## R10. Ending assessment

**Decision**: When a run completes, fails or is abandoned, `AdvanceQuests` (or the abandon endpoint, or a creator tool) sets the status, `ended_at` and `ending_status = pending`, logs it, dispatches `QuestEnded`, and queues `AssessQuestEnding` for the run.

`AssessQuestEnding` calls `AssessEnding` on the narrator model with a forced `record_ending` tool:

```json
{
  "tier": "one of the author's tiers (enum), omitted when there are none",
  "title": "string",
  "epilogue": "string, 2–5 paragraphs",
  "scores": [{ "dimension": "enum of dimension names", "score": "integer 1–10", "reason": "string" }],
  "resultingFlags": ["string"]
}
```

It reads the definition's description and rubric, how the run ended (and, when both matched, that complete also held), the event log in order with creator-made events marked "done through creator mode, not in the story" (FR-018), the run's flags, and excerpts: for each involved resident, the player's conversation with them in this session, messages from the run's `started_at` to `ended_at`, the last 40 per conversation, OOC and creator tags removed. The result is stored in `ending`, `ending_status = written`, `endingWritten` logged, and the ending broadcast (R12).

The job has 3 tries. After the last failure, `ending_status = failed`, `endingFailed` is logged with the error, and the player's quest log offers to write it again (`POST …/quest-runs/{run}/assess`), as does a creator tool (US5 scenario 9).

**Rationale**: One forced call with all evidence in hand matches the agreed design. Tiers and dimensions become enums so the model can only choose the author's names.

## R11. Campaigns

**Decision**: After a quest's ending is written or fails, a `CheckCampaignEnded` listener checks the quest's campaign: it is over when none of its quests has an active or available run that could still start, counting a repeatable quest once its first run has ended (FR-018b). It then creates a `world_session_campaigns` row (`ending_status = pending`) and queues `AssessCampaignEnding`. That job reuses `AssessEnding` with the campaign's rubric and, as evidence, each quest's latest ending plus its event log summary. A creator reset of a quest in an ended campaign deletes the campaign's row, so it is assessed again once the campaign is over (US7 scenario 7).

Later quests can require `{ "campaign": key, "outcome": … }`, read from that row.

**Rationale**: The campaign ending is the same kind of judgement over a larger body of evidence, so it shares the action.

## R12. Telling the player

**Decision**: Quest changes are broadcast with Reverb on a private channel `world-session.{sessionId}`, authorised when the session belongs to the user's world membership. Events:
- `quests.updated`: the changed runs as the player sees them, plus `notices` (`beatFinished`, `questStarted`, `questEnded`, `questAvailable`);
- `quests.ending`: a run's or campaign's ending, when written or failed.

The player's view of a run never contains a hidden beat until it is finished (clarification Q2), so hidden beats don't reach the browser. `GET …/quests` returns the full log on load, and `useQuests` keeps it current from the channel, the way `useAvatarBackground` uses `conversation.{id}`.

**Rationale**: Judged checks and assessments finish in queued jobs, after any request has returned, so a push channel is the only way to tell the player promptly. Using it for every quest change gives the HUD one source.

## R13. Creator mode

**Decision**: On creator turns in a world session, the character gets:
- `start_quest(quest)`, `end_quest(quest, outcome: completed | failed)`, `reset_quest(quest)`;
- `set_beat(quest, beat, finished: bool)`: undoing a beat also undoes the finished beats that require it;
- `set_quest_flag(quest, flag, on: bool)`;
- `assess_quest(quest)`: runs the ending assessment again;
- `edit_quest(quest, definition)`: the full definition as JSON, checked by `ValidateQuestDefinition` exactly like the configuration; refusals return the same messages (US8 scenario 4).

`quest` is an enum of titles. Each writes its events with `by_creator = true`, and dispatches the same events as play so listeners react alike. `reset_quest` clears the latest run's state, ending and status back to the definition's start and logs `reset`; earlier log rows stay.

**Rationale**: FR-018; one tool per verb keeps each schema small and each refusal clear.

## R14. Checking a definition

**Decision**: A `ValidateQuestDefinition` action, used by the store and update requests and by `edit_quest`, returns every problem with its path (`beats.1.when.any.0.enterZone.zone`):

1. **Shape**: required fields, types, enum values, beat and question ids unique within the quest, `grants`/`questions`/`knowledge` naming residents, tiers and dimension names unique.
2. **References**: regions, zones (from the region's `layout.zones`), objects and activities (from `Region::objectActivities`), residents, items and facts exist in this world; beat ids in `requires` and `beat` leaves exist in this quest; question ids exist; other quests' and campaigns' keys exist in this world.
3. **Beat cycles**: a depth-first search over `requires`, naming the beats in the cycle.
4. **Quest cycles**: the same search over every quest of the world, with this definition in place, through `requires` (quest to quest, and campaign to its quests).

Warnings, which don't block saving: a resident on `grants` or `questions` whose model can't call tools (spec edge case). They're returned with the saved quest.

A region layout re-import can remove a zone, object or activity a quest names; zones and activities exist only in layouts, so this is the only way they disappear. The quests list re-runs the check and returns `problems` per quest so the editor shows them; at play time a missing reference never matches.

**Rationale**: FR-003 and SC-002. One action serves the form, the JSON mode and creator edits.

## R15. Deleting what quests use

**Decision**: A `FindQuestReferences` action lists the quests whose definitions name a given region, resident, item or fact. The destroy endpoints for items, facts and regions, resident removal in the region's resident list, and deleting a world NPC (whose deletion cascades to their placements) return 422 naming them (FR-004). Deleting a whole world still deletes everything. Zones, objects and activities come from layouts and are covered by R14's problems.

Deleting a quest cascades its runs, events and offers, and the editor warns first with the number of sessions that have a run (spec edge case). A quest in a campaign leaves the campaign when deleted; an empty campaign stays until the user deletes it.

**Rationale**: Blocking keeps every definition valid; quietly editing an author's conditions to drop a reference would change what their quest means.

## R16. Editing a quest while sessions play it

**Decision**: Saving a definition runs `ReconcileQuestRuns` on the quest's open runs: beat ids that no longer exist are removed from `finishedBeats` and `seen`, and a `definitionEdited` event records which. Finished beats that still exist stay finished (spec edge case).

## R17. Form mode

**Decision**: The world edit page gets a **Quests** accordion and a **Campaigns** accordion. Each quest opens in an editor with the same Form/JSON toggle as `SchemaEditor`. JSON mode edits the whole definition; switching back to form mode is refused with the parse or shape errors shown under the text area.

```text
┌ QUESTS ─────────────────────────────────────────────────────── [+ NEW QUEST] ┐
│ ▸ The Flooded Mill        campaign: The River      3 beats   ⚠ 1 problem    │
│ ▾ The Drowned Ledger      no campaign              2 beats                   │
│ ┌──────────────────────────────────────────────────── [FORM] [JSON] ───────┐ │
│ │ Title  [The Drowned Ledger          ]   Key  [the-drowned-ledger]         │ │
│ │ Description [ ................................................ ]           │ │
│ │ Starts  (•) with the session  ( ) on a condition  ( ) offered by [Ada ▾]  │ │
│ │ Requires   [+ quest outcome]    ☐ Can be played again                     │ │
│ │ ── BEATS ───────────────────────────────────────────────── [+ BEAT] ──    │ │
│ │ ┌ 1 · find-ledger ─────────────────────────────────── ☐ hidden  [✕] ┐    │ │
│ │ │ Player text [Find the ledger the harbourmaster lost.        ]      │    │ │
│ │ │ After [— none —            ▾]                                      │    │ │
│ │ │ Finishes when                                                      │    │ │
│ │ │  ┌ ALL of ▾ ───────────────────────────────────────────────┐      │    │ │
│ │ │  │ [enter zone ▾]  [Harbour ▾] [Boathouse ▾]          [✕] │      │    │ │
│ │ │  │ [holds item ▾]  [Ledger ▾]  at least [1]           [✕] │      │    │ │
│ │ │  │ [+ condition]  [+ group]                                │      │    │ │
│ │ │  └─────────────────────────────────────────────────────────┘      │    │ │
│ │ │ ▸ What residents know   ▸ Flags residents grant   ▸ Questions      │    │ │
│ │ └────────────────────────────────────────────────────────────────────┘    │ │
│ │ Completes when [every beat is finished ▾]    Fails when [never ▾]        │ │
│ │ ── ENDING ─────────────────────────────────────────────────────────       │ │
│ │ Guidance [ ...... ]   Dimensions [+]   Tiers [vindicated ✕][+]            │ │
│ │                                                  [DELETE]  [SAVE QUEST]   │ │
│ └───────────────────────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────────────┘
```

- The **condition builder** (`ConditionBuilder`) is recursive. A group picks ALL, ANY or NOT and holds rows; each row picks a condition type, then its fields from world data: regions, then that region's zones or objects, then the object's activities; residents; items; facts; this quest's flags, questions and beats; other quests' keys and their flags. Picker data comes from one `GET /worlds/{world}/quest-options` call.
- Save errors from the server are shown on the fields their paths point to, and listed at the top when a path has no field (for example a cycle).
- The list shows each quest's problems from R14.

**Rationale**: FR-002. The builder never asks for an id; JSON mode shows them for authors who prefer text.

## R18. What the player sees

**Decision**:
- **Tracker** (`QuestTracker`): a HUD panel on the right, under the credits, listing active quests (up to three, then "+N more") with their current visible beats; it follows `useQuests`.
- **Beat notice** (`BeatNotice`): a short title card in the style of `ZoneTitleCard` for each `beatFinished`, `questStarted` and `questEnded` notice, queued so they show one at a time.
- **Quest log** (`QuestLogPanel`, key **K**, beside Learned on **J**): a panel in the style of `LearnedFactsPanel`. Campaigns group their quests; each quest shows its latest run's finished and current visible beats, or its ending; earlier runs are listed under it (spec edge case). Active quests have an abandon control behind `ConfirmationModal`-style confirmation; ended quests whose ending failed have a "write the ending again" control.
- **Ending card** (`EndingCard`): a full-screen overlay in the style of `HandoverRequestConfirm`, showing the title, tier and epilogue, with a details toggle for each score and its reason (clarification Q3). It also serves campaigns.
- **Offer**: a request line in `WorldChat`, and `QuestOfferCard` modeled on `HandoverRequestConfirm` with accept (Enter) and decline (Esc).
- **Quest event log** (`QuestEventLog`) on the sessions page, beside `RevealLog`, for the world's author. Its approved section label is "Quest log"; in these documents it is always called the quest event log, and "quest log" means the player's panel.

All follow the UI standard in [feature 1's tasks.md](../018-items-inventory-credits/tasks.md).
