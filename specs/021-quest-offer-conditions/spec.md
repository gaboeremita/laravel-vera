# Feature Specification: Offer Conditions for Quests

**Feature Branch**: `claude/optimistic-mendel-ss9haa`

**Created**: 2026-09-29

**Status**: Draft

**Input**: User description: "Offer conditions for quests (builds on 020 world quests, quest rewards and resident feelings). An offered quest can carry an optional \"offerWhen\" condition: each time the player talks with the giver, the condition is checked, and only while it holds does the giver see the quest in their prompt and get the offer_quest tool. offerWhen uses the existing condition tree (all, any, not and every existing leaf: has, credits, knows, acknowledged, flag, enterRegion, enterZone, talkTo, use, residentDid) plus new leaves: feeling, questState, declinedTimes, gaveTo, spentWith, sessionMinutes, messagesWith, giverIn, othersInTheZone. An offered quest can also carry an \"offerQuestion\": prose the giver keeps in mind; the giver signals when they believe it is met, and the existing question judge checks it against the conversation before the quest becomes offerable. feeling, questState, declinedTimes, gaveTo and spentWith work everywhere conditions do (beats, complete, fail, start.when), with triggers so beats react when they change; sessionMinutes, messagesWith, giverIn and aloneWithGiver are only meaningful at the moment the player talks with the giver and are allowed in offerWhen only. gaveTo needs a record of item handovers, written wherever items move between inventories. The quest editor's condition builder offers every new leaf with pickers from the world's data, and validation refuses unknown residents, items, quests and zones and leaves used outside where they are allowed. Open for clarification: whether sessionMinutes counts time since the session was created or time actually played (play time isn't tracked today)."

## Clarifications

### Session 2026-09-29

- Q: How does the session-age part count time? → A: It is left out of this feature; offerWhen has no part about how old the session is.
- Q: What is the part about who else is in the giver's zone called, and what shape does it take? → A: One part, othersInTheZone, with two forms: a named resident is in the giver's zone, or no resident other than the giver is.
- Q: Does the player see anything on the world page before a quest is offered? → A: No. offerWhen and offerQuestion stay hidden from the player; the author sees and checks them in the quest editor and in the quest event log.
- Q: Who decides whether offerWhen holds? → A: The giver, alone. During their turn they call the tools they need to look up the current value of each part, compare it with what the quest asks for, and decide in character whether to offer. The offer is never refused because offerWhen doesn't hold.
- Q: When the giver's lookups show a quest isn't ready yet, should they keep it hidden from the player? → A: The giver may hint in character that they have something in mind, if and when their own judgement says it fits, but never names the quest, describes it, or quotes the values they looked up.
- Q: Does an offer left unanswered when the conversation ends count as a decline? → A: Yes. It counts toward declinedTimes and makes the quest's state declined, exactly as an explicit decline does.
- Q: Do out-of-character messages and creator-mode commands count toward messagesWith? → A: Yes. Every message the player sends the named resident counts, whatever it contains.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A giver decides when to offer a quest (Priority: P1)

In a quest that starts when offered, the author writes an offerWhen condition, for example "the player holds the lantern and the giver's trust is at least 3". The giver knows the quest and its offerWhen. When the player talks with them, the giver checks, during their turn, what they need: they call tools to look up the current value of each part ("my trust toward the player is 2"), compare it with what the quest asks for ("I need at least 3"), and decide in character whether to offer the quest now. The condition can use every existing condition part and combine them with all, any and not.

**Why this priority**: This is the core of the feature. It lets a giver hold a quest back until the player has earned it, and keeps that decision in the giver's hands.

**Independent Test**: Write an offered quest whose offerWhen is "the giver's trust toward the player is at least 3". With trust at 2, talk to the giver and confirm they look up their trust during their turn and don't offer the quest. Raise their trust to 3, talk again, and confirm they look it up again and offer the quest.

**Acceptance Scenarios**:

1. **Given** an offered quest with an offerWhen, **When** the player talks with the giver, **Then** the giver's instructions contain the quest, its offerWhen in plain words, and an instruction to look up its parts before deciding to offer.
2. **Given** the giver is deciding whether to offer, **When** they look up a part during their turn, **Then** they get its current value and what the quest asks for (for example "trust is 2; the quest asks for at least 3"), and can look up as many parts as they need before replying.
3. **Given** the giver's lookups show offerWhen doesn't hold, **When** the giver follows their instructions, **Then** they don't offer the quest.
4. **Given** the giver offers the quest, **When** the offer is made, **Then** it is accepted as an offer whether or not offerWhen holds; the existing reasons to refuse an offer (not the giver, quest active or ended, requirements on other quests not met) still apply.
5. **Given** an offered quest without offerWhen, **When** the player talks with the giver, **Then** it behaves as it does today.
6. **Given** an offer is pending or the quest is active, **When** the player talks with the giver, **Then** offerWhen plays no part, and the quest stays in the giver's instructions as it does today.
7. **Given** the giver's lookups show the quest isn't ready, **When** they reply to the player, **Then** they may hint in character that they have something in mind, as their own judgement sees fit, but never name or describe the quest or quote the values they looked up.

---

### User Story 2 - Conditions about feelings, other quests and exchanges (Priority: P1)

The author can write conditions about how a resident feels toward the player (romance, trust or liking at least or at most a value from -10 to 10), about another quest's state (its latest run offered, active, declined or abandoned), about how many times the player declined another quest's offer, about how much of an item the player gave a resident this session, and about how many credits the player paid a resident in total this session. In offerWhen, the giver looks these up like any other part. In beats, completion, failure and a quest's start condition, they are checked as the game goes: when one of these things changes, the quests whose current conditions depend on it react, so a beat can finish the moment a resident's trust reaches the value it needs.

**Why this priority**: These are what make an offer feel earned, and the same parts make beats about relationships and exchanges possible.

**Independent Test**: Write a quest that starts automatically with one beat "the baker's liking is at least 4". Raise the baker's liking in conversation until it reaches 4 and confirm the beat finishes then, with the change recorded as its cause. Write a second beat "the player gave the baker at least 2 bread", hand over one bread, then another, and confirm it finishes after the second.

**Acceptance Scenarios**:

1. **Given** a beat requiring a resident's trust to be at least 3, **When** that resident's trust toward the player reaches 3, **Then** the beat finishes and the event log records the feeling change as its cause.
2. **Given** a condition requiring a resident's romance to be at most -2, **When** the resident's romance is -2 or lower, **Then** the condition holds.
3. **Given** a condition on another quest's latest run being declined, **When** the player declines that quest's offer, **Then** the condition holds; **When** the player later accepts it, **Then** the state is active and the declined condition no longer holds.
4. **Given** a condition requiring that another quest's offer was declined at least 2 times, **When** the player declines it a second time in the session, **Then** the condition holds.
4a. **Given** an offer the player leaves unanswered, **When** the conversation ends and the offer is withdrawn, **Then** it counts as a decline: declinedTimes goes up by one and the quest's state is declined.
5. **Given** a condition requiring the player gave a resident at least 3 of an item this session, **When** the player hands that resident the item in two handovers of 1 and 2, **Then** the condition holds.
6. **Given** a condition requiring the player paid a resident at least 50 credits this session, **When** the player pays them 20 and then 30, **Then** the condition holds.
7. **Given** a resident gives an item back to the player, **When** the gave-to count is read, **Then** only what the player gave that resident counts; what came back doesn't subtract from it.
8. **Given** a quest whose start condition names one of these parts, **When** that part starts to hold, **Then** the quest starts.

---

### User Story 3 - Conditions about the moment of the conversation (Priority: P2)

For offerWhen only, the author can also write conditions about the moment the player talks with the giver: the player has sent the giver at least some messages this session, the giver is in a given zone right now, and, through othersInTheZone, a given resident, or no resident other than the giver, is in the giver's zone. The giver looks these up during their turn like any other part; they are refused anywhere else.

