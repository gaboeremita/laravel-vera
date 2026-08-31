# Feature Specification: Connection Node World

**Feature Branch**: `007-connection-node-world`

**Created**: 2026-08-30

**Status**: Draft

**Input**: User description: "Add the Connection Node: a desktop-first, single-room 3D world accessed from the Assistants area through a Worlds section. Every existing waifu is persistently present as an embodied character. The user can explore with walking controls, collide with room furniture and boundaries, see waifus idle and roam within safe zones, approach a waifu, and press C to open that waifu's existing conversation. The experience is a polished futuristic sci-fi social simulation, distinct from the conventional conversation page. The implementation plan must separate user-owned asset sourcing and approval from agent-owned application work. V1 excludes mobile, multiple rooms, generated backgrounds, and open-world features."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Enter the Connection Node (Priority: P1)

An authenticated user opens the Assistants area, selects the Connection Node from a Worlds section, and enters a dedicated, immersive world view instead of a normal conversation page.

**Why this priority**: The world must be discoverable and clearly distinct from conventional chat before its social interactions can provide value.

**Independent Test**: From the Assistants area, select the Connection Node and verify that the user enters the single-room world with a visible starting position and clear desktop controls.

**Acceptance Scenarios**:

1. **Given** an authenticated user is viewing the Assistants area, **When** they select the Connection Node world card, **Then** they enter the Connection Node without selecting a conversation first.
2. **Given** the Connection Node is loading, **When** required world content is not yet ready, **Then** the user sees a clear loading state and is not shown a broken or empty interaction surface.
3. **Given** the user is in the Connection Node, **When** they leave the world, **Then** they can return to the Assistants area without losing or changing any existing conversations.

---

### User Story 2 - Explore the room (Priority: P1)

The user explores the single Connection Node room from a first-person perspective using a laptop keyboard and mouse. Walls and placed furniture make the room feel physical by preventing the user from walking through them.

**Why this priority**: Movement and believable boundaries are the core difference between this social world and the existing portrait-based chat experience.

**Independent Test**: Enter the room, move in every supported direction, look around with the mouse, and attempt to cross a wall and each collision-marked furnishing.

**Acceptance Scenarios**:

1. **Given** the user has entered the Connection Node, **When** they use the documented movement and look controls, **Then** they can walk and look around the room from their point of view.
2. **Given** the user walks into a wall or collision-marked furnishing, **When** they continue moving toward it, **Then** they remain outside the object and can move away normally.
3. **Given** the user pauses or releases world controls, **When** they move the pointer outside the world interaction surface, **Then** they can regain normal browser interaction without becoming trapped in the world controls.

---

### User Story 3 - Meet and chat with a waifu (Priority: P1)

Every eligible assistant is visibly and persistently represented by its waifu in the Connection Node. When the user approaches a waifu, an interaction prompt identifies the available chat action; pressing `C` starts or resumes that waifu's existing conversation from an in-world chat experience.

**Why this priority**: Embodied, proximity-based conversation is the defining social purpose of the Connection Node.

**Independent Test**: Approach multiple waifus, confirm that only nearby waifus offer the chat prompt, use `C`, exchange messages, leave the chat, and confirm that each conversation remains associated with the correct assistant.

**Acceptance Scenarios**:

1. **Given** one or more eligible assistants exist, **When** the user enters the Connection Node, **Then** every eligible waifu is present in the room at its assigned persistent location or roaming area.
2. **Given** the user is outside a waifu's interaction range, **When** they press `C`, **Then** no conversation opens accidentally.
3. **Given** the user is within a waifu's interaction range, **When** they press `C`, **Then** an in-world chat experience opens for that waifu and identifies the waifu being addressed.
4. **Given** the user has an existing conversation with the selected waifu, **When** the chat experience opens, **Then** they can continue that conversation without accessing another assistant's history.
5. **Given** the user has no prior conversation with the selected waifu, **When** they begin chatting, **Then** a new conversation is created using the application's existing assistant behavior.
6. **Given** the user closes the in-world chat experience, **When** the world becomes active again, **Then** they remain in the Connection Node near the waifu they were addressing.

---

### User Story 4 - Experience a living shared room (Priority: P2)

While the user explores, waifus remain visibly present, play idle behavior, and may move within their assigned safe roaming areas. The room has a polished futuristic sci-fi appearance supplied through approved environment and furnishing assets.

**Why this priority**: Persistent presence and restrained movement make the Connection Node feel inhabited without expanding v1 into a multi-room or open-world simulation.

**Independent Test**: Remain in the Connection Node long enough to observe each waifu's idle state and observe a roaming waifu move without passing through boundaries, furniture, or another waifu's protected interaction space.

**Acceptance Scenarios**:

1. **Given** a waifu has a safe roaming area, **When** the Connection Node is active, **Then** the waifu may move within that area and remains inside its boundaries.
2. **Given** a waifu has no safe roaming area, **When** the Connection Node is active, **Then** the waifu remains at its assigned location while continuing idle behavior.
3. **Given** a waifu is engaged in chat, **When** the user is addressing that waifu, **Then** the waifu remains available for that interaction and does not wander away.
4. **Given** an approved environment asset or furnishing asset is unavailable, **When** the user enters the Connection Node, **Then** the user receives a clear recoverable error rather than a misleading interactive world.

