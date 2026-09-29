# Feature Specification: Quests for Worlds

**Feature Branch**: `claude/friendly-hypatia-j2gily`

**Created**: 2026-09-29

**Status**: Draft

**Input**: User description: "Quests for worlds (feature 3 of 3; builds on items, inventory and credits, and on facts and reveal safeguards). Quests with beats and conditions. Completion is judged by an LLM instead of choosing from fixed endings: when a quest ends, the LLM assesses how it went (good, bad, bittersweet, or free-form) against a rubric the author wrote. Conditions stay as open as possible: prose the LLM judges wherever rules aren't about tracked resources. Definitions per world, edited in a form mode and a raw JSON mode and validated against the world; progress per session with an append-only event log; beats form a graph through requirements; conditions checked on existing events, granted by residents in character, or judged from conversation; residents play their roles through knowledge added while a beat is active; quests start automatically, on a condition, or when offered by their giver and accepted; quests can require other quests' outcomes; an assessment writes the ending, and its consequences reach residents' long-term memory; creator mode gets quest tools; the play interface gets a tracker, a beat-complete notice, a quest log and an ending card."

## Clarifications

### Session 2026-09-29

- Q: How often are judged beats checked? → A: Nothing checks on a schedule or after every turn. Every trigger announces itself when it happens, and quests listen for the announcements their current beats depend on. For a question judged from conversation, the trigger is a named resident signalling in character that they believe it has been met; that signal is announced, and the judge verifies it before the beat finishes.
- Q: Is a campaign its own concept? → A: Yes: a named group of quests with its own view and an overall ending assessed against its own rubric.
- Q: How is an offered quest shown? → A: Both: the giver's offer appears as a request in the conversation, and it opens a card with the quest's details and accept and decline.
- Q: How far does residents' memory of a quest ending reach? → A: Only the play session it happened in; other sessions and conversations outside the world never see it.
- Q: Can an author hide beats from the player? → A: Yes, per beat: a hidden beat stays out of the tracker and quest log until it finishes, then appears with a notice.
- Q: What does the ending card show the player? → A: The title, epilogue and tier, with each score and its reason behind a details toggle.
- Q: Can a quest be played again in the same session? → A: Per quest, the author can make it repeatable; each run keeps its own progress, event log and ending, and requirements read the latest run.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Write a quest for a world (Priority: P1)

In a world's configuration, the user writes quests. A quest has a title, a description, how it starts, whether it can be played again after it ends, the beats the player moves through, what completes it, what fails it, and a rubric for judging how it went: the dimensions to score, and optionally a list of ending tiers such as "triumph", "bittersweet" and "ruin". Each beat has text for the player, the beats it requires, a condition that finishes it, and whether it is hidden from the player until it finishes. Conditions combine with all, any and not, and each part is either about something tracked (entering a region or a zone, talking to a resident, using an activity, a resident doing an activity, holding an item, holding at least an amount of credits, knowing a fact, a resident having learned a fact from the player, a flag), a flag a named resident may grant, or a question in prose judged from the conversation with named residents. A beat can also carry prose for a named resident, which that resident is given while the beat is active. The user edits a quest in a form or as raw JSON and can switch between the two. Saving checks that the quest is well formed and that everything it names exists in the world.

**Why this priority**: Nothing else in the feature has anything to act on until a quest exists.

**Independent Test**: Write a quest with three beats, where the second requires the first and the third requires the second, using one tracked condition, one granted flag and one judged question; save it in form mode, switch to JSON and back, and confirm nothing was lost. Then name a region that doesn't exist and make the first beat require the third, and confirm saving is refused with both problems listed.

**Acceptance Scenarios**:

1. **Given** a world, **When** the user writes a quest in form mode and saves it, **Then** it is listed in the world's quests.
2. **Given** a quest open in form mode, **When** the user switches to JSON mode, edits it and switches back, **Then** the form shows the edited quest.
3. **Given** JSON that isn't a well-formed quest, **When** the user tries to switch to form mode or save, **Then** it is refused and the user is told what is wrong and where.
4. **Given** a quest naming a region, zone, resident, item, fact, activity, beat or other quest that doesn't exist in the world, **When** the user saves, **Then** it is refused, listing every missing reference.
5. **Given** beats whose requirements form a cycle, **When** the user saves, **Then** it is refused, naming the beats in the cycle.
6. **Given** a quest whose start requires another quest's outcome, and that quest in turn requires this one, **When** the user saves, **Then** it is refused, naming the quests in the cycle.
7. **Given** a condition naming zones, residents, items, facts or activities, **When** the user edits it in form mode, **Then** each is picked from what exists in the world.
8. **Given** a resident, item, fact, region or activity that a quest names, **When** the user tries to delete it, **Then** the deletion is refused, naming the quests that use it.

---

### User Story 2 - Play through a quest (Priority: P1)

When a session starts, quests set to start automatically become active. As the player plays, what they do finishes beats: entering a region, reaching a zone, starting a conversation with someone, using an activity, holding what a beat asks for. A beat can only finish once the beats it requires are finished. The player sees their active quests and current beats in a tracker in the HUD, and a notice appears when a beat finishes. When a quest's completion condition matches, it is completed; when its failure condition matches, it fails. Everything that happens to a quest is recorded in order in its event log.

**Why this priority**: This is the core loop: a quest that moves forward as the player plays.

**Independent Test**: Write a quest that starts automatically with two beats, "enter this region" then "hold this item", start a session, travel to the region and pick up the item, and confirm each beat finishes in order with a notice, the tracker follows along, the quest completes, and the event log records every step.

**Acceptance Scenarios**:

1. **Given** a quest set to start automatically, **When** the player starts a new session, **Then** the quest is active and shown in the tracker with its first beats.
2. **Given** a beat that finishes when the player enters a region, **When** the player travels there, **Then** the beat finishes, a notice appears, and the tracker shows the beats that now become current.
3. **Given** a beat that finishes when the player enters a zone, **When** the player moves around inside that zone, **Then** the beat finishes once, when they first cross into it.
4. **Given** a beat whose required beats are not finished, **When** its condition matches, **Then** it does not finish, and it finishes as soon as its requirements are finished while its condition still holds.
5. **Given** a beat that needs 50 credits, **When** the player's balance reaches 50, **Then** the beat finishes; **When** it later drops below 50, **Then** the beat stays finished.
6. **Given** a beat that finishes when a resident has learned a fact from the player, **When** the player tells that resident and they acknowledge it, **Then** the beat finishes.
7. **Given** a quest's completion condition matches, **When** it is checked, **Then** the quest is completed and moves out of the tracker.
8. **Given** anything that finishes a beat, starts, completes or fails a quest, **When** it happens, **Then** it is appended to the quest's event log with what caused it; nothing in the log is ever changed or removed.
8a. **Given** a hidden beat is current, **When** the player looks at the tracker or quest log, **Then** it isn't shown; **When** it finishes, **Then** a notice appears and it shows in the quest log as finished.
9. **Given** a quest is added to a world with sessions in progress, **When** the player next plays one of them, **Then** the quest is there and starts as its definition says.

---

### User Story 3 - Residents play their part and grant progress (Priority: P1)

While a beat is active, the residents it names are given the prose the author wrote for them, so they know their role: what they want from the player, what they know, how they feel. A beat can let a named resident grant a flag; that resident decides in character, while talking with the player, whether the player has earned it, and grants it with a reason. Granting is the main way beats about what happens between the player and a character finish.

**Why this priority**: It fits how residents already act, through tools and in character, and it makes quests about people rather than checklists.

**Independent Test**: Write a beat that gives a resident prose ("you'll only trust the player once they've helped at the well") and lets them grant a flag, confirm their instructions carry the prose while the beat is active and not before, help at the well, talk to them, and confirm they grant the flag and the beat finishes.

**Acceptance Scenarios**:

1. **Given** a beat with prose for a resident, **When** that beat is active and the resident's instructions are built, **Then** they contain the prose; **When** the beat is not active, **Then** they don't.
2. **Given** a beat lets a resident grant a flag, **When** that beat is active and the resident talks with the player, **Then** the resident can grant that flag, with a reason, and it is recorded in the event log as granted by them.
3. **Given** a resident not named on a beat, or a flag the beat doesn't allow, **When** the resident tries to grant it, **Then** the grant is refused and nothing changes.
4. **Given** a beat is not active, **When** a resident named on it tries to grant its flag, **Then** the grant is refused.
5. **Given** residents talk with each other or act on their own, **When** their instructions are built, **Then** they carry the prose of active beats but granting is not available.

---

### User Story 4 - Conditions judged from conversation (Priority: P2)

A beat condition can be a question in prose, "has the player convinced the miller that the flood wasn't their fault?", tied to residents the author names. Those residents know the question while the beat is active. When one of them believes, in character, that the player has met it, they signal it. That signal starts the check: a separate model reads the conversation and answers yes or no, citing the messages that prove it and giving a reason. Out-of-character text is removed before it reads anything. No check runs without a signal, and only residents the beat names can signal.

**Why this priority**: It covers whatever tracked conditions and grants can't, but quests are fully playable without it.

**Independent Test**: Write a beat with a question tied to one resident, talk with them without meeting it and confirm no check runs, then meet it, confirm the resident signals, and confirm the answer is yes with messages cited; confirm a resident the beat doesn't name can't signal.

**Acceptance Scenarios**:

1. **Given** an active beat with a question tied to a resident, **When** the resident signals it and the answer is yes, **Then** the condition holds, and the event log records the signal, the answer, the cited messages and the reason.
2. **Given** the answer is no, **When** it is recorded, **Then** the condition does not hold, the answer is kept in the event log, and the resident can signal again later in the conversation.
3. **Given** the player talks with residents, **When** no named resident signals, **Then** no check runs.
4. **Given** a resident the beat doesn't name, or a beat that isn't active, **When** a resident tries to signal, **Then** the signal is refused.
5. **Given** the player meets the question only inside `[OOC: ...]`, **When** the check runs, **Then** it answers no, because the OOC text was removed.
6. **Given** a yes that cites no messages, or messages outside the conversation, **When** it is received, **Then** it is treated as no and recorded as such.
7. **Given** the check cannot be completed, **When** it fails, **Then** the condition does not hold, the failure is recorded, and the resident can signal again.

---

### User Story 5 - Endings judged against the rubric (Priority: P1)

When a quest completes, fails, or the player abandons it, a model reads the quest's description, the rubric, the event log, the flags, and excerpts of the relevant conversations with out-of-character text removed. It writes the ending: a tier from the author's list if there is one, a title, an epilogue, a score with a reason for each dimension, and flags that result from how it went. The player sees an ending card with the title, epilogue and tier, and can open its details to read each score and its reason. The residents involved remember how it ended, and later quests can read its outcome and resulting flags.

**Why this priority**: This is what makes quests open: the ending is judged from what really happened, not picked from a fixed list.

**Independent Test**: Write a quest with the tiers "triumph", "bittersweet" and "ruin" and two dimensions, complete it, and confirm an ending card appears with a tier from the list, a title and an epilogue; confirm the scores and reasons are stored; confirm the giver remembers the ending in a later conversation.

**Acceptance Scenarios**:

1. **Given** a quest completes, **When** its ending is assessed, **Then** it has a title, an epilogue, a score and reason for each rubric dimension, and a tier from the author's list if the author gave one.
2. **Given** the author gave no tiers, **When** the ending is assessed, **Then** it describes how it went freely.
3. **Given** a quest fails, **When** its ending is assessed, **Then** the assessment knows it failed and why, and writes an ending for it.
4. **Given** the player abandons an active quest from the quest log, **When** they confirm, **Then** the quest is abandoned and its ending is assessed.
5. **Given** an ending is assessed, **When** it is shown, **Then** the player sees an ending card with the title, epilogue and tier, can open its details to see each score with its reason, and can reread it in the quest log.
6. **Given** an ending, **When** it is recorded, **Then** the residents involved in the quest remember it in later conversations in the same session, and in no other session or conversation outside the world.
7. **Given** the assessment's resulting flags, **When** a later quest's condition names one of them, **Then** the condition can hold.
8. **Given** the event log includes steps done through creator mode, **When** the ending is assessed, **Then** the assessment is told those steps did not happen in the story.
9. **Given** the assessment cannot be completed, **When** it fails, **Then** the quest keeps its status, the failure is recorded, the ending card says the ending isn't written yet, and the assessment can be run again.

