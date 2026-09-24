# Feature Specification: Player World Interaction

**Feature Branch**: `016-player-world-interaction`

**Created**: 2026-09-24

**Status**: Draft

**Input**: User description: "This spec is all about me, as the user, interacting with the world. I'd like to be able to run, to lie down, to sit, to recline, to swim, to make actions just as the AIs can in the world. I want to know which zones I am, like, if I arrive to a new zone, a header appears briefly telling which zone it is, if I get close to something that the AIs can interact with, I want to be able to see it, and hit a key to have a description, just like the AIs can know what the objects are. Anything you implement has to have a beautiful UI, that makes sense with the world we are building, it has to look pretty, this is like a videogame and the interface matters. Try for animations if possible for glowing effects or stuff like that, so whatever you do is not completely static"

## Clarifications

### Session 2026-09-24

- Q: Does the user stay a bodiless first-person view, or gain a visible body? → A: The user stays a first-person view with no body; sitting, lying and reclining change where the view sits and how it is tilted.
- Q: What happens when the user does an activity with no posture change, like making coffee or looking out at the city? → A: The user faces the spot, a glowing progress ring runs for a few seconds, and an action line such as "*makes a coffee*" appears on screen.
- Q: Who learns about the user's activities, and who reacts? → A: Every resident nearby who could reasonably see the user gets the action line (for example `*sits down at the bar*`). The resident in the open conversation replies to it right away; the others get it silently in their own conversation and know it the next time they speak or decide.
- Q: How do residents behave toward the user during a conversation? → A: Keep it simple: while a conversation is open, the resident turns to face the user. A resident holding a seat, bed or lounger keeps its direction.
- Q: How does the user choose an activity from a card? → A: The card holds a list of the activities that the user moves through with the arrow keys and starts with Enter.
- Q: Can the user crouch? → A: Yes. Q toggles crouching; the user can walk while crouched.
- Q: When the user does something their conversation partner cannot see, does she still get the action line? → A: No. She gets it only when she can see the user, like every other resident; otherwise nothing is added to the chat.
- Q: How are silently received action lines recorded in that resident's chat? → A: As her own messages, in her voice, as an action between asterisks describing what she sees (for example `*I see the user sit down at the bar counter*`).
- Q: When a resident silently sees several things in a row, does each become its own message? → A: Yes. Every observation is logged as its own message.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Know where I am (Priority: P1)

As the user walks through a world, crossing into a new zone shows a title card: the zone's name, the zone it sits inside when it is nested, and the floor in worlds with several floors. The card animates in with a glow, holds for a moment and fades away, the way a game announces a new area. A small, always-visible location readout next to the minimap keeps showing the current zone after the card is gone. When the user first arrives in the world, the card announces where they start.

**Why this priority**: It is the smallest piece that makes the world feel like a place with named areas, it works in every marked world today with no other story, and it sets the visual language the other stories reuse.

**Independent Test**: In the penthouse, walk from the foyer through the living room into the sunken lounge and out to the pool terrace. Each crossing shows a card with the right name, the sunken lounge card mentions the living room, and the location readout always matches.

**Acceptance Scenarios**:

1. **Given** the user is in the foyer, **When** they walk into the living room, **Then** a title card reading "Living room" animates in, holds briefly and fades out.
2. **Given** the user is in the living room, **When** they step down into the sunken lounge, **Then** the card shows "Sunken lounge" with "Living room" as its context line.
3. **Given** a world with three floors, **When** the user climbs to the second floor and enters a reading hall, **Then** the card shows the reading hall and the floor's name.
4. **Given** the user stands on the border between two zones, **When** they step back and forth across it several times within a few seconds, **Then** the card does not flicker or replay for each crossing.
5. **Given** the user enters the world, **When** the world becomes ready, **Then** the card announces the zone they start in.
6. **Given** the user walks into an area that belongs to no zone, **When** they cross into it, **Then** no card appears and the location readout shows the world's name.
7. **Given** a world with no marked zones, **When** the user explores it, **Then** no cards or readout appear and nothing else changes.

---

### User Story 2 - Run, crouch and swim (Priority: P2)

