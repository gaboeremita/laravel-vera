# Feature Specification: Offer Conditions for Quests

**Feature Branch**: `claude/optimistic-mendel-ss9haa`

**Created**: 2026-09-29

**Status**: Draft

**Input**: User description: "Offer conditions for quests (builds on 020 world quests, quest rewards and resident feelings). An offered quest can carry an optional \"offerWhen\" condition: each time the player talks with the giver, the condition is checked, and only while it holds does the giver see the quest in their prompt and get the offer_quest tool. offerWhen uses the existing condition tree (all, any, not and every existing leaf: has, credits, knows, acknowledged, flag, enterRegion, enterZone, talkTo, use, residentDid) plus new leaves: feeling, questState, declinedTimes, gaveTo, spentWith, sessionMinutes, messagesWith, giverIn, othersInTheZone. An offered quest can also carry an \"offerQuestion\": prose the giver keeps in mind; the giver signals when they believe it is met, and the existing question judge checks it against the conversation before the quest becomes offerable. feeling, questState, declinedTimes, gaveTo and spentWith work everywhere conditions do (beats, complete, fail, start.when), with triggers so beats react when they change; sessionMinutes, messagesWith, giverIn and aloneWithGiver are only meaningful at the moment the player talks with the giver and are allowed in offerWhen only. gaveTo needs a record of item handovers, written wherever items move between inventories. The quest editor's condition builder offers every new leaf with pickers from the world's data, and validation refuses unknown residents, items, quests and zones and leaves used outside where they are allowed. Open for clarification: whether sessionMinutes counts time since the session was created or time actually played (play time isn't tracked today)."

## Clarifications

### Session 2026-09-29

- Q: How does the session-age part count time? → A: It is left out of this feature; offerWhen has no part about how old the session is.
- Q: What is the part about who else is in the giver's zone called, and what shape does it take? → A: One part, othersInTheZone, with two forms: a named resident is in the giver's zone, or no resident other than the giver is.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A giver offers a quest only when the moment is right (Priority: P1)

In a quest that starts when offered, the author writes an offerWhen condition, for example "the player holds the lantern and the giver's trust is at least 3". Each time the player talks with the giver, the condition is checked. While it holds, the giver sees the quest and can offer it; while it doesn't, the giver knows nothing of the quest and cannot offer it. The condition can use every existing condition part and combine them with all, any and not.

**Why this priority**: This is the core of the feature. Without it, a giver can offer a quest to a player who has done nothing to earn it.

**Independent Test**: Write an offered quest whose offerWhen is "the player holds the lantern". Talk to the giver without the lantern and confirm they don't mention the quest and have no way to offer it. Pick up the lantern, talk to them again, and confirm they can offer it.

**Acceptance Scenarios**:

1. **Given** an offered quest with an offerWhen that doesn't hold, **When** the player talks with the giver, **Then** the giver's instructions don't contain the quest and the giver cannot offer it.
2. **Given** the same quest, **When** the condition holds as the player talks with the giver, **Then** the giver's instructions contain the quest and the giver can offer it.
3. **Given** an offered quest without offerWhen, **When** the player talks with the giver, **Then** it behaves as it does today.
4. **Given** an offer is pending or the quest is active, **When** offerWhen stops holding, **Then** the pending offer stays until the player answers it or the conversation ends, and an active quest stays in the giver's instructions as it does today.
5. **Given** offerWhen's requirements on other quests' outcomes are met but offerWhen doesn't hold, **When** the player talks with the giver, **Then** the quest cannot be offered; both must hold.

---

### User Story 2 - Conditions about feelings, other quests and exchanges (Priority: P1)

The author can write conditions about how a resident feels toward the player (romance, trust or liking at least or at most a value from -10 to 10), about another quest's state (its latest run offered, active, declined or abandoned), about how many times the player declined another quest's offer, about how much of an item the player gave a resident this session, and about how many credits the player paid a resident in total this session. These parts work in every condition: offerWhen, beats, completion, failure and a quest's start condition. When one of these things changes, the quests whose current conditions depend on it react, so a beat can finish the moment a resident's trust reaches the value it needs.

**Why this priority**: These are what make an offer feel earned, and the same parts make beats about relationships and exchanges possible.

**Independent Test**: Write a quest that starts automatically with one beat "the baker's liking is at least 4". Raise the baker's liking in conversation until it reaches 4 and confirm the beat finishes then, with the change recorded as its cause. Write a second beat "the player gave the baker at least 2 bread", hand over one bread, then another, and confirm it finishes after the second.