---

### User Story 6 - Quests offered by residents, and quests that chain (Priority: P2)

A quest can name a giver and start when offered. The giver offers it in character when it suits the conversation: the offer appears as a request in the conversation, and opening it shows a card with the quest's details and accept and decline. A quest can also start when a condition matches, or require other quests' outcomes before it can start (completed, failed, abandoned, or a particular ending tier), which is how quests chain into longer stories.

**Why this priority**: Offers and chains make quests feel like part of the world, but single quests that start automatically or on a condition already work without them.

**Independent Test**: Write a quest the giver offers, talk to them, accept it and confirm it becomes active; write a second quest requiring the first to end in "triumph", end the first differently and confirm the second never becomes available, then reset and end it in triumph and confirm it does.

**Acceptance Scenarios**:

1. **Given** a quest that starts when offered, **When** its giver talks with the player and its requirements are met, **Then** the giver can offer it, and only quests where they are the giver.
2. **Given** a quest is offered, **When** the player sees the offer, **Then** it appears as a request in the conversation, and opening it shows a card with the quest's title, description and giver, with accept and decline.
2a. **Given** an offer the player hasn't answered, **When** the conversation ends, **Then** the offer is withdrawn and the quest stays available.
3. **Given** the player accepts an offer, **When** they do, **Then** the quest becomes active and the giver is told.
4. **Given** the player declines an offer, **When** they do, **Then** the quest stays available, the giver is told, and it can be offered again later.
5. **Given** a quest that starts on a condition, **When** the condition matches, **Then** it becomes active.
6. **Given** a quest requiring another quest's outcome, **When** that quest's latest run hasn't ended that way, **Then** it cannot start or be offered.
7. **Given** a repeatable quest whose run has ended, **When** its start condition matches again or its giver offers it again, **Then** a new run starts with no finished beats or flags, while the earlier run keeps its event log and ending.
8. **Given** a quest that isn't repeatable and has ended, **When** its giver tries to offer it or its start condition matches, **Then** it doesn't start again.

---

### User Story 7 - Campaigns (Priority: P2)

In a world's configuration, the user groups quests into a campaign: a title, a description, the quests it contains, and a rubric of its own, with dimensions and optional ending tiers. The quests inside still chain through their requirements. When the campaign is over, because none of its quests is active or can still start (a repeatable quest counts once its first run has ended), its overall ending is assessed from the endings of its quests and their event logs, and the player sees a campaign ending card like a quest's. In the quest log, the player sees each campaign with its quests grouped under it.

**Why this priority**: Campaigns turn chained quests into one story with its own ending, but each quest already plays and ends on its own without them.

**Independent Test**: Group two chained quests into a campaign with tiers, play both to an end, and confirm the campaign's ending is assessed once, after the second quest's ending, with a tier from its list, and that the quest log shows both quests under the campaign.

**Acceptance Scenarios**:

1. **Given** a world with quests, **When** the user creates a campaign with a title, description, some of those quests and a rubric, **Then** it is listed in the world's campaigns.
2. **Given** a quest, **When** the user tries to put it in a second campaign, **Then** it is refused, since a quest belongs to at most one campaign.
3. **Given** a campaign, **When** its last active quest ends and none of its other quests can still start, **Then** its ending is assessed once from its quests' endings and event logs, and the player sees a campaign ending card with a title and epilogue.
4. **Given** a campaign with ending tiers, **When** its ending is assessed, **Then** its tier comes from its own list.
5. **Given** a campaign, **When** the player opens the quest log, **Then** its quests appear grouped under it, with its ending once it has one.
6. **Given** a campaign ending, **When** it is recorded, **Then** the residents involved in its quests remember it in that session, and later quests can require it.
7. **Given** a quest in a campaign is reset through creator mode after the campaign ended, **When** it is reset, **Then** the campaign's ending is cleared as well, and assessed again once the campaign is over.