The user can run by holding a key while moving, covering ground in large worlds like the faire much faster. They can crouch with a key press and walk crouched, slowly and low, until they press it again or start running. In water deeper than about chest height, the user swims: the view floats just above the surface with a gentle bob, movement is slower, and the water surface, light and sound make it clear they are swimming. Leaving deep water, by the pool steps or anywhere the ground rises, returns them to walking.

**Why this priority**: Running, crouching and swimming are pure movement and need no world markers, so they work in every world. They make the space nicer to move through, and swimming is the first posture the user shares with residents.

**Independent Test**: In the faire, run the length of the Lane and compare the time with walking. Crouch and walk around a table. In the penthouse, walk down the pool steps into the deep end, swim across, and walk back out.

**Acceptance Scenarios**:

1. **Given** the user is walking, **When** they hold the run key, **Then** they move at about twice walking speed until they release it.
2. **Given** the user runs into a wall or furniture, **When** they keep running, **Then** they are stopped exactly as when walking and never pass through.
3. **Given** the user walks down the pool steps, **When** the water gets deeper than about chest height, **Then** they start swimming, with the view just above the surface and slower movement.
4. **Given** the user is swimming, **When** they reach water shallow enough to stand in, **Then** they are walking again.
5. **Given** the user is swimming, **When** they hold the run key, **Then** they swim faster, still slower than walking speed on land.
6. **Given** a resident is asked to come to the user while the user swims, **When** she arrives, **Then** she comes to the user the same way she would for any swimmer.
7. **Given** the user is standing, **When** they press the crouch key, **Then** the view lowers smoothly to crouching height and stays there.
8. **Given** the user is crouched, **When** they move, **Then** they walk at about half walking speed, still crouched.
9. **Given** the user is crouched, **When** they press the crouch key again or hold the run key, **Then** they rise smoothly to standing, and running starts if they hold the run key.
10. **Given** the user is crouched, **When** they walk into water deep enough to swim, **Then** they stop crouching and swim.
11. **Given** the user is crouched, **When** they ask a resident what they are doing, **Then** she knows the user is crouching.

---

### User Story 3 - See and read what the world holds (Priority: P3)

Everything a resident can know about the world, the user can discover by walking up to it. When the user gets close to an object, it gains a soft animated glow and a small floating label with its name and a key prompt. Pressing the key opens a description card styled like the rest of the world interface: the object's name, its description, the zone it belongs to, and the activities it offers, with each activity showing whether a spot for it is free or which resident is using it. The activities form a list the user moves through with the arrow keys; the highlighted row glows. The user can also open the same kind of card for the zone they are in, with its description and its zone activities.

**Why this priority**: It turns the world's descriptions, which only residents could read, into something the user sees. It is also how the user finds what they can do, which Story 4 builds on.

**Independent Test**: In the penthouse, walk up to the Rhodes, open its card and read its description and "Play the Rhodes"; walk up to the pool loungers while a resident reclines on one and see that spot marked as taken by her; open the zone card in the pool terrace.

**Acceptance Scenarios**:

1. **Given** the user is several metres from the bar counter, **When** they walk up to it, **Then** it glows softly and shows its name with the key prompt.
2. **Given** several objects are within reach, **When** the user looks around, **Then** only the object they are looking at, or the nearest one when none is in view, shows the prompt, and the prompt follows their gaze.
3. **Given** the prompt is shown, **When** the user presses the inspect key, **Then** a card opens with the object's name, description, zone and activities, with the first activity highlighted.
4. **Given** a card with several activities is open, **When** the user presses the down and up arrow keys, **Then** the highlight moves between activities, wrapping at either end, and the user keeps looking and moving normally.
5. **Given** a resident reclines on a pool lounger, **When** the user opens the pool loungers' card, **Then** that spot shows as taken by her name and the other loungers show as free.
6. **Given** a card is open, **When** the user walks away, presses the key again or presses the close key, **Then** the card closes with an animation.
7. **Given** the user is on the pool terrace, **When** they open the zone card, **Then** it shows "Pool terrace", its description and "Look out at the city".
8. **Given** an object with a description and no spots, such as the TV, **When** the user inspects it, **Then** its card shows the description and states that it offers no activities.
9. **Given** a conversation is open and the message box is focused, **When** the user types the inspect key, **Then** it goes into the message and no card opens.