**Acceptance Scenarios**:

1. **Given** a beat requiring a resident's trust to be at least 3, **When** that resident's trust toward the player reaches 3, **Then** the beat finishes and the event log records the feeling change as its cause.
2. **Given** a condition requiring a resident's romance to be at most -2, **When** the resident's romance is -2 or lower, **Then** the condition holds.
3. **Given** a condition on another quest's latest run being declined, **When** the player declines that quest's offer, **Then** the condition holds; **When** the player later accepts it, **Then** the state is active and the declined condition no longer holds.
4. **Given** a condition requiring that another quest's offer was declined at least 2 times, **When** the player declines it a second time in the session, **Then** the condition holds.
5. **Given** a condition requiring the player gave a resident at least 3 of an item this session, **When** the player hands that resident the item in two handovers of 1 and 2, **Then** the condition holds.
6. **Given** a condition requiring the player paid a resident at least 50 credits this session, **When** the player pays them 20 and then 30, **Then** the condition holds.
7. **Given** a resident gives an item back to the player, **When** the gave-to count is read, **Then** only what the player gave that resident counts; what came back doesn't subtract from it.
8. **Given** a quest whose start condition names one of these parts, **When** that part starts to hold, **Then** the quest starts.

---

### User Story 3 - Conditions about the moment of the conversation (Priority: P2)

For offerWhen only, the author can also write conditions about the moment the player talks with the giver: the player has sent the giver at least some messages this session, the giver is in a given zone right now, and, through othersInTheZone, a given resident, or no resident other than the giver, is in the giver's zone. These describe the present moment, so they are checked each time the player talks with the giver and are refused anywhere else.

**Why this priority**: They add pacing and privacy to offers ("only once we're alone at the docks"), but offers already work with the parts from User Story 2.

**Independent Test**: Write an offered quest whose offerWhen is "the giver is in the docks zone and no other resident is in the zone". Talk to the giver at the docks while another resident stands there and confirm no offer is possible; talk again after that resident leaves and confirm the giver can offer it. Then try to put the same condition on a beat and confirm saving is refused.

**Acceptance Scenarios**:

1. **Given** offerWhen requires at least 5 messages sent to the giver this session, **When** the player sends their fifth message to the giver, **Then** the condition holds for that turn.
2. **Given** offerWhen requires the giver to be in a zone, **When** the player talks with the giver elsewhere, **Then** the quest cannot be offered.
3. **Given** offerWhen requires a named resident in the zone, **When** that resident is in the giver's zone, **Then** the condition holds.
4. **Given** offerWhen requires no other resident in the zone, **When** any resident other than the giver is in the giver's zone, **Then** the condition doesn't hold.
5. **Given** a beat, completion, failure or start condition, **When** it names any of these parts, **Then** saving is refused, naming the part and where it was used.

---

### User Story 4 - A question the giver answers before offering (Priority: P2)

An offered quest can carry an offerQuestion, prose such as "Has the player shown they can keep a secret?". While offerWhen holds, or when there is none, the giver keeps the question in mind as they talk with the player. When the giver believes, in character, that it has been met, they signal it, and the existing judge reads the conversation, with out-of-character and creator-command text removed, and answers yes or no with cited messages and a reason. Only after a yes does the quest become offerable.

**Why this priority**: It covers what tracked conditions can't, such as trust shown in words, but offers already work without it.

**Independent Test**: Write an offered quest with the offerQuestion "Has the player shown they can keep a secret?". Talk to the giver without meeting it and confirm no check runs and no offer is possible. Meet it, confirm the giver signals, the judge answers yes with cited messages, and the giver can then offer the quest.

**Acceptance Scenarios**:

1. **Given** an offered quest with an offerQuestion and offerWhen holding, **When** the player talks with the giver, **Then** the giver's instructions carry the question and the giver can signal it, but cannot offer the quest yet.
2. **Given** the giver signals and the judge answers yes, **When** it is recorded, **Then** the quest becomes offerable while offerWhen holds, and the event log records the signal, answer, cited messages and reason.
3. **Given** the judge answers no or cannot complete the check, **When** it is recorded, **Then** the quest stays not offerable and the giver can signal again later.
4. **Given** offerWhen doesn't hold, **When** the player talks with the giver, **Then** the giver doesn't carry the question and cannot signal it.
5. **Given** the question was met earlier in the session, **When** the player talks with the giver later, **Then** it stays met and only offerWhen decides whether the quest can be offered.
6. **Given** any resident other than the giver, **When** they try to signal the offerQuestion, **Then** the signal is refused.