---

### User Story 8 - Creator mode for quests (Priority: P2)

While creator mode is active, the creator can command the character to start, complete, fail or reset a quest, finish or undo a beat, set or clear flags, and edit a quest's definition, which is checked the same way as in the configuration. Each of these counts in the game, is recorded as done by the creator, and the ending assessment knows it didn't happen in the story.

**Why this priority**: Testing a quest by playing it through each time is slow; creator commands make it quick, but play works without them.

**Independent Test**: Activate creator mode, command a beat finished and a flag set, confirm both in the tracker and in the event log marked as done by the creator, then reset the quest and confirm its progress is gone.

**Acceptance Scenarios**:

1. **Given** creator mode is active, **When** the creator commands a beat finished, **Then** it finishes and is recorded as done by the creator.
2. **Given** creator mode is active, **When** the creator commands a beat undone, **Then** it is no longer finished, and beats that required it are undone too.
3. **Given** creator mode is active, **When** the creator commands a quest reset, **Then** its latest run's progress, flags and ending are cleared and it starts again as its definition says; the event log records the reset and keeps what came before.
4. **Given** creator mode is active, **When** the creator commands an edit that names something not in the world, **Then** the edit is refused with the same messages as the configuration.
5. **Given** creator mode is inactive, **When** the player asks for any of these, **Then** the character has no quest admin tools.

---

### User Story 9 - The quest log (Priority: P3)

During play, the player opens a quest log: active quests with their finished and current beats, and ended quests with their ending. The user can also read a session's quest event log, to understand an ending or debug a quest.

**Why this priority**: Helps the player follow longer stories and the author tune quests, but the tracker and ending card already cover play.

**Independent Test**: Finish one quest and keep another active, open the quest log, and confirm both appear with their beats and the ending; open the event log and confirm every step appears in order.

**Acceptance Scenarios**:

1. **Given** active and ended quests, **When** the player opens the quest log, **Then** active ones show finished and current beats, and ended ones show their status and ending.
2. **Given** no quests, **When** the player opens the log, **Then** it shows that there are none yet.
3. **Given** a session with quest activity, **When** the user opens its quest event log, **Then** every event appears in order with the beat, what happened, what caused it and whether it was done by the creator.

---

### Edge Cases

