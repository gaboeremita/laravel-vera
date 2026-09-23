# Feature Specification: Resident World Agency

**Feature Branch**: `015-resident-world-agency`

**Created**: 2026-09-22

**Status**: Draft

**Input**: User description: "Resident AIs understand world zones and objects, move with purpose, hold poses at interaction spots, and choose idle activities through an agent loop that runs only while the user is in the world."

## Clarifications

### Session 2026-09-22

- Q: For worlds without built-in markers, how should spots be placed and oriented? → A: No manual authoring. Zones, objects and interaction spots come only from markers embedded in the environment file; worlds without markers have no zones or objects.
- Q: When does the resident count as "in conversation" for holding off self-chosen actions? → A: Exactly while a conversation with her is open; she may resume self-chosen actions as soon as it closes.
- Q: How long does the resident wait between self-chosen activity decisions? → A: A random 10 seconds to 1 minute after her previous activity (or decision to stay put) finishes.
- Q: What happens to items she picks up, like a drink? → A: Residents do not pick up or carry objects. Activities such as getting a drink are performed as poses at the spot, with no held item.
- Q: Which model makes the resident's self-chosen activity decisions? → A: Always her own conversation model.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The resident knows the world she is in (Priority: P1)

A world is divided into named zones (music studio, pool terrace, master bedroom) that contain interactive objects (the bar, a pool lounger, the bed, the Rhodes). Each zone and object carries a short description and the activities it supports. While the user explores a world, the resident knows which zone she is in, which zone the user is in, roughly how far away the user is, what objects are around her, and what other zones exist. When the user asks "is there a pool here?" or "where are you?", she answers correctly from that knowledge.

**Why this priority**: Every other story depends on the resident knowing what exists and where. On its own it already fixes the most visible gap: today the resident is told she is in the penthouse but knows nothing about it.

**Independent Test**: Load a world with marked zones and objects, place the resident in the studio and the user on the pool terrace, and ask her where she is, where the user is, and what she could do nearby. Her answers match the marked world.

**Acceptance Scenarios**:

1. **Given** a world with a zone named "Pool terrace" containing a pool, **When** the user asks the resident whether the place has a pool, **Then** she confirms it and says where it is.
2. **Given** the resident stands inside the "Music studio" zone, **When** the user asks where she is, **Then** she names the music studio.
3. **Given** the user walks from the kitchen to the garden, **When** the user next speaks to the resident, **Then** she knows the user is now in the garden.
4. **Given** a zone nested in another (vocal booth inside the music studio), **When** the resident is inside the vocal booth, **Then** she describes her location as the vocal booth within the studio.
5. **Given** a world with no marked zones or objects, **When** the user talks to the resident, **Then** the conversation works as it does today, with no errors and no invented places.

---

### User Story 2 - Talk while moving, by text or voice (Priority: P2)

Talking to a resident no longer freezes the world. The conversation stays on screen while the user and the resident both keep moving. For typing, the user focuses the message box on purpose, so movement keys type text, and sending the message or leaving the box hands the keys back to movement. For speaking, the user turns on in-world voice mode, as in the regular chat, and talks hands-free while walking. The resident's replies are spoken aloud. Her voice comes from where she is, so it sounds closer or farther away as she moves.

**Why this priority**: Everything the user asks for during a conversation, like "follow me", "meet me at the bar" or "show me the studio", only makes sense if both of them can move while talking. Voice is the most natural way to do that.

**Independent Test**: Open a conversation with the resident, then walk from the foyer to the pool while exchanging messages, first by typing and then by voice. Neither the user nor the resident is ever paused, and every message reaches her.

**Acceptance Scenarios**:

