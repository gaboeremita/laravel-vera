# Feature Specification: Facts and Reveal Safeguards

**Feature Branch**: `claude/cool-hopper-uqk44z`

**Created**: 2026-09-29

**Status**: Draft

**Input**: User description: "Facts and reveal safeguards for worlds (feature 2 of 3 toward quests). Characters hold secrets (facts) and decide in character whether to reveal them. The player learns facts and relays them to other characters in their own words, with no dialog options. Conditions are plain-language prose judged by the LLM from the full context; code only tracks resources and recorded state. A holder's prompt carries only a fact's topic and disclosure prose; the content comes back only through a reveal, which a separate model reviews against the prose. Every reveal attempt is logged. A character who can act on a fact gets its content only once the player knows it, and their acknowledgement is refused unless the player really does. Out-of-character turns supersede the safeguards. Creator mode is activated by a password verified by the server and gives the character every tool, unscoped, plus admin tools; its actions count and are logged as the creator's."

## Clarifications

### Session 2026-09-29

- Q: How does creator mode end? → A: It lasts for the conversation it was activated in; conversations don't end, so it stays active there, and every other conversation needs its own activation.
- Q: How are facts authored and attached to holders? → A: Facts belong to residents: they are added on a resident in the region configuration, where residents are chosen.
- Q: Do facts spread between residents in their own conversations? → A: Not in this feature; residents reveal facts only to the player.
- Q: Build the check on OOC-turn replies now? → A: No; on OOC turns a fact counts as revealed only when the character reveals it.
- Q: In a world, does activating creator mode with one resident turn it on for the whole play session or only in that resident's conversation? → A: Only in that resident's conversation; every other resident needs their own activation.
- Q: Before the player learns a fact, do the residents who can act on it know it exists? → A: Yes; they know its topic from the start and that they want to find out, never its content, which they get once the player knows the fact.
- Q: What does the list of learned facts show for each fact? → A: Its topic, its source, and a short summary of what the player was actually told, written when the fact is learned.
- Q: When the player learns a fact from somewhere other than its holder, how does the holder treat it? → A: The holder gets the content and a note on how the player found out, and talks about it once the player brings it up.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Give residents facts (Priority: P1)

In a region's configuration, where the user chooses the region's residents, the user adds facts to a resident. Each fact has a topic (what the fact is about, which is all the resident is shown), its content (the secret itself) and plain-language disclosure prose: "speaks of it only while grieving", "only in the chapel", "only to someone they'd trust with their life", "for 50 credits". The user can also pick other residents of the world who can act on the fact once the player tells them.

**Why this priority**: Nothing else in the feature has anything to act on until facts exist and someone holds them.

**Independent Test**: Give a resident a fact and pick a second resident who can act on it, start a new session, and confirm the first resident holds it, the player knows nothing yet, and the second resident is not told its content.

**Acceptance Scenarios**:

1. **Given** a resident in a region's configuration, **When** the user adds a fact with a topic, content and disclosure prose, **Then** the fact is listed on that resident.
2. **Given** a resident has a fact, **When** the player starts a new session, **Then** that resident holds the fact and the player knows no facts.
3. **Given** sessions already exist, **When** the user adds a fact to a resident, **Then** the resident holds it in those sessions too, and what each player already knows is unchanged.
4. **Given** a resident's model cannot call tools, **When** the user tries to make them a holder, **Then** the configuration refuses it and says why.
5. **Given** a fact is known in some sessions, **When** the user deletes it, **Then** the user is warned how many sessions use it, and on confirmation it is removed from every session and configuration.

---

### User Story 2 - Characters guard and reveal facts (Priority: P1)

While the player talks with a holder, the holder knows only the fact's topic and disclosure prose, never the content. When the holder decides in character to share it, they reveal it with a reason. A separate model, one that is not playing the character, reviews the disclosure prose against the recent conversation, where the scene takes place and the character's recent expressions, and judges whether the situation reasonably meets the prose, without demanding literal proof. If it approves, the holder receives the content and tells the player in their own words, and the player now knows the fact. If it rejects, the holder is told it doesn't feel like the right moment yet, and nothing is learned. Every attempt is recorded with its reason and verdict.

