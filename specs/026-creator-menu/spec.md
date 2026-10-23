# Feature Specification: Creator Menu and HUD Theming

**Feature Branch**: `181-creator-menu`

**Created**: 2026-10-14

**Status**: Draft

**Input**: User description: "A creator mode for the world: a menu the player opens with T inside a world session to do creator things. Move around: go to any zone in any region (followers come along), go to any passage or the world spawn, fly through walls, walk faster. Residents: summon any resident to where the player is, send a resident to any zone in any region, set their sentiments toward the player, freeze or unfreeze them, send them back to their home spot, see what they are thinking and planning. Quests: start any quest, finish it as completed or failed, reopen it, finish or undo any beat, set or clear any flag. Items and credits: add or take credits, grab any item of the world into the inventory, open any object's inventory from any distance and take or put items, give items to or take items from any resident. World state: unlock a zone by making any blocking object passable and lock it again, reveal or forget any fact, use any object activity from anywhere without its costs or terms, show zone outlines and names on the ground. The menu is unlocked with the creator password set in Settings, asked once per world session."

## Clarifications

### Session 2026-10-14

- Q: What does grabbing any object give the player? → A: Both: any item of the world goes into the player's inventory, and any object's inventory can be opened from any distance to take what's inside. Placed objects themselves never become items.
- Q: Is making the existing HUD follow the theme part of this feature? → A: Yes. The whole HUD (inventory, quest log, pause screen, zone title card, location readout, passage confirmation, control hints and the Creator menu) uses shared classes that each theme styles, all in this feature.
- Q: Where does the creator password live? → A: On the world, set on its create and edit page; it unlocks the Creator menu and creator mode in that world's conversations. Outside any world, the character decides from its own prompt sections, which are always sent.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Open and unlock the Creator menu (Priority: P1)

Inside a world session, the player presses T. The first time in that session, the Creator menu asks for the world's creator password, set on the world's create or edit page. With the right password the menu opens and stays unlocked for the rest of that session, including after leaving and coming back. While the menu is open the world is paused. Pressing T again, or closing the menu, resumes play.

**Why this priority**: Every other power lives in this menu; nothing else works without it.

**Independent Test**: Set a creator password on the world's edit page, enter a world session, press T, type the password and confirm the menu opens. Close it, press T again and confirm it opens without asking. Reload the page and confirm it still opens without asking. Open a different session and confirm it asks again.

**Acceptance Scenarios**:

1. **Given** a session that was never unlocked, **When** the player presses T, **Then** the menu asks for the creator password and shows no powers until it is given.
2. **Given** the password prompt, **When** the player types a wrong password, **Then** the menu stays locked and says the password is wrong.
2a. **Given** the password prompt, **When** the player types a password containing T, **Then** each T goes into the password and the menu stays open.
3. **Given** the world has no creator password, **When** the player unlocks, **Then** the menu says a creator password must first be set for this world.
4. **Given** an unlocked session, **When** the player presses T, **Then** the menu opens at once and the world pauses; **When** they press T again, **Then** the menu closes and play resumes.
5. **Given** the player is typing in a chat or a text field, **When** they type the letter T, **Then** the menu does not open.
6. **Given** a session unlocked from the world, **When** the player chats with a resident, **Then** creator mode in that conversation is unchanged; unlocking in a conversation likewise leaves the menu locked.

---

### User Story 2 - Move anywhere (Priority: P1)

From the menu the player picks a region and one of its zones, shown nested the way zones sit inside each other, and lands inside that zone on ground they can walk on. If the zone is in another region, the player travels there and the residents following them come along and stand beside them, as they do through a passage. The player can also jump to any passage of any region or to the world spawn. Two toggles change how the player moves: flying, which ignores gravity and walls, and faster walking.

**Why this priority**: Getting to any place without walking is the first thing a creator needs to test a world.

**Independent Test**: In a region with nested zones, open the menu, pick a zone in the same region and confirm the player stands inside it. Pick a zone in another region while a resident follows the player and confirm both arrive there. Turn flying on, move through a wall, turn it off and confirm the player lands on the ground.