1. **Given** a conversation is open and the message box is not focused, **When** the user presses movement keys, **Then** the user moves and the conversation stays visible.
2. **Given** the message box is focused, **When** the user types letters that are also movement keys, **Then** they go into the message and the user does not move.
3. **Given** the user sends a typed message, **When** it is sent, **Then** the keys control movement again without an extra step.
4. **Given** voice mode is on, **When** the user speaks while walking, **Then** the speech is transcribed and sent without the user stopping, and her reply is spoken aloud.
5. **Given** the resident is walking to the bar because the user asked her to, **When** the user keeps talking to her, **Then** she keeps walking and replies along the way.
6. **Given** voice mode is on, **When** the resident is farther away, **Then** her spoken reply sounds farther away than when she is close.
7. **Given** voice mode is on, **When** the user leaves the world or ends the conversation, **Then** the microphone stops listening.
8. **Given** a conversation with one resident is open, **When** the user walks up to another resident and tries to start a conversation, **Then** no second conversation starts, and every typed or spoken message still goes only to the first resident.
9. **Given** a conversation is open, **When** the user walks toward the distance limit, **Then** the user is warned, and past the limit the conversation ends and voice mode stops listening.
10. **Given** a conversation is open, **When** the user looks around the world, **Then** the resident being talked to is clearly marked.

---

### User Story 3 - Find residents with name tags and a map (Priority: P3)

Residents are easy to find, even in a large world with many of them. Every resident shows a floating name tag above her head that stays readable at any distance and remains visible through walls and furniture. The resident the user is talking to has a highlighted tag. When she is off-screen, an indicator at the edge of the screen points toward her. The user can also open a map of the world: a small corner minimap while moving, and a full-screen map on demand. The map shows the world from above, zone names, the user's position and facing, and every resident with her name. The resident being talked to is highlighted.

**Why this priority**: Residents already get lost in large worlds today, and conversations that follow the user around make it more important to know where she is. The map is generated from the world itself, so it works for every world without extra setup.

**Independent Test**: In the penthouse with three residents, open the full-screen map and find each of them by name. Start a conversation with one, walk into another room, and follow the off-screen indicator and the highlighted map marker back to her.

**Acceptance Scenarios**:

1. **Given** several residents in a world, **When** the user looks around, **Then** each resident shows her name above her head, including residents behind walls.
2. **Given** a conversation is open, **When** the user looks at the residents, **Then** the tag of the resident being talked to is visibly highlighted and distinct from the others.
3. **Given** a conversation is open and the resident is off-screen, **When** the user looks elsewhere, **Then** an indicator at the screen edge points toward her.
4. **Given** the user opens the full-screen map, **When** it is shown, **Then** it displays the world from above with zone names, the user's position and facing, and every resident's position and name.
5. **Given** a conversation is open, **When** the user views the map, **Then** that resident's marker is highlighted.
6. **Given** the user and residents move, **When** the map or minimap is open, **Then** the markers follow their positions continuously.

---

### User Story 4 - The resident goes where she means to go (Priority: P4)

When the resident decides to go somewhere, either because the user asked ("meet me at the bar", "follow me") or because she said she wants to, she walks there along a valid route: through doorways, up and down steps, and around furniture. She can go to a zone, go to a specific object, follow the user, or stop. When she arrives, fails to arrive, or is interrupted, she learns the outcome, so what she says afterwards matches what actually happened.

**Why this priority**: Moving with purpose is the next most visible behavior after knowledge, and sitting, drinking and all idle activities depend on it.

**Independent Test**: With the resident in the studio, ask her to meet the user at the bar. She walks there through the studio door and the great room, stops at the bar, and confirms she has arrived.

**Acceptance Scenarios**:

1. **Given** the resident is in the studio, **When** she agrees to go to the bar, **Then** she walks a route that avoids walls and furniture and stops at the bar.
2. **Given** the resident is on the pool terrace, **When** she decides to enter the pool, **Then** she uses the pool steps and does not walk across the water surface or drop off the edge.
3. **Given** the user asks her to follow, **When** the user walks around the world, **Then** she stays close behind the user until told to stop or until she decides to do something else.
4. **Given** the resident is walking somewhere, **When** the user tells her to stop, **Then** she stops where she is.
5. **Given** the target is unreachable, **When** she tries to go there, **Then** she does not get stuck walking into a wall, and she is told the destination could not be reached so she can say so or choose something else.