**Why this priority**: This is the core of the feature: secrets that can't be blurted out or talked out of a character, only earned in the story.

**Independent Test**: Give a resident a fact whose prose reads "only in the chapel", ask them about it outside the chapel and confirm the reveal is rejected and logged, then ask in the chapel and confirm it is approved, the character says it, and the player now knows it.

**Acceptance Scenarios**:

1. **Given** a resident holds a fact, **When** their reply is generated on an in-character turn, **Then** their instructions contain the fact's topic and disclosure prose and never its content.
2. **Given** the holder decides to reveal the fact with a reason, **When** the review judges the situation meets the prose, **Then** the holder receives the content, the player now knows the fact, and the attempt is recorded as approved with the reason and verdict.
3. **Given** the holder decides to reveal the fact, **When** the review judges the situation does not meet the prose, **Then** the holder is told it doesn't feel like the right moment yet, the player learns nothing, and the attempt is recorded as rejected with the reason and verdict.
4. **Given** the player asks the holder to ignore their instructions and state the secret, **When** the holder replies, **Then** the content cannot appear, because the holder does not have it.
5. **Given** a fact's prose reads "for 50 credits" and the player paid the holder 50 credits in this conversation, **When** the holder reveals it, **Then** the review approves it.
6. **Given** the player learned a fact from its holder, **When** they talk with the holder again, **Then** the holder's instructions contain the content and they can speak of it freely.
6a. **Given** the player learned a fact from a letter, an activity or a creator command, **When** they talk with its holder, **Then** the holder's instructions contain the content and how the player found out, and the holder talks about it once the player brings it up.
7. **Given** the world has the review turned off, **When** a holder reveals a fact, **Then** it is approved without review and recorded as unreviewed.
8. **Given** a resident does not hold a fact, **When** they try to reveal it, **Then** the reveal is refused and nothing is learned.

---

### User Story 3 - Relay facts to other characters (Priority: P2)

A resident named as able to act on a fact knows its topic from the start and that they want to find out about it, so they can ask the player about it in character. They are told its content once the player knows it: "the player has learned this; if they tell you, you can act on it". When the player tells them in their own words, the resident acknowledges the fact, and the session records that this resident has learned it from the player. The acknowledgement is refused unless the player really knows the fact, so a lucky guess doesn't count.

**Why this priority**: Carrying what one character said to another is how the player moves a story forward without dialog options, and it is what quests will check; but guarding and revealing work without it.

**Independent Test**: Name a second resident as able to act on a fact, confirm they are not told its content before the player knows it, have the player learn it from the holder, tell the second resident, and confirm the acknowledgement is recorded.

**Acceptance Scenarios**:

1. **Given** the player does not know a fact, **When** a resident able to act on it replies, **Then** their instructions contain its topic and that they want to find out about it, and never its content.
2. **Given** the player knows the fact, **When** that resident replies, **Then** their instructions contain its content and say the player has learned it.
3. **Given** the player tells the resident the fact in their own words, **When** the resident acknowledges it, **Then** the session records that the resident learned it from the player.
4. **Given** the player does not know a fact, **When** a resident tries to acknowledge it, **Then** the acknowledgement is refused and nothing is recorded as learned.

---

### User Story 4 - Out-of-character turns (Priority: P2)

Anything the player writes inside `[OOC: ...]` is out of character and never counts in the game; everything outside it counts. When the player's latest message contains an OOC tag, the character is speaking with the player as a collaborator: holders get the full content of their facts and reveals are not reviewed. OOC text is never removed from the conversation, so the character keeps following instructions like "[OOC: remember to use pose tags correctly]"; once the player has gone OOC about a secret, that conversation is no longer protected.

**Why this priority**: The player needs a way to step outside the story and discuss it with the character, but the game is fully playable without it.

**Independent Test**: Send an in-character message and confirm the holder's instructions carry only the topic; send one with an OOC tag and confirm they carry the content and a reveal is approved without review.

**Acceptance Scenarios**:

1. **Given** the player's latest message contains `[OOC: ...]`, **When** a holder replies, **Then** their instructions contain the content of their facts, and a reveal is approved without review and recorded as made on an OOC turn.
2. **Given** a reveal is reviewed, **When** the review reads the conversation, **Then** every OOC span has been removed from what it reads.
3. **Given** the player wrote an OOC instruction earlier in the conversation, **When** the character replies later, **Then** the instruction is still in the conversation the character sees.
4. **Given** a resident is deciding what to do on their own or talking with another resident, **When** their instructions are built, **Then** no OOC bypass applies.

---

### User Story 5 - Creator mode, verified by the server (Priority: P2)

The user sets a creator password in their settings; it is stored so that it cannot be read back. Typing `[creator mode: "<password>"]` in a conversation activates creator mode only if the server verifies the password. The password is never part of the delivered interface, the stored conversation or what any character sees; the password currently written into the interface's code is removed. The character's creator mode instructions apply only while creator mode is active. Creator mode stays active in the conversation it was activated in; every other conversation needs its own activation.

**Why this priority**: Today the password ships inside the interface and in the repository, readable by anyone; that leaks it in the delivered interface and keeps personal data in the repository, and creator commands depend on the server knowing it.

**Independent Test**: Set a password, type the activation with a wrong password and confirm nothing activates, type it with the right one and confirm creator mode is active, and confirm the password appears nowhere in the stored conversation or the delivered interface.

**Acceptance Scenarios**:

1. **Given** the user set a creator password, **When** they type the activation with that password, **Then** creator mode becomes active and the player is told so.
2. **Given** the user types the activation with a wrong password, **When** the message is sent, **Then** creator mode stays inactive and the player is told it did not activate.
3. **Given** any activation attempt, **When** the message is stored and sent to the character, **Then** the password has been removed from it.
4. **Given** creator mode was activated in a conversation, **When** the player writes there later, even after leaving and coming back, **Then** it is still active; **When** the player writes in another conversation, including with another resident of the same world, **Then** it is not.
5. **Given** the user has not set a creator password, **When** they type an activation, **Then** creator mode stays inactive and the player is told to set one in their settings.
6. **Given** creator mode is inactive, **When** the character's instructions are built, **Then** they do not include the character's creator mode instructions.

---

### User Story 6 - Creator commands (Priority: P2)

While creator mode is active, the player gives commands inside `[creator mode: <instruction>]` tags. On such turns the character has every tool, unscoped and without review: reveal any fact, give any item or credits with or without holding them, plus admin tools to mark facts known or unknown for the player, and to grant or remove items and credits for any holder. What they do counts in the game and is recorded as done by the creator.

**Why this priority**: Creator commands make testing and steering a world fast, but play works without them.

**Independent Test**: Activate creator mode, command the character to make the player know a fact and to grant the player 100 credits, and confirm both happened and are recorded as done by the creator.

**Acceptance Scenarios**:

1. **Given** creator mode is active, **When** the player commands the character to reveal a fact they don't hold, **Then** the player knows the fact and the reveal is recorded as done by the creator.
2. **Given** creator mode is active, **When** the player commands a grant of 100 credits to the player, **Then** the balance grows by 100 and the credit history records it as done by the creator.
3. **Given** creator mode is active, **When** the player commands that a fact become unknown, **Then** the player no longer knows it.
4. **Given** creator mode is inactive, **When** the player writes a `[creator mode: <instruction>]` tag, **Then** the character gets no extra tools.

---

### User Story 7 - What the player has learned (Priority: P3)

During play, the player can open a view listing the facts they have learned in the session, each with its topic, who or what they learned it from, and a short summary of what they were actually told, written when they learned it.

**Why this priority**: Helps the player keep track of the story, but learning and relaying work without it.

**Independent Test**: Learn a fact, open the view, and confirm it lists the fact with a summary of what the holder said and the holder's name.

**Acceptance Scenarios**:

1. **Given** the player learned a fact from a resident, **When** they open the view, **Then** the fact appears with its topic, that resident's name, and a summary of what the resident told them, including only what they actually said.
2. **Given** the player learned a fact by examining an item or using an activity, **When** they open the view, **Then** the summary reflects the narration they read.
3. **Given** the player has learned nothing, **When** they open the view, **Then** it shows that nothing has been learned yet.
4. **Given** the player learns a fact, **When** the reveal is approved, **Then** the player is notified that they learned something.