**Acceptance Scenarios**:

1. **Given** a zone in the region the player is in, **When** the player picks it, **Then** the player stands on walkable ground inside that zone's outline.
2. **Given** a zone in another region and a resident following the player, **When** the player picks it, **Then** the session moves to that region, the player stands inside the zone, and the follower stands beside them.
3. **Given** a zone whose only walkable ground lies away from its middle, **When** the player picks it, **Then** they land on the walkable ground closest to the zone's middle that is still inside the zone.
4. **Given** a zone with quest beats that react to entering it, **When** the player jumps into it, **Then** those beats react exactly as if the player had walked in, and entering a new region counts as entering that region.
5. **Given** any passage of any region, **When** the player picks it, **Then** they arrive at that passage's arrival point, facing its direction.
6. **Given** a world with a spawn point, **When** the player picks the spawn, **Then** they arrive there.
7. **Given** flying is on, **When** the player moves, **Then** they pass through walls and objects and can rise and descend freely; **When** flying is turned off, **Then** they settle on the ground below them.
8. **Given** faster walking is on, **When** the player walks, **Then** they cover ground at several times the normal pace; turning it off restores the normal pace.

---

### User Story 3 - Command residents (Priority: P1)

The menu lists every resident of the world and where each one is. The player can summon a resident to stand beside them, from any region. They can send a resident to any zone of any region. They can freeze a resident so they stop deciding and moving on their own, and unfreeze them. They can send a resident back to their home spot. For any resident they can see what that resident is thinking and planning right now.

**Why this priority**: Residents being in the right place is what most scenes and quests depend on.

**Independent Test**: Summon a resident from another region and confirm they appear beside the player and can be talked to. Freeze them, wait, and confirm they don't move or start activities; unfreeze them and confirm they carry on.

**Acceptance Scenarios**:

1. **Given** a resident in another region, **When** the player summons them, **Then** they stand beside the player, standing and doing nothing, and the next conversation with them happens here.
2. **Given** a resident sitting or busy with an activity, **When** the player summons them, **Then** they leave the spot and the activity and stand beside the player.
3. **Given** a resident and a zone in any region, **When** the player sends the resident there, **Then** the resident stands on walkable ground inside that zone, in that region, whether or not their zone access would let them go in on their own.
4. **Given** a frozen resident, **When** time passes, **Then** they make no decisions, walk nowhere and start nothing on their own; the player can still talk to them, and they still answer.
5. **Given** a frozen resident, **When** the session is reloaded, **Then** they are still frozen until the player unfreezes them.
6. **Given** a resident away from home, **When** the player sends them home, **Then** they return to their home region and home spot.
7. **Given** a resident, **When** the player looks at what they are thinking and planning, **Then** the menu shows their latest thought and their current plan, or says they have none.

---

### User Story 4 - Change how residents feel (Priority: P2)

For any resident, the menu shows how they feel toward the player in this session, one value from -10 to 10 for each sentiment the world defines, and the player can set each value.

**Why this priority**: Feelings gate quest offers and beats; setting them directly is the fastest way to test those.

**Independent Test**: Set a resident's trust to 5, chat with them and confirm their prompt reflects trust 5. Confirm a beat that requires trust of at least 5 finishes when the value is set.

**Acceptance Scenarios**:

1. **Given** a world with sentiments defined, **When** the player opens a resident's feelings, **Then** every sentiment shows with its current value.
2. **Given** a sentiment, **When** the player sets it to a value from -10 to 10, **Then** the resident holds that value in this session from then on.
3. **Given** a beat or condition depending on that sentiment, **When** the value changes, **Then** it reacts as it does when the value changes in conversation.
4. **Given** a value outside -10 to 10, **When** the player tries to save it, **Then** it is refused.

---

### User Story 5 - Control quests (Priority: P2)