---

### User Story 4 - Sit, lie down, recline and do things like the residents (Priority: P4)

From an object's card, the user highlights one of its activities with the arrow keys and presses Enter. The user is carried smoothly to a free spot for it and takes its posture: sitting on a bar stool, lying on the bed, reclining on a lounger or in the bath. The view settles where their eyes would be in that posture, facing the way the spot faces, and they can still look around within a natural range. Standing activities, such as making coffee, singing at the microphone or looking out at the city, and zone activities play out where the user is: the user turns to face the spot, a glowing progress ring fills for a few seconds, and an action line such as "*makes a coffee*" appears on screen. While the user holds a spot, no resident can take it, and residents know what the user is doing when they speak. Each activity the user starts, completes or gets up from produces an action line that every nearby resident who could reasonably see the user receives. The resident in the open conversation, if any, replies to it right away; the others take note silently, recording in their own words what they saw, and know it the next time they speak or decide. The user gets up by pressing a movement key or the get-up key and is set back on the floor beside the spot.

**Why this priority**: This is the heart of the request, doing what the residents do, but it depends on Story 3 for discovering activities.

**Independent Test**: In the penthouse, sit at the bar counter, then recline on a pool lounger, then lie on the bed, getting up in between. Ask a resident "what am I doing?" while reclining, and ask another resident to use the lounger the user holds.

**Acceptance Scenarios**:

1. **Given** the user opens the bar counter's card, **When** they highlight "Sit down" and press Enter, **Then** they are carried to the nearest free stool, seated at seated eye height, facing the counter.
2. **Given** the user chooses "Lie down" on the bed, **When** they settle, **Then** the view is low and looking up and along the bed, and they can look around within a limited range.
3. **Given** the user reclines on a lounger, **When** they press a movement key, **Then** they get up smoothly and stand beside the lounger, and the lounger is free again.
4. **Given** every stool at the bar is taken by residents, **When** the user chooses "Sit down", **Then** they are told no seat is free, and nothing moves.
5. **Given** the user is seated on a stool, **When** a resident tries to use that stool, **Then** she is told it is taken, as for a resident-held spot.
6. **Given** the user reclines on a lounger, **When** they ask a resident what they are doing, **Then** she knows the user is reclining on a pool lounger.
7. **Given** the user is seated with a conversation open, **When** they type or speak, **Then** the conversation works exactly as when standing.
8. **Given** the user is in a private zone such as the powder room, **When** they use its objects, **Then** they can, since private zones only restrict residents.
9. **Given** the user holds a spot, **When** they leave the world and later resume the session, **Then** they return standing beside that spot and the spot is free.
10. **Given** the user is at the back counter, **When** they choose "Make coffee", **Then** they turn to face the counter, a glowing progress ring fills for a few seconds, and "*makes a coffee*" appears on screen.
11. **Given** the user is doing a standing activity, **When** they press a movement key before the ring fills, **Then** the activity is cancelled and no action line appears.
12. **Given** a conversation with a resident is open and she can see the user, **When** the user sits down at the bar, **Then** `*sits down at the bar counter*` is added to the conversation and she replies to it.
13. **Given** a conversation is open, the user is seated and she can see them, **When** they get up, **Then** an action line saying so is added to the conversation and she replies to it.
14. **Given** a second resident stands across the bar in plain view, **When** the user sits down, **Then** her conversation gains her own line `*I see the user sit down at the bar counter*`, with no reply, and when the user later talks to her she knows they sat down.
15. **Given** a resident is in another room behind a wall, or far across the world, **When** the user sits down, **Then** nothing is added to her conversation.
16. **Given** no conversation is open, **When** the user starts an activity in view of two residents, **Then** each records her own observation of it, and neither replies.
17. **Given** a conversation is open and the resident is within talking range but behind a wall, **When** the user sits down, **Then** nothing is added to the conversation and she does not reply.

---

### User Story 5 - Residents face the user while talking (Priority: P5)