---

### User Story 8 - Items and activities that reveal facts (Priority: P3)

An item's contents or an activity's outcome can be linked to a fact. When the player examines the item, or uses the activity and its requirement is judged met, the player now knows the fact.

**Why this priority**: Lets secrets be found in the world as well as in conversation, but facts are fully usable without it.

**Independent Test**: Link a letter's contents to a fact, examine the letter, and confirm the player knows the fact.

**Acceptance Scenarios**:

1. **Given** a letter's contents are linked to a fact, **When** the player examines it, **Then** the player knows the fact and it is recorded as learned from the letter.
2. **Given** an activity's outcome is linked to a fact, **When** its requirement is judged not met, **Then** the player does not learn the fact.

---

### User Story 9 - Review the reveal log (Priority: P3)

The user can read a session's reveal log: every attempt with the holder, the fact, the reason, the verdict, whether it was approved, and whether it happened in character, on an OOC turn or by the creator.

**Why this priority**: Needed to tune disclosure prose and the review, and later to judge quest endings, but play works without a view of it.

**Independent Test**: Make one approved and one rejected reveal, open the log, and confirm both appear in order with reasons and verdicts.

**Acceptance Scenarios**:

1. **Given** a session with reveal attempts, **When** the user opens its reveal log, **Then** each attempt appears in order with holder, fact, reason, verdict and outcome.
2. **Given** a session with no attempts, **When** the user opens the log, **Then** it shows that nothing has happened yet.

---

### Edge Cases