### Edge Cases

- What happens when no assistants are eligible for embodiment? The Connection Node remains enterable and clearly explains that no waifus are available to meet; it must not fail to load.
- What happens when many eligible assistants cannot be placed safely in the initial room layout? The room must preserve non-overlapping positions and usable movement paths; the system must not stack waifus or place them inside furniture.
- What happens when a waifu's character presentation cannot load? The user sees a visible fallback representation for that waifu, and the proximity chat interaction remains available.
- What happens when the active chat cannot be created or resumed? The user receives a clear error and remains in the Connection Node without losing their position or opening a chat for a different waifu.
- What happens when the user changes browser focus while walking? Movement stops until the user intentionally returns to the world controls.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST add a Worlds section to the Assistants area containing a card for the Connection Node.
- **FR-002**: The system MUST provide a dedicated Connection Node experience that is distinct from conventional conversation navigation.
- **FR-003**: The system MUST present the Connection Node as one bounded room in v1.
- **FR-004**: The system MUST support first-person walking and mouse-based looking for laptop and desktop users.
- **FR-005**: The system MUST provide a visible way to release world input and return to normal browser interaction.
- **FR-006**: The system MUST prevent the user from moving through room boundaries and furnishings designated as solid.
- **FR-007**: The system MUST make every eligible assistant persistently present as a waifu in the Connection Node for the duration of a visit.
- **FR-008**: The system MUST give each waifu an assigned non-overlapping location and, where configured, a bounded roaming area.
- **FR-009**: The system MUST support idle behavior for every waifu and optional bounded roaming behavior that does not cross room boundaries, collision-marked furnishings, or protected interaction positions.
- **FR-010**: The system MUST show a nearby waifu's chat prompt only while the user is within that waifu's interaction range.
- **FR-011**: The system MUST open the nearby waifu's chat experience when the user presses `C` and MUST prevent that input from opening a chat while no waifu is in range.
- **FR-012**: The in-world chat experience MUST use the selected waifu's existing assistant identity, conversation behavior, and conversation history while preserving ownership boundaries between assistants.
- **FR-013**: The system MUST let the user leave an in-world chat and resume exploring from the same Connection Node visit.
- **FR-014**: The system MUST preserve the Connection Node's polished futuristic sci-fi visual direction while allowing the approved room and furnishing assets to be selected separately from application implementation.
- **FR-015**: The delivery plan MUST include explicit user-owned tasks for sourcing, licensing, approving, and handing off room and furnishing assets, together with agent-owned tasks for validating, integrating, and placing those assets.
- **FR-016**: The system MUST clearly communicate loading and recoverable failures for the room, character presentation, and chat without corrupting existing conversations.
- **FR-017**: The system MUST stop movement when the world loses browser focus.
- **FR-018**: The system MUST exclude mobile controls, additional rooms, generated environmental backgrounds, and open-world traversal from v1.

### Key Entities *(include if feature involves data)*

- **Connection Node**: The single canonical room users can enter from the Worlds section; it defines the bounded visit and visual identity of the v1 world.
- **World Placement**: A waifu's persisted position, orientation, and optional safe roaming area within the Connection Node.
- **Interaction Zone**: The nearby space around a waifu in which the user can see and activate the chat prompt.
- **World Asset**: An approved room, furnishing, or ambient asset used to present the Connection Node, including its source and suitability for the intended use.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can enter the Connection Node from the Assistants area in no more than two selections.
- **SC-002**: A user can walk from the starting position to any waifu's interaction zone in the initial room without passing through a boundary or collision-marked furnishing.
- **SC-003**: In a room with up to 10 eligible waifus, every waifu is visible or reachable through unobstructed walkable paths during a Connection Node visit.
- **SC-004**: At least 95% of successful nearby interaction attempts open the intended waifu's conversation after a single press of `C`.
- **SC-005**: A user can leave an in-world chat and resume exploration in under 2 seconds without losing their Connection Node position.
- **SC-006**: The Connection Node remains usable throughout a 15-minute exploration session without unbounded visual degradation or loss of chat availability.
- **SC-007**: A user can distinguish the Connection Node from the conventional conversation interface without instructions, based on its Worlds entry point and immersive room presentation.

## Assumptions

- The feature reuses the application's existing authenticated user and assistant ownership rules.
- An assistant is eligible for embodiment when it has a usable waifu/3D character presentation; assistants that cannot be embodied do not block use of the world.
- The initial Connection Node has a layout sized to accommodate the currently expected assistant count, up to 10 embodied waifus for v1 validation.
- The user will source and approve the environment and furnishing assets; the implementation work begins after those assets are available and validated for the project.
- The final visual direction is polished futuristic sci-fi, while the exact asset pack and furniture selection remain a user decision.
- Waifu wandering is ambient behavior only; it does not create autonomous conversations, alter assistant memory, or cause waifus to leave the Connection Node.
- Existing conversations, voice behavior, character expressions, and pose behavior remain the source of truth for in-world chat.
- V1 targets laptop and desktop browsers with a keyboard and mouse; mobile and touch interaction are out of scope.
