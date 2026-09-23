# Feature Specification: Resident World Agency

**Feature Branch**: `015-resident-world-agency`

**Created**: 2026-09-22

**Status**: Draft

**Input**: User description: "Resident AIs understand world zones and objects, move with purpose, hold poses at interaction spots, and choose idle activities through an agent loop that runs only while the user is in the world."

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

### User Story 2 - The resident goes where she means to go (Priority: P2)

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

### User Story 3 - The resident uses things in the world (Priority: P3)

Objects offer interaction spots with an activity: sit on the piano bench, lie down on the bed, recline on a pool lounger, get a drink at the bar, play the Rhodes. When the resident uses one, she walks to that exact spot, faces the right way, and holds the matching pose until she moves on. Some activities give her something to hold, like a glass after getting a drink, and that state is part of what she knows about herself.

**Why this priority**: This is the payoff that makes the world feel inhabited, but it needs the resident to know the world (P1) and reach the spot (P2) first.

**Independent Test**: Ask the resident to lie down on a pool lounger. She walks to it, lies down aligned with it, and stays reclined until asked to get up or until she chooses another activity.

**Acceptance Scenarios**:

1. **Given** a free pool lounger, **When** the resident chooses to recline on it, **Then** she ends up lying on it, aligned with its position and direction, and stays there.
2. **Given** the resident is reclining, **When** she chooses another activity, **Then** she gets up and leaves the lounger free.
3. **Given** another resident already occupies a lounger, **When** the resident tries to use the same lounger, **Then** she is told it is taken and can pick another one.
4. **Given** the resident gets a drink at the bar, **When** she walks elsewhere, **Then** she visibly carries the drink and knows she is holding it.
5. **Given** an activity with no dedicated pose, **When** the resident performs it, **Then** she still completes it with her default stance, and nothing fails.

---

### User Story 4 - The resident chooses what to do when left alone (Priority: P4)

While the user is in the world but not in conversation with her, the resident periodically decides for herself whether to do something and what. While the user is talking with her, she makes no self-chosen actions; she gives the user her attention and only acts when the user asks her to. Her choice comes from her personality, her mood, what she has done recently and what the world offers. She is not picked at random from a list. An extroverted resident may go get a drink; one who loves music may wander into the studio; one who just had a drink will choose something else. She can carry out a multi-step activity ("get a drink, take it to the pool, lie on a lounger"). She adjusts the plan when a step fails, and drops it when the user speaks to her. Staying where she is counts as a real choice.

Every self-chosen step is recorded in the conversation using roleplay convention: her reason as a thought in parentheses and the step as an action in asterisks. For example: `(I want to forget about today for a while) *walks to the bar to get a drink*`. The same line appears in a thought bubble above her in the world, so the user can see what she is doing and why at a glance.

**Why this priority**: This makes the world feel alive without the user directing everything. It builds on all the previous stories and carries the highest ongoing cost, so it comes last.

**Independent Test**: Enter the penthouse and leave the resident unaddressed for several minutes. She makes at least one self-chosen, personality-consistent activity choice, carries it out across multiple steps, and her recent-activity history shows what she did and why.

**Acceptance Scenarios**:

1. **Given** the user is in the world and has not spoken to the resident for a while, **When** her idle period ends, **Then** she decides on an activity, or on staying put, based on her personality and recent history.
2. **Given** the resident had a drink a few minutes ago, **When** she next decides what to do, **Then** her choice takes that recent activity into account.
3. **Given** the resident is partway through a multi-step activity, **When** the user speaks to her, **Then** she stops the activity and gives the user her attention.
4. **Given** a step of her plan fails (the lounger is taken), **When** she learns the outcome, **Then** she adapts the plan or picks something else.
5. **Given** the user leaves the world, **When** the resident is mid-activity or idle, **Then** she makes no further decisions and takes no further actions.
6. **Given** the user comes back to the world, **When** the world loads, **Then** the resident is where the user left her, and her first decision can respond to the user's arrival.
7. **Given** the resident decides to get a drink because she wants to forget her day, **When** she starts, **Then** the conversation shows a line like `(I want to forget about today for a while) *walks to the bar to get a drink*`, and a thought bubble above her shows the same line.
8. **Given** the user is in conversation with the resident, **When** her idle period would otherwise end, **Then** she makes no self-chosen action.