**Why this priority**: They add pacing and privacy to offers ("only once we're alone at the docks"), but offers already work with the parts from User Story 2.

**Independent Test**: Write an offered quest whose offerWhen is "the giver is in the docks zone and no other resident is in the zone". Talk to the giver at the docks while another resident stands there and confirm the giver looks it up and holds the offer back; talk again after that resident leaves and confirm the giver offers it. Then try to put the same condition on a beat and confirm saving is refused.

**Acceptance Scenarios**:

1. **Given** offerWhen asks for at least 5 messages sent to the giver this session, **When** the giver looks it up, **Then** they get how many messages the player has sent them, counting the one they are answering.
2. **Given** offerWhen asks for the giver to be in a zone, **When** the giver looks it up, **Then** they get the zone they are in and the zone asked for.
3. **Given** othersInTheZone asks for a named resident, **When** the giver looks it up, **Then** they are told whether that resident is in their zone.
4. **Given** othersInTheZone asks for no one besides the giver, **When** the giver looks it up, **Then** they are told whether anyone else is in their zone, and who.
5. **Given** a beat, completion, failure or start condition, **When** it names any of these parts, **Then** saving is refused, naming the part and where it was used.

---

### User Story 4 - A question the giver weighs before offering (Priority: P2)

An offered quest can carry an offerQuestion, prose such as "Has the player shown they can keep a secret?". The giver keeps the question in mind as they talk with the player. When the giver believes, in character, that it has been met, they signal it, and the existing judge reads the conversation, with out-of-character and creator-command text removed, and answers yes or no with cited messages and a reason. The giver is told the answer and weighs it, alongside offerWhen, in deciding whether to offer.

**Why this priority**: It covers what tracked conditions can't, such as trust shown in words, but offers already work without it.

**Independent Test**: Write an offered quest with the offerQuestion "Has the player shown they can keep a secret?". Talk to the giver without meeting it and confirm no check runs and the giver doesn't offer the quest. Meet it, confirm the giver signals, the judge answers yes with cited messages, the giver is told, and the giver then offers the quest.

**Acceptance Scenarios**:

1. **Given** an offered quest with an offerQuestion, **When** the player talks with the giver, **Then** the giver's instructions carry the question and tell them to signal it when they believe it is met and to wait for a yes before offering.
2. **Given** the giver signals and the judge answers, **When** the answer arrives, **Then** the giver is told it, and the event log records the signal, answer, cited messages and reason.
3. **Given** the judge answers no or cannot complete the check, **When** it is recorded, **Then** the giver is told, and can signal again later.
4. **Given** the question was met earlier in the session, **When** the player talks with the giver later, **Then** the giver's instructions say it has been met.
5. **Given** any resident other than the giver, **When** they try to signal the offerQuestion, **Then** the signal is refused.

---

### User Story 5 - Writing offer conditions in the quest editor (Priority: P1)

In the quest editor, an offered quest has a place for offerWhen and one for offerQuestion. The condition builder offers every new condition part wherever it is allowed, with residents, items, quests and zones picked from the world's data. Each part's fields only accept values that make sense for it: a feeling kind and a bound between -10 and 10, one of the four quest states, whole numbers of at least 1, a zone from the world's regions, and for othersInTheZone a choice between a named resident and no one besides the giver. Each part shows a short plain description of what it checks, and the parts only meaningful in offerWhen say so. Saving refuses anything that names something not in the world, and any part used where it isn't allowed, and in form mode each problem is shown next to the field it concerns. The same checks apply in JSON mode and in creator mode edits.

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
9. **Given** a new part in form mode, **When** the user fills its fields, **Then** a feeling part offers romance, trust and liking and a bound of at least or at most with values from -10 to 10, a questState part offers only offered, active, declined and abandoned, count and amount fields accept only whole numbers of at least 1, and othersInTheZone offers a named resident or no one besides the giver.
10. **Given** any part in the condition builder, **When** the user looks at it, **Then** it shows a short description of what it checks, and parts allowed only in offerWhen are marked as such.
11. **Given** saving is refused in form mode, **When** the problems are listed, **Then** each one is also shown next to the field it concerns.
12. **Given** an offered quest with offerWhen or an offerQuestion, **When** the user edits it, **Then** the editor states that the giver looks up offerWhen and weighs the offerQuestion's answer, and decides alone whether to offer.