- Two residents hold facts about the same thing: each is its own fact; learning one teaches the player nothing about the other.
- The review cannot be completed (the model fails or doesn't answer): the reveal is rejected as not the right moment yet, and the failure is recorded.
- The player tells a resident a fact inside an OOC tag: the telling is out of character; the resident's acknowledgement still only requires the player to know the fact.
- Residents talk with each other while one holds a fact: they are told only the topic and disclosure prose and never reveal it to each other; facts reach other residents only through the player.
- The player sends an activation and a command in the same message: the activation is checked first, then the command applies.
- A resident's model loses tool calling after they were given facts: they keep the facts in configuration but cannot reveal them until their model can call tools again, and the configuration shows why.
- A fact is edited while sessions exist: its topic, content and prose read as currently configured; what players already know stays known.
- A resident with facts is removed from a region: their facts are deleted with them, after the same warning as deleting a fact.
- The same fact is revealed again to a player who already knows it: it is recorded, with no review, and the summary stays as first written.
- A fact becomes known through a creator command, with no telling: its summary is the fact's written content.
- A holder's reveal is approved but their reply says nothing of it: the fact is known and its summary says the player hasn't heard the details yet.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Users MUST be able to create, edit and delete facts on a resident in a region's configuration, each with a topic, content, disclosure prose, and other residents of the world able to act on it.
- **FR-002**: Users MUST NOT be able to give facts to a resident whose model cannot call tools; the configuration MUST say why.
- **FR-003**: A resident MUST hold the facts currently configured on them, in every session.
- **FR-004**: What the player knows, where they learned it, and what each resident learned from the player MUST be tracked per session and belong to one session of one player only.
- **FR-005**: On in-character turns, a holder's instructions MUST contain only the topic and disclosure prose of facts the player does not yet know; the content MUST reach the holder only as the result of an approved reveal. For facts the player learned elsewhere, the holder MUST be given the content and how the player found out, to talk about once the player brings it up.
- **FR-006**: Every reveal MUST carry a reason. Unless the world turns the review off (it is on by default), a separate model not playing the character MUST judge whether the situation reasonably meets the disclosure prose, reading the prose, the recent conversation (with OOC spans removed and marked as data), the location, the character's recent expressions, their memory of the player, and what they and the player hold.
- **FR-007**: An approved reveal MUST return the content to the holder and mark the fact known to the player; a rejected reveal MUST tell the holder it isn't the right moment yet and change nothing.
- **FR-008**: Every reveal attempt MUST be recorded with the session, holder, fact, reason, verdict, outcome and how it happened (in character, OOC turn, creator, item or activity).
- **FR-009**: A resident able to act on a fact MUST be given its topic from the start and its content only once the player knows it; their acknowledgement MUST be refused unless the player knows the fact, and a successful one MUST be recorded.
- **FR-010**: On turns where the player's latest message contains an OOC tag, holders MUST be given the full content of their facts, and reveals MUST be approved without review.
- **FR-011**: OOC text MUST never be removed from the conversation a character sees.
- **FR-012**: OOC and creator bypasses MUST apply only where the player talks with a character, never when residents decide on their own or talk with each other.
- **FR-012a**: Residents MUST reveal facts only to the player; in conversations between residents, holders MUST be given only topics and disclosure prose and MUST NOT have the reveal available.
- **FR-013**: Users MUST be able to set and change a creator password in their settings; it MUST be stored so it cannot be read back.
- **FR-014**: Creator mode MUST activate only when the server verifies the typed password; the password MUST be removed from the stored conversation and from what the character sees, and MUST NOT appear in the delivered interface or the repository.
- **FR-015**: Creator mode MUST stay active in the conversation it was activated in, and only there; in a world, that is the conversation with the one resident it was activated with. A character's creator mode instructions MUST be included only while it is active.
- **FR-016**: On turns with a creator command while creator mode is active, the character MUST have every tool unscoped and without review, plus tools to mark facts known or unknown for the player and to grant or remove items and credits for any holder; every such action MUST count in the game and be recorded as done by the creator.
- **FR-017**: The player MUST be able to see the facts they learned in the session, each with its topic, source, and a summary of what they were actually told, written when they learned it from the character's reply or the narration; the player MUST be notified when they learn one.
- **FR-018**: Users MUST be able to link an item's contents or an activity's outcome to a fact; examining the item, or using the activity with its requirement judged met, MUST mark the fact known to the player.
- **FR-019**: Users MUST be able to read a session's reveal log.
- **FR-020**: Every new screen, panel and control MUST follow the app's current theme and styling and look polished, consistent with the existing HUD and configuration screens.

### Key Entities

- **Fact**: A secret held by one resident: a topic, its content, disclosure prose, and the other residents able to act on it.
- **Session fact state**: Per session: which facts the player knows, where they learned each and a summary of what they were told, and which residents learned which facts from the player.
- **Reveal attempt**: One recorded attempt: session, holder, fact, reason, verdict, approved or not, how it happened, time.
- **Creator password**: A user's secret for creator mode, stored so it can be verified but not read back.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can give a resident a fact with disclosure prose in under 2 minutes.
- **SC-002**: In 100% of in-character turns, no holder's instructions contain the content of a fact the player doesn't know.
- **SC-003**: 100% of reveal attempts appear in the reveal log with reason and verdict.
- **SC-004**: 100% of acknowledgements of facts the player doesn't know are refused.
- **SC-005**: On a set of scripted scenes with an expected verdict, the review agrees with the expected verdict in at least 90% of cases.
- **SC-006**: A reviewed reveal adds under 5 seconds to the character's reply.
- **SC-007**: The creator password appears in 0 places in the delivered interface, the repository, stored conversations and character instructions.

## Assumptions

- "Fact" is the working term for a secret; UI labels and tool names are proposed in the plan and used only after approval.
- The review uses the world's narrator model, falling back to the default model like narration does.
- The review can be turned off per world; it is on by default.
- Creator mode can be activated in any conversation with an assistant in the app, in chats and in worlds; Discord conversations never have it, since a password typed there is visible to the channel. Fact, item and credit tools apply only inside a world session.
- Creator grants can give any defined item from nothing; creating new item definitions is out of scope.
- The OOC tag grammar stays as the existing parsers recognise it, with no extra edge-case handling.
- On OOC turns, a secret the character says outside the tag counts as revealed only when the character also reveals it; a check that reads OOC-turn replies for secrets is out of scope.
- Facts spreading between residents in their own conversations is out of scope.
- Creator mode state lives with the conversation; how a player leaves it, if ever, is out of scope.
- Quests, beats and endings are feature 3; this feature only records what they will read.
- Worlds, regions, residents, sessions, items, activities and narration already exist as built in earlier features.