---

### User Story 5 - World owners mark zones and objects in an intuitive editor (Priority: P5)

A world owner marks up a world without technical knowledge. The editor shows a top-down map of the world, generated from the environment itself. The owner can optionally lay an uploaded blueprint image over it and align it to the map. On the map, the owner draws zones as rectangles or free outlines and names and describes them. The owner places objects on the map or directly in the 3D view and adds interaction spots with a facing direction and activities. A preview of the resident standing, sitting or lying at each spot shows whether it lines up. The editor warns about mistakes the owner cannot easily see: a spot the resident cannot walk to, a zone with no entry point, or overlapping zones that are not nested. Markers already contained in an uploaded environment are imported automatically and can be edited like hand-made ones.

**Why this priority**: Stories 1–4 can be demonstrated on the penthouse, which ships its own markers. The editor is what extends the feature to every other world, like the renaissance faire. It is also the largest single piece of work, so it comes after the behavior it configures has been proven.

**Independent Test**: In an unmarked world, draw a "Jousting field" zone on the map and add a "Grandstand bench" object with a sit spot. The spot preview shows the resident seated on the bench. Entering the world, the resident knows the jousting field exists and can sit on the bench.

**Acceptance Scenarios**:

1. **Given** a world with no markers, **When** the owner opens the editor, **Then** a top-down map of the world's environment is shown without the owner uploading anything.
2. **Given** the owner uploads a blueprint image, **When** they align it to the map by moving, scaling and rotating it, **Then** it stays aligned under the map on later visits to the editor.
3. **Given** the map view, **When** the owner draws a rectangle or free outline and names it, **Then** a zone is created with that area, and the resident knows it on the next visit.
4. **Given** the owner places a spot with a "sit" activity, **When** they view its preview, **Then** they see the resident seated at that position and facing that direction before saving.
5. **Given** a spot the resident cannot walk to, **When** the owner saves it, **Then** the editor warns that it is unreachable.
6. **Given** an uploaded environment containing markers, **When** the world is created or its environment replaced, **Then** those markers appear in the editor as the world's zones and objects.
7. **Given** any change in the editor, **When** the owner undoes it, **Then** the previous state is restored.

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
- **FR-018**: Activities MAY give the resident a held item (a drink), which MUST be shown on her and included in what she knows about herself until she puts it down or finishes it.
- **FR-019**: Activities without a dedicated pose MUST still complete using the resident's default stance.

**Action outcomes**

- **FR-020**: Every action the resident takes MUST report its outcome (completed, failed with a reason, or interrupted) back to her before she continues.
- **FR-021**: Actions naming things that do not exist MUST fail with a reason the resident is told about. They MUST NOT be silently ignored.
- **FR-022**: Each resident MUST keep a recent-activity history (what she did, where, when, and why she chose it) that informs her later decisions.

**Idle autonomy**

- **FR-023**: While the user is in the world and not actively engaging the resident, she MUST periodically decide whether to do something and what.
- **FR-024**: Idle decisions MUST be made by the resident's model, using her personality, her current state, her recent-activity history and the activities available. They MUST NOT be random selection among options.
- **FR-025**: Staying put MUST be a valid idle decision.
- **FR-026**: The resident MUST be able to carry out multi-step activities, deciding each next step after learning the previous step's outcome.
- **FR-027**: Anything the user says to the resident MUST interrupt her current activity.
- **FR-028**: The user arriving in the world MUST be available to the resident as a moment she can respond to.
- **FR-029**: Idle decisions MUST be rate-limited per resident, and when several residents share a world their decisions MUST NOT all happen at the same moment.
- **FR-030**: Each self-chosen step MUST be added to the conversation as her reason in parentheses followed by the action in asterisks, stating both the intention and the concrete action (for example `(I want to forget about today for a while) *walks to the bar to get a drink*`).
- **FR-030a**: The same reason-and-action line MUST appear in a thought bubble above the resident in the world while that step is in progress.
- **FR-030b**: While the user is in conversation with the resident, she MUST NOT take self-chosen actions. Actions the user asks for during conversation are still carried out.

**Presence**