The menu lists every quest of the world with its state in this session. The player can start any quest, skipping its offer conditions; finish one as completed or failed, after which its ending is written; reopen one, clearing its latest run back to the start while its log keeps what happened; finish or undo any beat; and set or clear any flag of its latest run. Each change is logged as made by the creator.

**Why this priority**: Testing a long quest without replaying it from the start.

**Independent Test**: Start a quest from the menu, finish its second beat, confirm the first beat stays as it was and the log shows both changes as made by the creator. Fail the quest, confirm its ending is written, then reopen it and confirm it is back to the start with the log intact.

**Acceptance Scenarios**:

1. **Given** a quest not yet started, **When** the player starts it, **Then** it becomes active whatever its offer or start conditions say.
2. **Given** an active quest, **When** the player finishes it as completed or failed, **Then** it ends that way and its ending is written.
3. **Given** a quest that ended, **When** the player reopens it, **Then** its latest run is back to how it starts and its log keeps every earlier event.
4. **Given** an active quest, **When** the player undoes a finished beat, **Then** that beat and every finished beat after it are undone.
5. **Given** a quest, **When** the player sets or clears a flag, **Then** its latest run holds that flag value.
6. **Given** any of these changes, **When** the quest log is viewed, **Then** each one is marked as made by the creator.

---

### User Story 6 - Credits and items (Priority: P2)

The player can add credits to their balance or take them away. They can grab any item of the world, including items made in this session, in any quantity, into their inventory. They can open the inventory of any object in any region, without walking to it, and take items out or put items in. They can do the same with any resident's inventory. Each change is recorded as made by the creator.

**Why this priority**: Purchases, gifts and item conditions all depend on what is held.

**Independent Test**: Add 100 credits and confirm the balance rises by 100. Grab 3 of an item and confirm they are in the inventory. Open a chest in another region from the menu, take an item, and confirm it left the chest and arrived in the inventory.

**Acceptance Scenarios**:

1. **Given** a balance, **When** the player adds or takes credits, **Then** the balance changes by that amount and the change is recorded as made by the creator.
2. **Given** taking more credits than the balance holds, **When** the player confirms, **Then** the balance becomes 0.
3. **Given** any item of the world, **When** the player grabs a quantity of it, **Then** that quantity is added to their inventory.
4. **Given** an object with an inventory, in any region, **When** the player opens it from the menu, **Then** they see its contents and can move items between it and their own inventory.
5. **Given** a resident, **When** the player opens their inventory from the menu, **Then** they can give the resident items from nothing or from their own inventory, and take the resident's items.
6. **Given** any of these moves, **When** a quest condition depends on what is held, **Then** it reacts as it does for any other change of inventory.

---

### User Story 7 - World state (Priority: P3)

The player can unlock a zone by making any blocking object passable for the session, and lock it again. They can reveal any fact of the world to themselves or make themselves forget it. They can use any activity of any object from anywhere, skipping its costs and terms, with its effects applied as usual. They can turn on an overlay that draws every zone's outline and name on the ground.

**Why this priority**: Useful for testing and debugging; the earlier stories cover the most common needs.

**Independent Test**: Make a blocking door passable and walk through it; lock it again and confirm it blocks. Reveal a fact and confirm it appears among learned facts. Use a paid activity from across the region and confirm its effects apply and no credits were taken. Turn on the zone overlay and confirm outlines and names show.

**Acceptance Scenarios**:

1. **Given** a blocking object, **When** the player unlocks it, **Then** the player can pass through it for the rest of the session; **When** they lock it again, **Then** it blocks again.
2. **Given** a fact the player doesn't know, **When** they reveal it, **Then** it is among their learned facts, recorded as revealed by the creator; **When** they make themselves forget it, **Then** it is gone from their learned facts.
3. **Given** an activity with costs or terms, **When** the player uses it from the menu, **Then** its effects apply and none of its costs are charged or terms checked.
4. **Given** the zone overlay is on, **When** the player looks around, **Then** each zone's outline and name are drawn on the ground; turning it off removes them.