---

### User Story 5 - Writing offer conditions in the quest editor (Priority: P1)

In the quest editor, an offered quest has a place for offerWhen and one for offerQuestion. The condition builder offers every new condition part wherever it is allowed, with residents, items, quests and zones picked from the world's data. Saving refuses anything that names something not in the world, and any part used where it isn't allowed. The same checks apply in JSON mode and in creator mode edits.

**Why this priority**: Authors can't use any of the above without a way to write it and a check that it is right.

**Independent Test**: In form mode, give an offered quest an offerWhen combining a feeling part and a giver-in-zone part, and an offerQuestion; switch to JSON and back and confirm nothing was lost. Then name a zone that doesn't exist and put a messages-sent part on a beat, and confirm saving is refused with both problems listed.

**Acceptance Scenarios**:

1. **Given** an offered quest in form mode, **When** the user edits it, **Then** it offers fields for offerWhen and offerQuestion; **Given** a quest that doesn't start when offered, **Then** it doesn't.
2. **Given** the condition builder in offerWhen, **When** the user adds a part, **Then** every existing and new part is offered; **Given** the builder in a beat, completion, failure or start condition, **Then** only the parts allowed there are offered.
3. **Given** a new part naming a resident, item, quest or zone, **When** the user edits it in form mode, **Then** each is picked from what exists in the world.
4. **Given** a quest naming a resident, item, quest or zone that doesn't exist, **When** the user saves, **Then** it is refused, listing every missing reference.
5. **Given** a feeling value outside -10 to 10, or a count or amount below 1, **When** the user saves, **Then** it is refused, naming the value and where it is.
6. **Given** a quest that doesn't start when offered, **When** its JSON carries offerWhen or offerQuestion, **Then** saving is refused.
7. **Given** a questState or declinedTimes part naming the quest it belongs to, **When** the user saves, **Then** it is accepted, since those read the quest's own earlier runs and offers.
8. **Given** a resident, item or quest named by one of the new parts, **When** the user tries to delete it, **Then** the deletion is refused, naming the quests that use it, as with every existing reference.

---

### Edge Cases

- The giver is not in the same zone as the player when they talk: giverIn and the zone parts read the giver's zone.
- A zone named by giverIn or the zone parts disappears from a new layout: the quest is listed with the problem, as with existing zone references, and the part never holds in play.
- A resident's feeling has never been set: it reads as the starting value residents already use for feelings.
- The player pays a resident through a trade where both sides hand something over: the player's side counts toward gaveTo and spentWith.
- Items or credits move through creator mode: they count, and the event log shows they were moved by the creator.
- A quest reward moves items from a resident to the player: it doesn't count toward gaveTo or spentWith, which only count what the player hands over.
- questState names a repeatable quest: it reads its latest run; declinedTimes counts every declined offer of that quest in the session, across runs.
- Several offered quests share a giver: each quest's offerWhen and offerQuestion are checked on their own, and the giver sees only the ones that hold.
- offerWhen holds when the player starts talking and stops holding mid-conversation (another resident walks into the zone): it is checked again each time the player sends the giver a message, and the giver loses the quest from their instructions for turns where it no longer holds, unless an offer is already pending.
- A quest's definition is edited to add or change offerWhen while a session is playing it: the next time the player talks with the giver, the new condition applies.
- The offerQuestion is changed after it was met: the earlier yes no longer counts, since it answered a different question.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: A quest that starts when offered MUST be able to carry an optional offerWhen condition and an optional offerQuestion; a quest that starts any other way MUST NOT carry either.
- **FR-002**: Each time the player talks with a giver, the offerWhen of every quest they give MUST be checked. The giver MUST see a quest in their instructions and be able to offer it only while its requirements on other quests are met, its offerWhen holds (or it has none), and its offerQuestion has been met (or it has none).
- **FR-003**: A pending offer and an active quest MUST NOT be affected by offerWhen or offerQuestion.
- **FR-004**: offerWhen MUST accept all, any and not, and every existing condition part.
- **FR-005**: Conditions MUST support a feeling part: a named resident's romance, trust or liking toward the player is at least and/or at most a value from -10 to 10.
- **FR-006**: Conditions MUST support a questState part: another quest's latest run is offered (an offer is pending), active, declined (its latest offer was declined and it hasn't started since), or abandoned.
- **FR-007**: Conditions MUST support a declinedTimes part: the player declined a named quest's offer at least a number of times in the session.
- **FR-008**: Conditions MUST support a gaveTo part: the player gave a named resident at least a quantity of a named item in the session, summed over every handover.
- **FR-009**: Conditions MUST support a spentWith part: the player paid a named resident at least an amount of credits in total in the session.
- **FR-010**: The feeling, questState, declinedTimes, gaveTo and spentWith parts MUST be allowed in every condition (offerWhen, beats, completion, failure and start). A change to what each depends on MUST be announced as it happens, and quests MUST react to it as they do to existing announcements, recording it as the cause.
- **FR-011**: Every time items or credits move from the player to a resident, the handover MUST be recorded per session with the resident, the items and quantities, and the credits, including moves made through creator mode, which are marked as such.
- **FR-012**: offerWhen MUST additionally support: messagesWith (the player has sent a named resident at least a number of messages in the session); giverIn (the giver is in a named zone right now); and othersInTheZone, in one of two forms: a named resident is in the giver's zone, or no resident other than the giver is in the giver's zone.
- **FR-013**: The parts in FR-012 MUST be allowed only in offerWhen, and MUST be read as they stand at the moment of the player's message to the giver.
- **FR-014**: While a quest's offerWhen holds (or it has none) and its offerQuestion isn't met, the giver MUST carry the question in their instructions and be able to signal it, in a conversation with the player. A signal MUST start the existing judged check, with out-of-character and creator-command text removed, and the same rules for citations, failures and one running check per question.
- **FR-015**: A yes to an offerQuestion MUST stay met for the rest of the session, until the question's text is changed; every signal, answer, citation and reason MUST be recorded in the quest's event log.
- **FR-016**: Saving a quest, in form mode, JSON mode or through creator mode, MUST refuse offerWhen or offerQuestion on a quest that doesn't start when offered, any new part naming a resident, item, quest or zone not in the world, feeling values outside -10 to 10, counts, quantities or amounts below 1, and any part of FR-012 used outside offerWhen, listing every problem with where it is.
- **FR-017**: Deleting a resident, item or quest named by a new part MUST be refused, naming the quests that use it; zones named by the new parts MUST be treated as existing zone references are.
- **FR-018**: The quest editor's form mode MUST offer offerWhen and offerQuestion on offered quests, offer each new part in the condition builder only where it is allowed, and pick residents, items, quests and zones from the world's data; switching between form and JSON MUST lose nothing.
- **FR-019**: New editor controls MUST follow the app's current theme and match the existing quest editor.

