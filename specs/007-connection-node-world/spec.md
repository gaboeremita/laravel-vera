# Feature Specification: Connection Node World

**Feature Branch**: `007-connection-node-world`

**Created**: 2026-08-30

**Status**: Draft

**Input**: User description: "Add the Connection Node: a desktop-first, single-room 3D world accessed from the Assistants area through a Worlds section. Eligible assistants are persistently present as embodied characters. The user can explore with walking controls, collide with room furniture and boundaries, observe assistants idle and roam within safe zones, approach an assistant, and press C to open that assistant's existing conversation. The experience is a polished futuristic sci-fi social simulation, distinct from the conventional conversation page. The implementation plan must separate user-owned asset sourcing and approval from agent-owned application work. V1 excludes mobile, multiple rooms, generated backgrounds, and open-world features."

## Clarifications

### Session 2026-08-30

- Q: How should world NPCs access setting knowledge? → A: World NPCs reuse the existing assistant archive access so they can respond knowledgeably about the world.
- Q: How should the Connection Node manage unused character work? → A: Character animation, roaming, and related updates are reduced when an assistant is not visible or nearby, then restored before the assistant can be observed or interacted with.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Enter the Connection Node (Priority: P1)

An authenticated user opens the Assistants area, selects the Connection Node from a Worlds section, and enters a dedicated, immersive world view instead of a normal conversation page. A graceful transition and loader make clear that the room and its residents are being prepared.

**Why this priority**: The world must be discoverable and clearly distinct from conventional chat before its social interactions can provide value.

**Independent Test**: From the Assistants area, select the Connection Node and verify that the user enters the single-room world with a visible starting position and clear desktop controls.

**Acceptance Scenarios**:

1. **Given** an authenticated user is viewing the Assistants area, **When** they select the Connection Node world card, **Then** they enter the Connection Node without selecting a conversation first.
2. **Given** the user selects the Connection Node, **When** the world is preparing, **Then** the user sees a graceful transition and a clear loading state rather than a broken or empty interaction surface.
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

### User Story 3 - Meet and chat with an assistant (Priority: P1)

Every eligible assistant is visibly and persistently represented by its 3D character in the Connection Node. When the user approaches an assistant, an interaction prompt identifies the available chat action; pressing `C` starts or resumes that assistant's existing conversation from an in-world chat experience.

**Why this priority**: Embodied, proximity-based conversation is the defining social purpose of the Connection Node.

**Independent Test**: Approach multiple assistants, confirm that only nearby assistants offer the chat prompt, use `C`, exchange messages, leave the chat, and confirm that each conversation remains associated with the correct assistant.

**Acceptance Scenarios**:

1. **Given** one or more eligible assistants exist, **When** the user enters the Connection Node, **Then** every eligible assistant is present in the room at its assigned persistent location or roaming area.
2. **Given** the user is outside an assistant's interaction range, **When** they press `C`, **Then** no conversation opens accidentally.
3. **Given** the user is within an assistant's interaction range, **When** they press `C`, **Then** an in-world chat experience opens for that assistant and identifies the assistant being addressed.
4. **Given** the user has an existing conversation with the selected assistant, **When** the chat experience opens, **Then** they can continue that conversation without accessing another assistant's history.
5. **Given** the user has no prior conversation with the selected assistant, **When** they begin chatting, **Then** a new conversation is created using the application's existing assistant behavior.
6. **Given** the user closes the in-world chat experience, **When** the world becomes active again, **Then** they remain in the Connection Node near the assistant they were addressing.

---

### User Story 4 - Experience a living shared room (Priority: P2)

While the user explores, assistants remain visibly present, play idle behavior, and may move within their assigned safe roaming areas. The room has a polished futuristic sci-fi appearance supplied through approved environment and furnishing assets.

**Why this priority**: Persistent presence and restrained movement make the Connection Node feel inhabited without expanding v1 into a multi-room or open-world simulation.

**Independent Test**: Remain in the Connection Node long enough to observe each assistant's idle state and observe a roaming assistant move without passing through boundaries, furniture, or another assistant's protected interaction space.

**Acceptance Scenarios**:

1. **Given** an assistant has a safe roaming area, **When** the Connection Node is active, **Then** the assistant may move within that area and remains inside its boundaries.
2. **Given** an assistant has no safe roaming area, **When** the Connection Node is active, **Then** the assistant remains at its assigned location while continuing idle behavior.
3. **Given** an assistant is engaged in chat, **When** the user is addressing that assistant, **Then** the assistant remains available for that interaction and does not wander away.
4. **Given** an approved environment asset or furnishing asset is unavailable, **When** the user enters the Connection Node, **Then** the user receives a clear recoverable error rather than a misleading interactive world.

---

### User Story 5 - Configure residents and NPCs (Priority: P2)

The user opens a dedicated Connection Node editor to choose eligible 3D-avatar assistants, configure their presence in the room, and add or edit world NPCs. An NPC has the same character, animation, prompt, conversation, and archive knowledge capabilities as an assistant, while the editor presents only the world-relevant controls.

**Why this priority**: The Connection Node needs a professional, maintainable way to curate its residents without duplicating the existing character and AI configuration systems.

**Independent Test**: Open the Connection Node editor, select an eligible assistant, add an NPC with a name, prompt, 3D character model, and poses, then verify that both can be placed and interacted with in the world.

**Acceptance Scenarios**:

1. **Given** the user is editing the Connection Node, **When** they choose residents, **Then** only assistants with usable 3D character models are selectable and unavailable assistants explain why they cannot be selected.
2. **Given** the user creates an NPC, **When** they provide its name, prompt, 3D character model, and pose configuration, **Then** the NPC can be added as a resident of the Connection Node.
3. **Given** an NPC is configured with an archive, **When** the user chats with that NPC, **Then** its responses use the archive's relevant world knowledge through the same behavior available to assistants.
4. **Given** the user changes a resident's stationary or roaming behavior, **When** they save the world configuration, **Then** the new behavior is reflected on the next Connection Node visit.

### Edge Cases

- What happens when no assistants are eligible for embodiment? The Connection Node remains enterable and clearly explains that no assistants are available to meet; it must not fail to load.
- What happens when many eligible assistants cannot be placed safely in the initial room layout? The room must preserve non-overlapping positions and usable movement paths; the system must not stack assistants or place them inside furniture.
- What happens when an assistant's character presentation cannot load? The user sees a visible fallback representation for that assistant, and the proximity chat interaction remains available.
- What happens when the active chat cannot be created or resumed? The user receives a clear error and remains in the Connection Node without losing their position or opening a chat for a different assistant.
- What happens when the user changes browser focus while walking? Movement stops until the user intentionally returns to the world controls.
- What happens when an assistant is outside the user's view and interaction range? Its nonessential animation and roaming work is reduced without making it unavailable when the user returns.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST add a Worlds section to the Assistants area containing a card for the Connection Node.
- **FR-002**: The system MUST provide a dedicated Connection Node experience that is distinct from conventional conversation navigation.
- **FR-003**: The system MUST present the Connection Node as one bounded room in v1.
- **FR-004**: The system MUST support first-person walking and mouse-based looking for laptop and desktop users.
- **FR-005**: The system MUST provide a visible way to release world input and return to normal browser interaction.
- **FR-006**: The system MUST prevent the user from moving through room boundaries and furnishings designated as solid.
- **FR-007**: The system MUST make every eligible assistant persistently present as a 3D character in the Connection Node for the duration of a visit.
- **FR-008**: The system MUST give each assistant an assigned non-overlapping location and, where configured, a bounded roaming area.
- **FR-009**: The system MUST support idle behavior for every assistant and optional bounded roaming behavior that does not cross room boundaries, collision-marked furnishings, or protected interaction positions.
- **FR-010**: The system MUST show a nearby assistant's chat prompt only while the user is within that assistant's interaction range.
- **FR-011**: The system MUST open the nearby assistant's chat experience when the user presses `C` and MUST prevent that input from opening a chat while no assistant is in range.
- **FR-012**: The in-world chat experience MUST use the selected assistant's existing identity, conversation behavior, and conversation history while preserving ownership boundaries between assistants.
- **FR-013**: The system MUST let the user leave an in-world chat and resume exploring from the same Connection Node visit.
- **FR-014**: The system MUST preserve the Connection Node's polished futuristic sci-fi visual direction while allowing the approved room and furnishing assets to be selected separately from application implementation.
- **FR-015**: The delivery plan MUST include explicit user-owned tasks for sourcing, licensing, approving, and handing off room and furnishing assets, together with agent-owned tasks for validating, integrating, and placing those assets.
- **FR-016**: The system MUST clearly communicate loading and recoverable failures for the room, character presentation, and chat without corrupting existing conversations.
- **FR-017**: The system MUST stop movement when the world loses browser focus.
- **FR-018**: The system MUST exclude mobile controls, additional rooms, generated environmental backgrounds, and open-world traversal from v1.
- **FR-019**: The system MUST provide a Connection Node editor accessible from the Worlds section.
- **FR-020**: The Connection Node editor MUST let the user select only assistants with usable 3D character models as world residents and MUST identify assistants that are ineligible.
- **FR-021**: The Connection Node editor MUST let the user create, edit, and remove NPC residents with a name, a single character prompt, a 3D character model, pose configuration, placement, and stationary or roaming behavior.
- **FR-022**: NPC residents MUST reuse the existing assistant character, animation, prompt, conversation, and archive knowledge behavior rather than introduce a separate NPC interaction system.
- **FR-023**: The system MUST reduce nonessential animation, roaming, and related visual-update work for assistants outside the user's view and interaction range, while restoring their expected behavior before they become visible or interactable.
- **FR-024**: The system MUST release Connection Node resources when the user leaves the world so repeated visits do not accumulate unused room or character resources.