---

### User Story 5 - The resident uses things in the world (Priority: P5)

Objects offer interaction spots with an activity: sit on the piano bench, lie down on the bed, recline on a pool lounger, get a drink at the bar, play the Rhodes. When the resident uses one, she walks to that exact spot, faces the right way, and holds or performs the matching pose until she moves on. Activities are poses only: getting a drink at the bar means performing a drinking pose there, without a glass appearing or being carried.

**Why this priority**: This is the payoff that makes the world feel inhabited, but it needs the resident to know the world (P1) and reach the spot (P4) first.

**Independent Test**: Ask the resident to lie down on a pool lounger. She walks to it, lies down aligned with it, and stays reclined until asked to get up or until she chooses another activity.

**Acceptance Scenarios**:

1. **Given** a free pool lounger, **When** the resident chooses to recline on it, **Then** she ends up lying on it, aligned with its position and direction, and stays there.
2. **Given** the resident is reclining, **When** she chooses another activity, **Then** she gets up and leaves the lounger free.
3. **Given** another resident already occupies a lounger, **When** the resident tries to use the same lounger, **Then** she is told it is taken and can pick another one.
4. **Given** the resident gets a drink at the bar, **When** she performs it, **Then** she plays the drinking pose at the bar, and no object appears in her hands.
5. **Given** an activity with no dedicated pose, **When** the resident performs it, **Then** she still completes it with her default stance, and nothing fails.

---

### User Story 6 - The resident chooses what to do when left alone (Priority: P6)

While the user is in the world and no conversation with her is open, the resident periodically decides for herself whether to do something and what. While a conversation with her is open, she makes no self-chosen actions; she gives the user her attention and only acts when the user asks her to. Her choice comes from her personality, her mood, what she has done recently and what the world offers. She is not picked at random from a list. An extroverted resident may go get a drink; one who loves music may wander into the studio; one who just had a drink will choose something else. She can carry out a multi-step activity ("get a drink at the bar, then go lie on a lounger by the pool"). She adjusts the plan when a step fails, and drops it when the user speaks to her. Staying where she is counts as a real choice.

Every self-chosen step is recorded in the conversation using roleplay convention: her reason as a thought in parentheses and the step as an action in asterisks. For example: `(I want to forget about today for a while) *walks to the bar to get a drink*`. The same line appears in a thought bubble above her in the world, so the user can see what she is doing and why at a glance.

**Why this priority**: This makes the world feel alive without the user directing everything. It builds on all the previous stories and carries the highest ongoing cost, so it comes last.

**Independent Test**: Enter the penthouse and leave the resident unaddressed for several minutes. She makes at least one self-chosen, personality-consistent activity choice, carries it out across multiple steps, and her recent-activity history shows what she did and why.

**Acceptance Scenarios**:

1. **Given** the user is in the world, no conversation with her is open, and her previous activity finished between 10 seconds and 1 minute ago, **When** her idle wait ends, **Then** she decides on an activity, or on staying put, based on her personality and recent history.
2. **Given** the resident had a drink a few minutes ago, **When** she next decides what to do, **Then** her choice takes that recent activity into account.
3. **Given** the resident is partway through a multi-step activity, **When** the user speaks to her, **Then** she stops the activity and gives the user her attention.
4. **Given** a step of her plan fails (the lounger is taken), **When** she learns the outcome, **Then** she adapts the plan or picks something else.
5. **Given** the user leaves the world, **When** the resident is mid-activity or idle, **Then** she makes no further decisions and takes no further actions.
6. **Given** the user comes back to the world, **When** the world loads, **Then** the resident is where the user left her, and her first decision can respond to the user's arrival.
7. **Given** the resident decides to get a drink because she wants to forget her day, **When** she starts, **Then** the conversation shows a line like `(I want to forget about today for a while) *walks to the bar to get a drink*`, and a thought bubble above her shows the same line.
8. **Given** a conversation with the resident is open, **When** her idle period would otherwise end, **Then** she makes no self-chosen action.