- A quest's completion and failure conditions match at the same time: the quest fails, and the assessment is told both matched.
- One thing the player does finishes beats in several quests: each quest records it and the player sees one notice per beat.
- A quest's definition is edited while sessions are playing it: finished beats that still exist stay finished, beats removed from the definition drop from progress, and the event log is untouched.
- A quest is deleted while sessions are playing it: the user is warned how many sessions have it, and on confirmation it and its progress are removed from them.
- A resident named on a beat is removed from their region: the removal is refused while a quest names them, as with any other reference.
- A judged check is still running when the quest ends: its answer is recorded but changes nothing.
- A named resident signals the same question again while a check for it is still running: the second signal is recorded and no second check runs.
- A repeatable quest set to start automatically: it starts once when the session starts, and later runs start only through a condition or an offer, so it never restarts on its own the moment it ends.
- A campaign contains a repeatable quest: for the campaign, that quest counts as done once its first run ends, so the campaign can still end.
- The quest log with several runs of one quest: the latest run is shown first, and earlier runs with their endings are listed under it.
- A campaign's quest is deleted: the campaign keeps its other quests; a campaign left with no quests stays in the configuration, empty, until the user deletes it.
- The player abandons a quest while it is being assessed: nothing changes; it already ended.
- A beat's condition is only about a flag a resident grants, and that resident's model cannot call tools: saving the quest warns that the flag can't be granted.
- Completion is not written: the quest completes when every beat is finished.
- A resident tries to offer a quest that is already active, has ended, or whose requirements aren't met: the offer is refused.
- The player tells a resident something inside `[creator mode: ...]`: like OOC text, it is removed before any judged check or assessment reads the conversation.
- A quest resets after its ending was remembered by residents: they keep that memory; the reset is recorded in the event log.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Users MUST be able to create, edit and delete quests per world, each with a title, description, how it starts, its beats, completion and failure conditions, and a rubric of dimensions with an optional list of ending tiers.
- **FR-002**: Users MUST be able to edit a quest in a form mode and in a raw JSON mode and switch between them without losing anything; the form MUST offer world data (regions, zones, residents, items, facts, activities, beats, other quests) as choices wherever a condition names one.
- **FR-003**: Saving MUST refuse a quest that isn't well formed, that names anything not in the world, whose beat requirements form a cycle, or whose quest requirements form a cycle across quests, listing every problem.
- **FR-004**: Deleting a region, resident, item, fact or activity MUST be refused while a quest names it, naming those quests.
- **FR-005**: Conditions MUST combine with all, any and not, over: entering a region, entering a zone, starting a conversation with a resident, the player using an activity, a resident doing an activity, the player holding an item, the player holding at least an amount of credits, the player knowing a fact, a resident having learned a fact from the player, a flag, and a question judged from conversation.
- **FR-006**: Quest progress MUST be driven by what happens in the game: each thing a condition can depend on MUST be announced as it happens, and quests MUST react only to the announcements their current beats and conditions depend on, with nothing checked on a schedule or re-checked every turn. Entering a zone MUST count only when the player crosses into it.
- **FR-007**: A beat MUST finish only once every beat it requires has finished and its condition holds; finished beats MUST stay finished unless undone through creator mode.
- **FR-008**: Progress MUST be kept per run of a quest in a session: its status (available, active, completed, failed, abandoned), finished beats, flags, event log and ending, belonging to one session of one player only. A quest that isn't repeatable MUST have at most one run per session; a repeatable one MUST be able to start a new run after its latest run ends, and requirements on its outcome MUST read its latest run.
- **FR-009**: Every change to a quest's progress MUST be appended to its event log with the beat, what happened, what caused it and whether it was done by the creator; the log MUST never be edited or trimmed.
- **FR-010**: While a beat is active, each resident it names MUST be given the prose the author wrote for them in their instructions, in every kind of turn.
- **FR-011**: A resident MUST be able to grant a flag, with a reason, only while talking with the player, while the beat allowing it is active, and only the flags that beat allows them.
- **FR-012**: While a beat with a judged question is active, the residents it names MUST know the question and be able to signal, in a conversation with the player, that they believe it has been met. Only such a signal MUST start a check: a separate model answering yes or no with cited messages and a reason, after removing OOC and creator-command text. A yes without valid citations MUST count as no, and at most one check per question MUST run at a time.
- **FR-013**: Quests MUST be able to start automatically when a session starts, when a condition matches, or when offered by their giver and accepted by the player; a quest MUST be able to require other quests' outcomes before it can start.
- **FR-014**: Only a quest's giver MUST be able to offer it, and only while its requirements are met and it isn't active or ended. The offer MUST appear as a request in the conversation that opens a card with the quest's details, where the player accepts or declines; an unanswered offer MUST be withdrawn when the conversation ends.
- **FR-015**: When a quest completes, fails or is abandoned, its ending MUST be assessed by one model call reading the description, rubric, event log, flags and excerpts of the relevant conversations with OOC and creator-command text removed, producing a tier from the author's list when there is one, a title, an epilogue, a score and reason per dimension, and resulting flags.
- **FR-016**: An ending MUST be remembered by the residents involved in the quest (its giver, the residents its beats name, and residents who granted its flags) in that session only, never in other sessions or in conversations outside the world, and its outcome and resulting flags MUST be readable by later quests' conditions and requirements.
- **FR-017**: The player MUST be able to abandon an active quest, after confirming.
- **FR-018**: While creator mode is active, the character MUST have tools to start, complete, fail and reset a quest, finish or undo a beat, set or clear flags, and edit a quest's definition with the same checks as the configuration; every such action MUST count in the game and be recorded as done by the creator, and assessments MUST be told which steps were.
- **FR-018a**: Users MUST be able to create, edit and delete campaigns per world, each with a title, description, its quests and its own rubric; a quest MUST belong to at most one campaign.
- **FR-018b**: When none of a campaign's quests is active or can still start, counting a repeatable quest once its first run has ended, its ending MUST be assessed once from its quests' endings and event logs, against its own rubric, and remembered by the residents involved in its quests in that session only; later quests MUST be able to require a campaign's outcome.
- **FR-018c**: Authors MUST be able to mark a beat hidden; a hidden beat MUST stay out of the tracker and quest log until it finishes, and then appear with a notice.
- **FR-019**: The play interface MUST show a tracker of active quests and their current beats that aren't hidden, a notice when a beat finishes, a quest log with quests grouped under their campaigns, and ending cards for quests and campaigns with their title, epilogue and tier, and each score with its reason behind a details toggle.
- **FR-020**: Users MUST be able to read a session's quest event log.
- **FR-021**: The app MUST NOT build in any particular world, region, resident or quest; every quest comes from a world's configuration.
- **FR-022**: Every new screen, panel and control MUST follow the app's current theme and look polished, consistent with the existing HUD and configuration screens.