---

### User Story 6 - Seeing how a giver decided (Priority: P2)

The author needs to see why a giver did or didn't offer a quest, to tune offerWhen and the offerQuestion. The session's quest event log shows every offer with what the giver looked up during that turn and the values they got, and whether offerWhen held at that moment. It also shows every new kind of cause readably: feeling changes, item handovers, payments, declined offers, quest state changes, and the giver's signals and the judge's answers on the offerQuestion.

**Why this priority**: Without it, an author whose giver offers too early or never offers can't tell whether the condition or the giver is at fault; but offers already work without it.

**Independent Test**: With an offerWhen of "trust at least 3", get the giver to offer the quest and open the quest event log; confirm the offer shows the trust lookup, its value and whether offerWhen held. Then give the giver an item, raise their trust and decline another quest, and confirm each appears in the event log in plain words.

**Acceptance Scenarios**:

1. **Given** the giver offers a quest with an offerWhen, **When** the user reads the quest event log, **Then** the offer shows each part the giver looked up during that turn with the value they got, and whether offerWhen held at that moment.
2. **Given** the giver offered while offerWhen didn't hold, **When** the user reads the offer in the event log, **Then** it is marked as offered while offerWhen didn't hold, naming the parts that didn't.
3. **Given** a beat finished because of a feeling change, handover, payment, declined offer or quest state change, **When** the user reads the quest event log, **Then** the entry states the cause in plain words, with the resident, item, amount or quest it concerns.
4. **Given** the giver signals an offerQuestion and the judge answers, **When** the user reads the quest event log, **Then** both appear, with the answer, the cited messages and the reason.
5. **Given** the player talks with a giver who hasn't offered a quest, **When** the player looks at the world page, **Then** nothing tells them the quest exists.

---

### Edge Cases