---

### Edge Cases

- The resident is asked to use an object in a zone she is not allowed to enter on her own (a private zone like the toilet room).
- Two residents choose the same interaction spot at the same moment.
- The user moves while the resident is following them into a spot she cannot reach, such as the water, or an area only the user can access.
- The resident is interrupted mid-pose (reclining) by a request to go somewhere.
- The model returns an action that does not exist, or names an object or zone that is not in the world.
- A zone or object is deleted while the resident is using it or walking to it.
- The resident's saved position no longer falls on walkable ground because the environment was replaced.
- A world with many zones and objects, such as the faire, produces more description than fits comfortably in the resident's context.
- The user leaves the world while the resident is in the middle of a held pose.
- Nobody talks to the resident for a long time, and she keeps choosing activities indefinitely.
- The user walks far away from the resident in the middle of a conversation.
- Several residents stand close together when the user starts a conversation, or while one is in progress.
- The resident being talked to walks away from the user on her own (following a request) until they are beyond the distance limit.
- A world with several floors, where the map must show which floor each resident is on.
- Many residents crowded into a small area, with their name tags and map markers overlapping.
- In voice mode, the resident's own spoken reply or background sound is picked up by the microphone.
- The user denies microphone access, or it becomes unavailable while walking in voice mode.

## Requirements *(mandatory)*

### Functional Requirements

**World knowledge**

- **FR-001**: Worlds MUST support named zones, each with a description, an area on the ground with a vertical range, and an optional parent zone.
- **FR-002**: Worlds MUST support interactive objects, each with a name, description, position, and zero or more interaction spots.
- **FR-003**: Each interaction spot MUST define its position, the direction the resident faces, and the activities it offers.
- **FR-004**: Zones MAY offer zone-level activities that do not belong to a single object (swim in the pool, look out at the city).
- **FR-005**: Zones MAY be marked private, meaning residents only enter them when the user explicitly asks them to.
- **FR-006**: Each zone MUST define an entry point that residents walk to when told to go to that zone.
- **FR-007**: An object's zone MUST be determined by which zone contains it; owners do not assign it separately.
- **FR-008**: Whenever the resident responds or decides, she MUST know her current zone, the user's current zone, her approximate distance to the user, the objects in her current zone with their activities, and the names of all other zones.
- **FR-009**: The knowledge given to the resident MUST stay concise enough for large worlds by describing her current zone in detail and other zones only by name and short description.
- **FR-010**: Worlds without any zones or objects MUST keep today's behavior.

**Movement**

- **FR-011**: Residents MUST be able to go to a zone, go to an object or interaction spot, follow the user, and stop.
- **FR-012**: Residents MUST travel along routes that respect the same walkability rules as the user, including steps, drops and water.
- **FR-013**: Residents MUST visibly walk while moving, not glide.
- **FR-014**: When a destination is unreachable, the resident MUST stop trying and be told the destination could not be reached.
- **FR-015**: While following, the resident MUST stay near the user and keep up as the user moves.

**Using objects**

- **FR-016**: When a resident uses an interaction spot, she MUST end up at the spot's position and direction and hold the activity's pose until she leaves.
- **FR-017**: An interaction spot MUST be usable by only one resident at a time.
- **FR-018**: Residents MUST NOT pick up, carry or place objects. Activities are performed as poses at a spot or in a zone.
- **FR-019**: Activities without a dedicated pose MUST still complete using the resident's default stance.

**Action outcomes**

- **FR-020**: Every action the resident takes MUST report its outcome (completed, failed with a reason, or interrupted) back to her before she continues.
- **FR-021**: Actions naming things that do not exist MUST fail with a reason the resident is told about. They MUST NOT be silently ignored.
- **FR-022**: Each resident MUST keep a recent-activity history (what she did, where, when, and why she chose it) that informs her later decisions.