While a conversation is open, the resident turns to face the user, and keeps facing them as the user moves around her. Whatever she does in the conversation, whether playing a pose or replying, she does toward the user. When she walks somewhere, she faces where she is going, and turns back to the user when she stops.

**Why this priority**: It is a small change that makes every conversation feel directed at the user, and it needs none of the other stories.

**Independent Test**: Open a conversation with a resident from behind her, then circle around her while talking and ask her to play a pose.

**Acceptance Scenarios**:

1. **Given** a resident stands with her back to the user, **When** the user opens a conversation with her, **Then** she turns smoothly to face the user.
2. **Given** a conversation is open, **When** the user walks around her, **Then** she keeps turning to face them.
3. **Given** a conversation is open, **When** she plays a pose, **Then** she plays it facing the user.
4. **Given** she walks to the bar at the user's request during a conversation, **When** she walks, **Then** she faces her direction of travel, and turns to face the user once she arrives.
5. **Given** the conversation ends, **When** she is standing still, **Then** she stops turning toward the user and goes back to her own activities.

---

### Edge Cases

- The user starts running or swimming while a conversation is open and walks past the conversation's distance limit faster than before.
- The user runs into deep water from the edge of the pool instead of walking down the steps.
- The user swims under an object or a ledge where the view would end up inside geometry.
- A resident is already walking to the spot the user chooses.
- The user chooses an activity at a spot that is not reachable from where they stand, or is on another floor.
- The zone the user is in has a nested zone in the same place, like the sun ledge inside the pool inside the pool terrace.
- Many objects cluster together, like the eight bar stools or the keytar wall next to the keyboards, so glows and labels would overlap.
- The environment is replaced while the user holds a spot, and the spot no longer exists.
- The user tries to take a resting posture on a spot inside the water, like the in-water loungers.
- The title card would appear while the full-screen map, an object card or a conversation is already on screen.
- The user has the operating system's reduced-motion setting on.
- Each of the four application themes gives the world interface its colours.
- The user starts several activities in quick succession during a conversation, each asking the resident for a reply.
- The user gets up while the resident is still replying to the line for sitting down.
- The user sits, gets up and sits again in view of a resident with no conversation open; her conversation gains three observations, in order.
- The resident is seated on a stool with the user standing behind her when a conversation opens.
- The user and the resident are on different heights, like the sunken lounge and the living room above it, while she turns to face them.

## Requirements *(mandatory)*

### Functional Requirements

**Location**

- **FR-001**: When the user crosses into a different zone, the world MUST show a title card with the zone's name, the name of its parent zone when it has one, and the floor's name when the world has more than one floor.
- **FR-002**: When zones are nested, the card MUST name the innermost zone containing the user.
- **FR-003**: The title card MUST animate in, stay readable for about 3 seconds, and animate out. A new crossing while a card is showing MUST replace it.
- **FR-004**: Crossing back into a zone the user left less than about 2 seconds earlier MUST NOT show a card.
- **FR-005**: When the world becomes ready, the card MUST announce the zone the user starts in.
- **FR-006**: A location readout MUST stay on screen while exploring, showing the current zone and, in multi-floor worlds, the floor. Outside any zone, it MUST show the world's name.
- **FR-007**: Worlds without marked zones MUST show no title card and no location readout.

**Movement**

- **FR-008**: Holding the run key while moving MUST move the user at about twice walking speed, with the same collision, step and drop rules as walking.
- **FR-008a**: Pressing the crouch key MUST lower the view smoothly to crouching height, and pressing it again MUST raise it back to standing. While crouched, the user MUST move at about half walking speed. Holding the run key while crouched MUST stand the user up and run. Crouching MUST end on entering water deep enough to swim, and MUST NOT be possible while swimming or holding a spot.
- **FR-009**: The user MUST be swimming whenever the water under them is deeper than the resident swimming threshold (about 1.1 m), and walking again when it is shallower.
- **FR-010**: While swimming, the user's view MUST float just above the water surface with a gentle bob, movement MUST be slower than walking, and running MUST speed it up while keeping it below walking speed.
- **FR-011**: While swimming, the view MUST never pass through the water surface, the pool walls or objects in the water.
- **FR-012**: The user MUST leave the water wherever the ground rises to standing depth, such as the pool steps.