- The giver is not in the same zone as the player when they talk: giverIn and othersInTheZone read the giver's zone.
- A zone named by giverIn or othersInTheZone disappears from a new layout: the quest is listed with the problem, as with existing zone references, and a lookup of that part reports that the zone no longer exists.
- A resident's feeling has never been set: it reads as the starting value residents already use for feelings.
- The player pays a resident through a trade where both sides hand something over: the player's side counts toward gaveTo and spentWith.
- Items or credits move through creator mode: they count, and the event log shows they were moved by the creator.
- A quest reward moves items from a resident to the player: it doesn't count toward gaveTo or spentWith, which only count what the player hands over.
- An offer is left unanswered when the conversation ends: it is withdrawn as today, the quest stays available to be offered again, and it counts as a decline for declinedTimes and questState.
- questState names a repeatable quest: it reads its latest run; declinedTimes counts every declined offer of that quest in the session, across runs.
- Several offered quests share a giver: the giver knows each one with its own offerWhen and offerQuestion, and looks up and decides on each separately.
- Something changes mid-conversation (another resident walks into the zone): the giver's next lookup reads the new value; values are never cached between turns.
- The giver doesn't look anything up and offers anyway: the offer stands, and the event log shows it was made without lookups and whether offerWhen held.
- The giver looks up a part that isn't in any offerWhen of their quests: the lookup is refused.
- A quest's definition is edited to add or change offerWhen while a session is playing it: the giver's instructions and lookups follow the new definition from their next turn.
- The offerQuestion is changed after it was met: the earlier yes no longer counts, since it answered a different question.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: A quest that starts when offered MUST be able to carry an optional offerWhen condition and an optional offerQuestion; a quest that starts any other way MUST NOT carry either.
- **FR-002**: When the player talks with a giver, the giver's instructions MUST contain each quest they can offer (under the existing rules) with its offerWhen in plain words and its offerQuestion, and MUST tell the giver to look up offerWhen's parts and weigh the offerQuestion before deciding to offer. Before offering, the giver MUST be told they may hint in character that they have something in mind, at their own discretion, and MUST NOT name or describe the quest, its conditions or the values they looked up.
- **FR-003**: During their turn, the giver MUST be able to look up, through tools, the current value of any part of the offerWhen of a quest they give, getting the value read and what the quest asks for, as many times and for as many parts as they need before replying. Lookups MUST read the state at the moment they are made and MUST be refused for parts outside their quests' offerWhen.
- **FR-004**: The giver MUST decide alone whether to offer. An offer MUST NOT be refused because offerWhen doesn't hold or the offerQuestion isn't met; the existing reasons to refuse an offer still apply.
- **FR-005**: A pending offer and an active quest MUST NOT be affected by offerWhen or offerQuestion.
- **FR-006**: offerWhen MUST accept all, any and not, and every existing condition part.
- **FR-007**: Conditions MUST support a feeling part: a named resident's romance, trust or liking toward the player is at least and/or at most a value from -10 to 10.
- **FR-008**: Conditions MUST support a questState part: another quest's latest run is offered (an offer is pending), active, declined (its latest offer was declined, or withdrawn unanswered when the conversation ended, and it hasn't started since), or abandoned.
- **FR-009**: Conditions MUST support a declinedTimes part: the player declined a named quest's offer at least a number of times in the session. An offer withdrawn unanswered when the conversation ends MUST count as a decline.
- **FR-010**: Conditions MUST support a gaveTo part: the player gave a named resident at least a quantity of a named item in the session, summed over every handover.
- **FR-011**: Conditions MUST support a spentWith part: the player paid a named resident at least an amount of credits in total in the session.
- **FR-012**: The feeling, questState, declinedTimes, gaveTo and spentWith parts MUST be allowed in every condition (offerWhen, beats, completion, failure and start). Outside offerWhen, a change to what each depends on MUST be announced as it happens, and quests MUST react to it as they do to existing announcements, recording it as the cause.
- **FR-013**: Every time items or credits move from the player to a resident, the handover MUST be recorded per session with the resident, the items and quantities, and the credits, including moves made through creator mode, which are marked as such.
- **FR-014**: offerWhen MUST additionally support: messagesWith (the player has sent a named resident at least a number of messages in the session, counting every message whatever it contains, out-of-character and creator-command messages included); giverIn (the giver is in a named zone right now); and othersInTheZone, in one of two forms: a named resident is in the giver's zone, or no resident other than the giver is in the giver's zone. These parts MUST be allowed only in offerWhen.
- **FR-015**: The giver MUST carry the offerQuestion in their instructions and be able to signal it in a conversation with the player. A signal MUST start the existing judged check, with out-of-character and creator-command text removed, and the same rules for citations, failures and one running check per question. The giver MUST be told the answer.
- **FR-016**: A yes to an offerQuestion MUST stay met for the rest of the session, until the question's text is changed, and the giver's instructions MUST say so; every signal, answer, citation and reason MUST be recorded in the quest's event log.
- **FR-017**: Saving a quest, in form mode, JSON mode or through creator mode, MUST refuse offerWhen or offerQuestion on a quest that doesn't start when offered, any new part naming a resident, item, quest or zone not in the world, feeling values outside -10 to 10, counts, quantities or amounts below 1, and any part of FR-014 used outside offerWhen, listing every problem with where it is.
- **FR-018**: Deleting a resident, item or quest named by a new part MUST be refused, naming the quests that use it; zones named by the new parts MUST be treated as existing zone references are.
- **FR-019**: The quest editor's form mode MUST offer offerWhen and offerQuestion on offered quests, offer each new part in the condition builder only where it is allowed, and pick residents, items, quests and zones from the world's data; switching between form and JSON MUST lose nothing.
- **FR-020**: Each part's fields in form mode MUST accept only valid values for it (feeling kind and a bound from -10 to 10, the four quest states, whole numbers of at least 1, a zone of the world, and the two forms of othersInTheZone), and each part MUST show a short description of what it checks, marking the parts allowed only in offerWhen. The editor MUST state that the giver decides alone whether to offer.
- **FR-021**: When saving is refused in form mode, each problem MUST also be shown next to the field it concerns.
- **FR-022**: Each offer of a quest with offerWhen MUST be recorded in its event log with every lookup the giver made during that turn and the value they got, and whether offerWhen held at that moment, naming the parts that didn't.
- **FR-023**: The quest event log MUST show each new kind of cause (feeling change, item handover, payment, declined offer, quest state change, and offerQuestion signals and answers) in plain words, naming the resident, item, amount or quest it concerns.
- **FR-024**: The world page MUST NOT reveal to the player a quest that hasn't been offered, nor its offerWhen or offerQuestion; offers keep appearing through the existing request and card.
- **FR-025**: New editor controls MUST follow the app's current theme and match the existing quest editor.