### Key Entities *(include if feature involves data)*

- **Connection Node**: The single canonical room users can enter from the Worlds section; it defines the bounded visit and visual identity of the v1 world.
- **World Placement**: A resident's persisted position, orientation, and optional safe roaming area within the Connection Node.
- **World NPC**: A world-managed assistant resident configured through the Connection Node editor with a reduced management surface, while reusing the existing character, conversation, and archive knowledge capabilities.
- **Interaction Zone**: The nearby space around a resident in which the user can see and activate the chat prompt.
- **World Asset**: An approved room, furnishing, or ambient asset used to present the Connection Node, including its source and suitability for the intended use.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can enter the Connection Node from the Assistants area in no more than two selections.
- **SC-002**: A user can walk from the starting position to any assistant's interaction zone in the initial room without passing through a boundary or collision-marked furnishing.
- **SC-003**: In a room with up to 10 eligible assistants, every assistant is visible or reachable through unobstructed walkable paths during a Connection Node visit.
- **SC-004**: At least 95% of successful nearby interaction attempts open the intended assistant's conversation after a single press of `C`.
- **SC-005**: A user can leave an in-world chat and resume exploration in under 2 seconds without losing their Connection Node position.
- **SC-006**: The Connection Node remains usable throughout a 15-minute exploration session without unbounded visual degradation or loss of chat availability.
- **SC-007**: A user can distinguish the Connection Node from the conventional conversation interface without instructions, based on its Worlds entry point and immersive room presentation.
- **SC-008**: During a 15-minute visit with up to 10 residents, an assistant outside the user's view and interaction range does not continue visible roaming or interaction behavior until the user can observe or approach that assistant again.

## Assumptions

- The feature reuses the application's existing authenticated user and assistant ownership rules.
- An assistant is eligible for embodiment when it has a usable 3D character presentation; assistants that cannot be embodied do not block use of the world.
- The initial Connection Node has a layout sized to accommodate the currently expected resident count, up to 10 embodied assistants and NPCs for v1 validation.
- The user will source and approve the environment and furnishing assets; the implementation work begins after those assets are available and validated for the project.
- The final visual direction is polished futuristic sci-fi, while the exact asset pack and furniture selection remain a user decision.
- Assistant and NPC wandering is ambient behavior only; it does not create autonomous conversations, alter conversation memory, or cause residents to leave the Connection Node.
- Existing conversations, voice behavior, character expressions, and pose behavior remain the source of truth for in-world chat.
- V1 targets laptop and desktop browsers with a keyboard and mouse; mobile and touch interaction are out of scope.