**Discovering objects**

- **FR-013**: Every object in the world's layout MUST be discoverable by the user, whether or not it offers activities.
- **FR-014**: When the user is within reach of an object (about 2.5 m, on the same floor), the object MUST show an animated glow and a floating label with its name and the inspect key.
- **FR-015**: At most one object MUST show the prompt at a time: the one the user is looking at within reach, else the nearest within reach.
- **FR-016**: Pressing the inspect key MUST open a card with the object's name, description, zone and activities. Each activity MUST show whether a spot offering it is free, or the name of the resident using it.
- **FR-016a**: A card's activities MUST form a selectable list: the up and down arrow keys move a highlight between them, wrapping at either end, and Enter starts the highlighted activity. The first activity is highlighted when the card opens. The arrow keys and Enter MUST NOT move the user or act while typing.
- **FR-017**: The user MUST be able to open a card for the zone they are in, showing its name, description, parent zone, floor and zone activities.
- **FR-018**: A card MUST close when the user presses the inspect or close key, or moves out of reach of its object.
- **FR-019**: Keys used by the interface MUST NOT act while the user is typing in the message box.

**Doing activities**

- **FR-020**: The user MUST be able to start any activity of an object from its card, and any zone activity of the zone they are in from the zone card.
- **FR-021**: Starting a spot activity MUST carry the user smoothly to the nearest free spot offering it and set their view to that spot's facing and that posture's eye position.
- **FR-022**: The user MUST take the postures residents take: sitting, lying and reclining, each with its own eye height and a limited look-around range suited to it.
- **FR-023**: When no spot offering the activity is free, the user MUST be told so and stay where they are.
- **FR-024**: A spot the user holds MUST count as occupied for residents, exactly as a resident-held spot does, and the user MUST NOT be able to take a spot a resident holds.
- **FR-025**: The user MUST get up by pressing a movement key or the get-up key, ending standing on the floor beside the spot and freeing it.
- **FR-026**: Starting an activity with no posture, or a zone activity, MUST turn the user to face its spot (for a zone activity, they stay facing where they are), fill a glowing progress ring over about 3 seconds, and then show an action line describing it (for example "*makes a coffee*") on screen. Pressing a movement key before the ring fills MUST cancel the activity without an action line.
- **FR-027**: Whenever a resident responds or decides, she MUST know the user's current posture and activity, and the object or zone it belongs to, alongside the user's zone and floor.
- **FR-028**: Every activity the user starts, completes or gets up from MUST produce an action line in asterisks (for example `*sits down at the bar counter*`), added to the session conversation of every resident who could reasonably see the user at that moment.
- **FR-028b**: A resident could reasonably see the user when she is on the same floor, within about 15 m, with nothing solid between her eyes and the user's, and either within about 4 m or facing within about 110° of the user.
- **FR-028c**: The resident of the open conversation, if any, MUST reply to the line as she would to a message when she can see the user; when she cannot, nothing is added to the conversation. Every other resident MUST receive it silently, with no reply, and know it the next time she speaks or decides.
- **FR-028d**: A line a resident receives silently MUST be recorded in her conversation as her own message, in the first person, as an action between asterisks describing what she sees (for example `*I see the user sit down at the bar counter*`, `*I see the user get up from the bar counter*`). Lines sent to the resident of the open conversation stay the user's own messages (`*sits down at the bar counter*`).
- **FR-028e**: Every observation MUST be logged as its own message, in the order the user did things.
- **FR-028a**: Action lines sent to the user's conversation partner MUST be written in the third person from the activity's name and its object (for example "Sit down" at the "Bar counter" becomes `*sits down at the bar counter*`).
- **FR-029**: Conversations, voice mode, the map and direct controls MUST work in every posture and while swimming.
- **FR-030**: The user MUST be able to use objects in private zones; privacy restricts residents only.
- **FR-031**: When the user leaves the world while holding a spot, the spot MUST be freed, and on return the user MUST stand beside it.

**Residents facing the user**