### Key Entities

- **Offer condition (offerWhen)**: A condition on an offered quest, checked each time the player talks with the giver, that decides whether the giver sees and can offer the quest.
- **Offer question (offerQuestion)**: Prose on an offered quest that the giver keeps in mind; once judged met, it stays met for the session.
- **Handover record**: One movement of items or credits from the player to a resident in a session: the resident, the items and quantities, the credits, when it happened, and whether the creator made it.
- **New condition parts**: feeling, questState, declinedTimes, gaveTo and spentWith, usable everywhere; messagesWith, giverIn and othersInTheZone, usable in offerWhen only.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In 100% of conversations with a giver whose quest's offerWhen doesn't hold, the giver cannot offer the quest.
- **SC-002**: A beat depending on a feeling, a quest's state, a decline count, a handover or a payment finishes within 2 seconds of the change that makes it hold.
- **SC-003**: 100% of item and credit movements from the player to a resident appear in the handover record.
- **SC-004**: 100% of quests naming something missing from the world, or using a part where it isn't allowed, are refused on save with every problem listed.
- **SC-005**: 0 judged checks for an offerQuestion run without a signal from the quest's giver.
- **SC-006**: A user can add an offerWhen with two parts and an offerQuestion to an offered quest in form mode in under 3 minutes.

## Assumptions

- "offerWhen", "offerQuestion" and the part names feeling, questState, declinedTimes, gaveTo, spentWith, messagesWith, giverIn and othersInTheZone come from the feature description; any UI label for them is proposed in the plan and used only after approval.
- Completed and failed outcomes of other quests stay covered by the existing quest requirements and ending flags, so questState covers offered, active, declined and abandoned.
- Feelings, the question judge, offers, declines, item and credit handovers, zones and residents' positions already exist as built in earlier features; the handover record is new.
- Handovers are counted from the session's handover record, so items and credits the player handed over before this feature existed don't count.
- "This session" means the current play session of the world; counts don't carry over to other sessions.
- Only messages the player sends to the named resident count toward messagesWith; messages from the resident, narration and other residents' messages don't.
- A part about how long the session has lasted is out of scope for this feature.
- The giver is always a resident, as quests that start when offered already require.