- **FR-031**: Residents MUST take no actions and make no decisions while the user is not in the world.
- **FR-032**: When the user leaves, any in-progress activity MUST stop. On return the resident MUST be where she was left, and her unfinished plan MUST be discarded while her activity history is kept.

**Authoring**

- **FR-033**: World owners MUST be able to create, edit and delete zones, objects and interaction spots for their worlds in the world editor.
- **FR-033a**: The editor MUST show a top-down map of the world generated from its environment, with no upload required.
- **FR-033b**: Owners MAY upload a blueprint image and align it under the map by moving, scaling and rotating it. The alignment MUST persist.
- **FR-033c**: Owners MUST be able to draw zones on the map as rectangles or free outlines and set each zone's vertical range, parent zone, entry point, activities and private flag.
- **FR-033d**: Owners MUST be able to place objects and interaction spots either on the map or by clicking in the 3D view, and set each spot's facing direction and activities.
- **FR-033e**: The editor MUST preview the resident's pose at a spot before it is saved.
- **FR-033f**: The editor MUST warn when a spot or zone entry point is unreachable on foot, and when zones overlap without being nested.
- **FR-033g**: The editor MUST support undoing and redoing changes within an editing session.
- **FR-034**: Zone and object markers contained in an uploaded environment file MUST be imported as the world's zones and objects when the environment is uploaded or replaced.
- **FR-035**: Zones and objects MUST belong to their world and MUST NOT be readable or editable by other users.

**Direct commands**

- **FR-036**: The user MUST be able to ask the resident in conversation to follow or stop, and she decides how to respond in character.
- **FR-037**: The user MUST also have direct controls for "follow me" and "stop" that the resident always obeys. The resident MUST be told when a direct control was used so she can react to it.

### Key Entities

- **Zone**: A named area of a world with a description, a ground outline, a vertical range, an optional parent zone, an entry point, optional zone-level activities, and a private flag.
- **World object**: A named, described thing at a position in a world. It belongs to whichever zone contains it, and offers interaction spots.
- **Interaction spot**: A precise place on an object with a facing direction, the activities it supports, and at most one occupying resident at a time.
- **Activity**: Something a resident can do at a spot or in a zone, with an optional pose and an optional held item.
- **Resident state**: A resident's current position, zone, current activity, held item and occupied spot within a world session.
- **Activity history entry**: A record of what a resident did, where, when, the outcome, and the stated reason.
- **World map**: The top-down view of a world's environment used for authoring, with an optional uploaded blueprint image and its alignment.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In a marked world, the resident correctly answers where she is, where the user is, and what she can do nearby in at least 9 of 10 attempts.
- **SC-002**: When sent to any reachable zone or object in the penthouse, the resident arrives in at least 95% of attempts without getting stuck, crossing water, or passing through walls.
- **SC-003**: The resident starts moving within 2 seconds of agreeing to go somewhere.
- **SC-004**: When using an interaction spot, the resident ends up visibly aligned with it (seated on the seat, lying on the lounger) in at least 9 of 10 attempts.
- **SC-005**: Over a 20-minute unaddressed session, the resident makes at least 3 self-chosen activity decisions, and no activity repeats back-to-back unless she states a reason.
- **SC-006**: Zero resident decisions or actions happen while the user is not in the world.
- **SC-007**: A world owner can mark a new zone with one sittable object and see the resident use it in under 5 minutes.
- **SC-008**: A first-time owner can mark all rooms of a ten-room world in under 20 minutes without instructions.
- **SC-009**: For every self-chosen step, the user can tell from the thought bubble alone both what the resident is doing and why.

## Assumptions

- The penthouse environment will ship with built-in zone and object markers for every room and seat, and serves as the reference world for testing Stories 1–4.
- Residents interacting with each other (conversation or shared activities) is out of scope; residents only avoid sharing a spot.
- Swimming, as floating or diving, is out of scope; residents in the pool wade on the pool floor like the user.
- Per-activity poses reuse the existing pose library. Activities whose pose is not yet in an assistant's library fall back to the default stance.
- Idle decisions use the resident's own configured model, and the idle period and rate limits have sensible defaults the owner can change later.
- Only the world owner's own world sessions drive resident behavior; worlds visited by multiple users at once are out of scope.
- "In conversation" means the user has messaged the resident recently; the length of that window has a sensible default the owner can change later.