---

### User Story 8 - The HUD follows the theme (Priority: P1)

The player picks a theme in Settings and enters a world. Every piece of interface drawn over the world (inventory, quest log, pause screen, zone title card, location readout, passage confirmation, control hints, the Creator menu) takes that theme's look: Terminal keeps today's cyberpunk frame; Default is soft and rounded with a quiet shadow, and a soft glow only in dark mode; Grimoire is rounded parchment with a double border and small capitals; Slate is flat and crisp.

**Why this priority**: The HUD is the face of the game in every theme, and the Creator menu is built on the same shared classes, so it can't look right in a theme until the HUD does.

**Independent Test**: In each theme, open the inventory, the quest log and the pause screen, and walk into a new zone. Confirm each matches the theme's row in the plan's HUD theme table; in Terminal, confirm nothing changed from today.

**Acceptance Scenarios**:

1. **Given** the Terminal theme, **When** any HUD piece shows, **Then** it looks exactly as it does today: square corners with corner marks, scanlines, glow, uppercase spaced labels, block caret.
2. **Given** the Default theme in light mode, **When** any HUD piece shows, **Then** it has rounded corners, no corner marks, no scanlines and no glow, a soft drop shadow, and filled primary buttons.
3. **Given** the Default theme in dark mode, **When** any HUD piece shows, **Then** it looks as in light mode with a soft glow and a darker shadow.
4. **Given** the Grimoire theme, **When** any HUD piece shows, **Then** it has rounded corners, a double border, a warm shadow, small-capital labels, no scanlines and no glow.
5. **Given** the Slate theme, **When** any HUD piece shows, **Then** it has slightly rounded corners, a crisp shadow, small uppercase labels, no scanlines and no glow.
6. **Given** any theme, **When** a text caret shows in the HUD, **Then** it has that theme's shape and blink.
7. **Given** the player switches theme in Settings, **When** they return to the world, **Then** the HUD shows the new theme's look.

---

### Edge Cases

- A zone has no walkable ground inside its outline: the jump is refused with a message saying the zone has nowhere to stand.
- The target region has no environment asset: the jump is refused with a message saying so.
- A resident who is in the middle of a conversation with another resident is summoned or sent away: that conversation ends.
- The resident the player is chatting with is sent away: the chat closes.
- The world's creator password is changed after a session was unlocked: the session stays unlocked.
- The world's creator password is cleared: unlocked sessions stay unlocked; other sessions can't be unlocked until a password is set again.
- A quest that is already active is started again: it is refused with a message saying it is already active.
- An item is put into an object that has no inventory yet: the object gets one.
- Flying is on when the player jumps somewhere: flying stays on at the arrival point.
- The world has no sentiments defined: the feelings section says the world has none.

## Requirements *(mandatory)*

### Functional Requirements

**Menu and unlocking**

- **FR-001**: Pressing T in a world session MUST toggle the Creator menu, except while the player is typing in a text field or chat. While the password prompt shows, every key goes to the password, T included, and only Escape or Cancel closes the menu.
- **FR-002**: The menu MUST ask for the creator password the first time it is opened in a session and show no powers until the right password is given.
- **FR-003**: A session unlocked once MUST stay unlocked for its whole life, across reloads.
- **FR-004**: Unlocking a session MUST NOT change creator mode in any conversation, and turning on creator mode in a conversation MUST NOT unlock the menu.
- **FR-005**: Every creator power MUST be refused for a session that isn't unlocked.
- **FR-006**: The world MUST pause while the menu is open.

**Moving around**

- **FR-007**: The menu MUST list every region of the world with its zones nested by parent.
- **FR-008**: Picking a zone MUST put the player on walkable ground inside that zone, closest to the zone's middle.
- **FR-009**: Picking a zone in another region MUST move the session to that region and bring the residents following the player along beside them.
- **FR-010**: Jumping into a zone or region MUST count as entering it for every quest beat and condition, the same as walking in.
- **FR-011**: The player MUST be able to jump to any passage of any region, arriving at its arrival point and direction, and to the world spawn.
- **FR-012**: The player MUST be able to toggle flying, which ignores gravity and collisions, and faster walking.