- **FR-037**: While a conversation is open and the resident is standing still or swimming in place, she MUST turn smoothly to face the user and keep facing them as the user moves.
- **FR-038**: Poses she plays during the conversation MUST play facing the user.
- **FR-039**: While walking or swimming somewhere, she MUST face her direction of travel, and turn to face the user again when she stops.
- **FR-040**: While she holds a spot in a resting posture (sitting, lying, reclining), she MUST keep the spot's facing, so she stays aligned with the seat, bed or lounger.
- **FR-041**: When the conversation ends, she MUST stop turning toward the user.

**Interface quality**

- **FR-032**: Every new interface element (title card, location readout, object glow and label, prompts, cards, activity choices, notices) MUST share one visual language consistent with the existing world interface, and MUST take its colours from the active application theme.
- **FR-033**: Every new interface element MUST animate in and out, with no element appearing or disappearing abruptly. Persistent elements, such as the prompt and the location readout, MUST carry a subtle ongoing animation, such as a glow pulse or a scan-line shimmer.
- **FR-034**: When the operating system asks for reduced motion, animations MUST be reduced to short fades, and ongoing effects MUST stop.
- **FR-035**: New interface elements MUST stay readable against bright and dark scenes, and MUST NOT overlap each other, the conversation panel or the map.
- **FR-036**: Every key the feature uses MUST be shown in the interface where it applies.

### Key Entities

- **Player state**: The user's current movement mode (walking, running, crouching, swimming), posture (standing, crouching, sitting, lying, reclining), the spot and activity they hold, and their current zone and floor.
- **Zone**: As defined for residents: a named, described area with a floor, an optional parent zone and zone activities.
- **World object**: As defined for residents: a named, described thing with interaction spots.
- **Interaction spot**: As defined for residents, now occupiable by either one resident or the user.
- **Action line**: A description of something the user did: in the third person as the user's message to the resident they are talking to, and in the first person, as her own observation, for the residents who saw it silently.
- **Activity**: As defined for residents: something done at a spot or in a zone, with an optional posture.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On every zone crossing in a walk through all 22 penthouse zones, the title card shows the right zone within half a second.
- **SC-002**: Running covers the length of the faire's Lane in about half the time walking takes.
- **SC-003**: A user can swim the full length of the penthouse pool and climb out by the steps without the view ever going below the water or through a wall.
- **SC-004**: Every object in the penthouse layout can be found and inspected, and its card matches what residents are told about it.
- **SC-005**: A first-time user finds and completes "recline on a pool lounger" starting from the foyer within 1 minute, using only the on-screen prompts.
- **SC-006**: In 10 attempts at each resting posture in the penthouse, the user ends up at the spot's facing and posture in every attempt.
- **SC-007**: Across 20 tries, no resident ever takes a spot the user holds.
- **SC-008**: When asked, residents correctly say what the user is doing in at least 9 of 10 attempts.
- **SC-009**: Every new interface element animates in and out, and renders legibly in all four application themes.
- **SC-010**: Within 1 second of a conversation opening, a standing resident is facing the user, whichever way she faced before.
- **SC-011**: While a conversation is open and the resident can see the user, every activity the user starts or gets up from appears in the conversation and gets a reply from her.
- **SC-012**: When the user sits down in view of a resident across the room and out of view of one behind a wall, only the first one knows about it when asked afterwards.

## Assumptions

- The user stays a first-person view with no visible body; postures change where the view sits and how far it can turn.
- The user has no pose library; poses remain a resident-only feature.
- Default keys: Shift to run, Q to toggle crouching, E to inspect, G to open the zone card, the arrow keys and Enter to choose and start an activity from a card, and a movement key or Space to get up. C, F, X, M and V keep their current meanings.
- The penthouse, the faire and The Index are the reference worlds; the Connection Node and the café gain these features once their environments carry markers.
- Swimming uses the same depth thresholds residents use, so the user and residents enter and leave the water at the same depths.
- Diving below the surface is out of scope.
- The user's own spot is not saved per session; the user resumes standing beside it.
- Running has no stamina or limit.
- Crouching lowers the view and slows the user; it does not let them pass under anything they could not pass standing.
- The interface reuses the application's four themes and existing world interface styles; no new theme is added.