**Idle autonomy**

- **FR-023**: While the user is in the world and no conversation with her is open, the resident MUST decide whether to do something and what at a random moment between 10 seconds and 1 minute after her previous activity, or her decision to stay put, finishes.
- **FR-024**: Idle decisions MUST be made by the resident's own conversation model, using her personality, her current state, her recent-activity history and the activities available. They MUST NOT be random selection among options.
- **FR-025**: Staying put MUST be a valid idle decision.
- **FR-026**: The resident MUST be able to carry out multi-step activities, deciding each next step after learning the previous step's outcome.
- **FR-027**: Anything the user says to the resident MUST interrupt her current activity.
- **FR-028**: The user arriving in the world MUST be available to the resident as a moment she can respond to.
- **FR-029**: Idle decisions MUST be rate-limited per resident, and when several residents share a world their decisions MUST NOT all happen at the same moment.
- **FR-030**: Each self-chosen step MUST be added to the conversation as her reason in parentheses followed by the action in asterisks, stating both the intention and the concrete action (for example `(I want to forget about today for a while) *walks to the bar to get a drink*`).
- **FR-030a**: The same reason-and-action line MUST appear in a thought bubble above the resident in the world while that step is in progress.
- **FR-030b**: While a conversation with the resident is open, she MUST NOT take self-chosen actions. Actions the user asks for during conversation are still carried out. She MAY resume self-chosen actions as soon as the conversation closes.

**Presence**

- **FR-031**: Residents MUST take no actions and make no decisions while the user is not in the world.
- **FR-032**: When the user leaves, any in-progress activity MUST stop. On return the resident MUST be where she was left, and her unfinished plan MUST be discarded while her activity history is kept.

**Marker import**

- **FR-033**: Zones, objects and interaction spots MUST come only from markers embedded in the world's environment file. There is no manual authoring.
- **FR-034**: Markers MUST be read when the environment is uploaded or replaced, and replacing the environment MUST replace the world's zones, objects and spots with the new file's markers.
- **FR-034a**: Markers with missing or invalid required information MUST be skipped and reported to the world owner, while valid markers are still imported.
- **FR-035**: A world's zones and objects MUST belong to that world and MUST NOT be readable by other users.

**Conversation while moving**

- **FR-038**: Opening a conversation with a resident MUST NOT pause the world. The user and all residents MUST keep moving and acting.
- **FR-039**: The conversation MUST stay visible while the user moves.
- **FR-040**: The user MUST explicitly focus the message box to type. While it is focused, keys MUST go only to the message; otherwise they MUST go only to movement. Sending a message MUST return keys to movement.
- **FR-041**: The user MUST be able to turn on hands-free voice mode in the world, with the same transcription and spoken replies as the regular chat, without having to stop moving.
- **FR-042**: The world MUST show whether voice mode is listening, processing or speaking.
- **FR-043**: The resident's spoken replies MUST sound from her position in the world, with loudness depending on her distance from the user.
- **FR-044**: The microphone MUST stop listening when the user ends the conversation, turns voice mode off, or leaves the world.
- **FR-045**: The user MUST have at most one open conversation at a time, with exactly one resident. While it is open, starting a conversation with another resident MUST NOT be possible until the current one ends.
- **FR-046**: Every typed and spoken message MUST go only to the resident of the open conversation, regardless of which residents are nearby.
- **FR-047**: The resident of the open conversation MUST be clearly identified, both in the conversation view and in the world.
- **FR-048**: A conversation MUST end automatically when the user and the resident are farther apart than a set distance. The user MUST be warned before that distance is reached.
- **FR-049**: The user MUST be able to end a conversation deliberately with a control that is separate from leaving the message box.

**Finding residents**