**Residents**

- **FR-013**: The menu MUST list every resident of the world and the region and zone each is in during this session.
- **FR-014**: Summoning a resident MUST put them beside the player, standing, with no spot or activity, in the player's region.
- **FR-015**: Sending a resident to a zone MUST put them on walkable ground inside that zone, in its region, regardless of their zone access.
- **FR-016**: Freezing a resident MUST stop their own decisions, movement and activities until unfrozen, and MUST last across reloads of the session; a frozen resident MUST still answer when talked to.
- **FR-017**: Sending a resident home MUST put them back in their home region at their home spot.
- **FR-018**: The menu MUST show a resident's latest thought and current plan.
- **FR-019**: The player MUST be able to set each of a resident's sentiments toward them in this session to a whole value from -10 to 10, and quest beats and conditions MUST react to it as to any other sentiment change.

**Quests**

- **FR-020**: The menu MUST list every quest of the world with its state in this session.
- **FR-021**: The player MUST be able to start any quest regardless of its offer and start conditions, finish an active quest as completed or failed with its ending written, reopen a quest back to the start of its latest run with its log kept, finish or undo any beat, and set or clear any flag.
- **FR-022**: Every quest change from the menu MUST be logged as made by the creator.

**Credits and items**

- **FR-023**: The player MUST be able to add credits or take credits down to a balance of 0.
- **FR-024**: The player MUST be able to add any quantity of any item of the world, including items made in the session, to their inventory.
- **FR-025**: The player MUST be able to open the inventory of any object in any region, or of any resident, from the menu, and move items between it and their own inventory, or add items to it from nothing.
- **FR-026**: Every credit and item change from the menu MUST be recorded as made by the creator, and quest conditions about what is held MUST react to it.

**World state**

- **FR-027**: The player MUST be able to make any blocking object passable for the session and make it block again.
- **FR-028**: The player MUST be able to reveal any fact of the world to themselves, recorded as revealed by the creator, and forget any fact they know.
- **FR-029**: The player MUST be able to use any activity of any object from the menu, with its effects applied and its costs and terms skipped.
- **FR-030**: The player MUST be able to toggle an overlay that draws every zone's outline and name on the ground of the current region.

**Look and feel**

- **FR-031**: The menu MUST be built only from the HUD's shared classes and the theme's tokens, so it takes each theme's HUD look and accent color; pieces the HUD doesn't have yet (meter, chip, switch, password cell, caret, control states, animations) MUST be added as shared HUD classes that every theme styles.
- **FR-032**: The locked state MUST show a "Creator" title, one cell per typed password character with a blinking caret cell, and "Authenticate" and "Cancel" buttons.
- **FR-033**: The unlocked menu MUST show a header with the session name, "World paused" and key hints; a rail with the five tabs numbered 1 to 5 and the Fly, Fast walk and Show zones switches; and a creator log listing the latest changes made from the menu, newest first.
- **FR-034**: Keys 1 to 5 MUST switch tabs, the up and down arrows MUST move the selection in a tab's list, and T or Escape MUST close the menu.
- **FR-035**: The Go to tab MUST show the chosen region's zones both as a nested list and as a top-down map of their outlines with the player's position and residents marked; hovering a zone in either MUST highlight it in both, and clicking a zone on the map MUST go there.
- **FR-036**: The Residents tab MUST show each resident with a portrait, their region and zone, and chips for frozen and for being near the player; the selected resident's detail MUST show the live thought, each sentiment as a meter of 20 cells centred on 0 that is set by clicking a cell, the resident's actions, and their recent activities.
- **FR-037**: The Quests tab MUST show beats as the quest log's diamond markers, set and undone by clicking them; the Items tab MUST show a holder and the player side by side with items moved between them; the World tab MUST show blocking objects with locked or open chips and secrets with hidden or revealed chips.
- **FR-038**: Every interactive element MUST have distinct hover, pressed, busy, success and failure states, and every change of state the menu shows MUST animate, as listed in the plan's motion table. With reduced motion requested by the system, animations MUST shorten to plain fades.
- **FR-039**: Going somewhere MUST end with a short accent-coloured flash over the view, and a summoned or sent resident MUST arrive with a brief glowing ring at their feet.