### Key Entities

- **Quest**: Written once per world: title, description, how it starts (and its giver, when offered), quest requirements, beats, completion and failure conditions, rubric and optional ending tiers.
- **Beat**: A step of a quest: text for the player, whether it is hidden until it finishes, the beats it requires, the condition that finishes it, flags named residents may grant, and prose for named residents.
- **Condition**: A combination (all, any, not) of tracked checks, flags, and judged questions.
- **Session quest**: One run of a quest in one session: status, finished beats, flags, and ending. A repeatable quest can have several runs in a session.
- **Quest event**: One appended entry of a run's log: the beat, what happened, what caused it, its details, whether the creator did it, and when.
- **Ending**: The assessed result of a quest or a campaign: tier, title, epilogue, scores with reasons per dimension, resulting flags.
- **Campaign**: Written once per world: title, description, the quests it groups, and its own rubric with optional ending tiers. Its ending is kept per session.
- **Offer**: A giver's pending offer of a quest in one conversation, until the player accepts or declines it or the conversation ends.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can write a three-beat quest with a rubric in form mode in under 10 minutes.
- **SC-002**: 100% of quests naming something missing from the world, or with a cycle, are refused on save, with every problem listed.
- **SC-003**: After a tracked condition is met, the tracker and notice show the finished beat within 2 seconds.
- **SC-004**: 0 judged checks run without a signal from a resident the beat names.
- **SC-005**: On a set of scripted conversations with an expected answer, judged checks agree in at least 90% of cases.
- **SC-006**: The ending card appears within 30 seconds of a quest or campaign ending.
- **SC-007**: 100% of changes to quest progress appear in the event log, and 100% of those done through creator mode are marked as such.
- **SC-008**: A resident involved in a quest refers to its ending, when asked, in a conversation after it ended.

## Assumptions

- "Quest", "beat", "flag", "rubric", "tier", "giver", "epilogue", "campaign", "quest tracker", "quest log" and "ending card" come from the feature description; any other UI label, term or tool name is proposed in the plan and used only after approval.
- The detailed layout of the form mode (the beats list and the condition builder) is designed in the plan and approved there.
- Judged checks and ending assessments use the world's narrator model, falling back to the default model like narration does.
- Several quests can be active at once.
- Every beat has text for the player, shown once it becomes current, or once it finishes when it is hidden.
- Tracked conditions read state as it is when they're checked: a beat about holding an item finishes the moment the player holds it, even if they held it before the beat became current.
- Out-of-character and creator-command handling, facts, items, credits, activities, narration, residents' long-term memory and creator mode already exist as built in earlier features.
- A reset does not withdraw what residents remember of an ending.
- Quests are played only in world sessions; Discord conversations are out of scope.