### Key Entities

- **Offer condition (offerWhen)**: A condition on an offered quest that the giver looks up during their turn and weighs to decide whether to offer the quest.
- **Offer question (offerQuestion)**: Prose on an offered quest that the giver keeps in mind and signals when they believe it is met; the judge's answer is given to the giver, and a yes stays met for the session.
- **Lookup**: One check the giver makes during their turn of a part of offerWhen: the part, the value read and what the quest asks for.
- **Handover record**: One movement of items or credits from the player to a resident in a session: the resident, the items and quantities, the credits, when it happened, and whether the creator made it.
- **New condition parts**: feeling, questState, declinedTimes, gaveTo and spentWith, usable everywhere; messagesWith, giverIn and othersInTheZone, usable in offerWhen only.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On a set of scripted conversations with a known offerWhen state, givers look up the parts before offering and offer only when offerWhen holds in at least 90% of cases.
- **SC-002**: A beat depending on a feeling, a quest's state, a decline count, a handover or a payment finishes within 2 seconds of the change that makes it hold.
- **SC-003**: 100% of item and credit movements from the player to a resident appear in the handover record.
- **SC-004**: 100% of quests naming something missing from the world, or using a part where it isn't allowed, are refused on save with every problem listed.
- **SC-005**: 0 judged checks for an offerQuestion run without a signal from the quest's giver.
- **SC-006**: A user can add an offerWhen with two parts and an offerQuestion to an offered quest in form mode in under 3 minutes.
- **SC-007**: 100% of offers of quests with offerWhen show, in the event log, the giver's lookups and whether offerWhen held, so an author can tell in under 1 minute why a giver offered when they did.
- **SC-008**: 100% of invalid values entered in form mode are rejected at the field before saving, or shown next to the field on save.

## Assumptions

- "offerWhen", "offerQuestion" and the part names feeling, questState, declinedTimes, gaveTo, spentWith, messagesWith, giverIn and othersInTheZone come from the feature description; any UI label or tool name for them is proposed in the plan and used only after approval.
- Completed and failed outcomes of other quests stay covered by the existing quest requirements and ending flags, so questState covers offered, active, declined and abandoned.
- The existing rules for when a giver can offer a quest (being its giver, the quest not active or ended, its requirements on other quests met) stay enforced by the game; offerWhen and offerQuestion are the giver's to weigh.
- Feelings, the question judge, offers, declines, item and credit handovers, zones and residents' positions already exist as built in earlier features; the handover record and the giver's lookups are new.
- Handovers are counted from the session's handover record, so items and credits the player handed over before this feature existed don't count.
- "This session" means the current play session of the world; counts don't carry over to other sessions.
- Only messages the player sends to the named resident count toward messagesWith, including out-of-character and creator-command messages; messages from the resident, narration and other residents' messages don't.
- A part about how long the session has lasted is out of scope for this feature.
- The giver is always a resident, as quests that start when offered already require.