**HUD theming**

- **FR-040**: The HUD's shared classes MUST take these from the active theme: corner radius, chip radius, whether corner marks show, scanline strength, glow strength, shadow, border style, panel opacity, label case, spacing, small capitals and size, title spacing and weight, primary button fill, avatar shape, and caret width, corners and blink.
- **FR-041**: Each of the four themes, and Default in both light and dark mode, MUST set every value of FR-040 as listed in the plan's HUD theme table; Terminal's values MUST reproduce today's HUD exactly.
- **FR-042**: No HUD component MAY fix any FR-040 value itself (corner radius, glow shadows, label spacing); every one MUST come from the shared classes.
- **FR-043**: The HUD's reduced-motion behaviour MUST stay as it is in every theme.

**Creator password**

- **FR-044**: Each world MUST have its own creator password, set, changed or cleared on the world's create and edit page with a show-password toggle, stored hashed and never returned.
- **FR-045**: In a world's conversations, `[creator mode: "password"]` MUST be checked against that world's password. Outside any world the server MUST leave the tag as typed and send the assistant's creator mode and trigger prompt sections, so the character judges it.
- **FR-046**: Whenever the creator summons a resident, sends them to a zone or home, or carries them along on a jump to another region, the resident MUST react to it through their own model, with their prompt, world context, where they were and where they are now; they learn only that something happened in between, never who moved them or how. The reaction is stored in their conversation with the user, spoken over their head when they're in the player's region, and may change how they feel about the user.

### Key Entities

- **World session**: gains the moment it was unlocked for the Creator menu (unlocked when set) and which residents are frozen.
- **Session resident state**: the existing record of where a resident is in the session, which summoning, sending and sending home write or clear.
- **Sentiment, quest event, credit transaction, item transfer, revealed fact**: existing records, written with their existing "made by the creator" marking.
- **Object state**: the existing per-session state of an object, whose passable value the menu sets and clears.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: From anywhere in a world, the player reaches any zone of any region in under 10 seconds using the menu.
- **SC-002**: A resident from any region stands beside the player within 3 seconds of being summoned.
- **SC-003**: Every one of the 23 powers can be used without leaving the world page or opening the world editor.
- **SC-004**: 100% of changes made from the menu to quests, credits, items and facts show as made by the creator wherever those changes are logged.
- **SC-005**: No creator power takes effect in a session that wasn't unlocked with the right password.
- **SC-006**: Every action in the menu shows visible feedback within 100 ms of the click, before the request returns.
- **SC-007**: In the Terminal theme, every HUD piece is visually unchanged from before this feature.
- **SC-008**: In Default, Grimoire and Slate, no HUD piece shows scanlines or corner marks, and none shows glow except Default in dark mode.

## Assumptions

- Theme selection already exists in Settings; this feature adds no new theme and no theme setting.
- The creator password belongs to the world: it is set on the world's create and edit page, and the same password turns on creator mode in that world's conversations. Outside any world the server checks no password: the assistant's own creator mode and trigger prompt sections are always sent, and the character judges the trigger itself.
- The menu is available only on the world page, in a world session; the world editor is unchanged.
- The world's own setup (regions, zones, objects, residents' configuration, quests' definitions) is never changed by the menu; every power changes this session only.
- Flying, faster walking and the zone overlay belong to the open page and turn off when the page is reloaded.
- "Items of the world" are the items defined for the world plus the items made during this session.
- Sending a resident home uses the home region and spot configured for them in the world.
- A frozen resident's sentiments, inventory and quest involvement keep working as usual; only their own decisions and movement stop.