- **FR-050**: Every resident MUST show a name tag above her that stays readable at any distance and is visible through walls and furniture.
- **FR-051**: The name tag of the resident in the open conversation MUST be highlighted distinctly from all others.
- **FR-052**: When the resident in the open conversation is off-screen, an indicator at the screen edge MUST point toward her.
- **FR-053**: The user MUST be able to show a corner minimap while moving and open a full-screen map on demand.
- **FR-054**: The map MUST show a top-down view generated from the world's environment, zone names, the user's position and facing, and every resident's position and name, updating continuously.
- **FR-055**: The map marker of the resident in the open conversation MUST be highlighted.
- **FR-056**: Overlapping name tags and map markers MUST remain individually readable.

**Direct commands**

- **FR-036**: The user MUST be able to ask the resident in conversation to follow or stop, and she decides how to respond in character.
- **FR-037**: The user MUST also have direct controls for "follow me" and "stop" that the resident always obeys. The resident MUST be told when a direct control was used so she can react to it.

### Key Entities

- **Zone**: A named area of a world with a description, a ground outline, a vertical range, an optional parent zone, an entry point, optional zone-level activities, and a private flag.
- **World object**: A named, described thing at a position in a world. It belongs to whichever zone contains it, and offers interaction spots.
- **Interaction spot**: A precise place on an object with a facing direction, the activities it supports, and at most one occupying resident at a time.
- **Activity**: Something a resident can do at a spot or in a zone, with an optional pose.
- **Resident state**: A resident's current position, zone, current activity and occupied spot within a world session.
- **Activity history entry**: A record of what a resident did, where, when, the outcome, and the stated reason.
- **World map**: The top-down view generated from a world's environment, used for the in-world map.
- **Environment marker**: Information embedded in the environment file that defines a zone, object or interaction spot, following a documented format.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In a marked world, the resident correctly answers where she is, where the user is, and what she can do nearby in at least 9 of 10 attempts.
- **SC-002**: When sent to any reachable zone or object in the penthouse, the resident arrives in at least 95% of attempts without getting stuck, crossing water, or passing through walls.
- **SC-003**: The resident starts moving within 2 seconds of agreeing to go somewhere.
- **SC-004**: When using an interaction spot, the resident ends up visibly aligned with it (seated on the seat, lying on the lounger) in at least 9 of 10 attempts.
- **SC-005**: Over a 20-minute unaddressed session, the resident makes at least 3 self-chosen activity decisions, and no activity repeats back-to-back unless she states a reason.
- **SC-006**: Zero resident decisions or actions happen while the user is not in the world.
- **SC-007**: Every zone, object and spot embedded in the penthouse environment is available to residents after a single upload, with no manual steps.
- **SC-010**: In a world with five residents, a user can locate any named resident within 10 seconds using the name tags or the map.
- **SC-009**: For every self-chosen step, the user can tell from the thought bubble alone both what the resident is doing and why.

## Assumptions

- The penthouse environment will ship with built-in zone and object markers for every room and seat, and serves as the reference world for testing.
- The marker format is documented so environments can be generated with markers or authored with them in 3D modeling tools; worlds without markers, such as the renaissance faire today, need their environment re-exported with markers to gain zones and objects.
- Residents interacting with each other (conversation or shared activities) is out of scope; residents only avoid sharing a spot.
- Swimming, as floating or diving, is out of scope; residents in the pool wade on the pool floor like the user.
- Per-activity poses reuse the existing pose library. Activities whose pose is not yet in an assistant's library fall back to the default stance.
- Idle decisions use the resident's own conversation model; at one decision every 10 seconds to 1 minute this is roughly 100 model calls per hour per idle resident, which is accepted.
- Only the world owner's own world sessions drive resident behavior; worlds visited by multiple users at once are out of scope.
- Voice mode in the world reuses the existing transcription and speech settings of the resident's assistant; no new voice providers are needed.
- The distance at which a conversation ends has a sensible default, well beyond the distance needed to start one, that the owner can change later.
