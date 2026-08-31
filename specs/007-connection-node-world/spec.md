# Feature Specification: Configurable Worlds

**Feature Branch**: `007-connection-node-world`

**Created**: 2026-08-30

**Status**: Draft

**Input**: User description: "Add user-created, desktop-first 3D worlds to the Assistants area. A world such as the Connection Node is a single room containing selected 3D assistant residents and configurable NPC residents. Users can walk, interact with nearby residents, and use in-world chat. Each world has editable context prompts for companion assistants and NPCs."

## Clarifications

### Session 2026-08-30

- Q: How should world NPCs access setting knowledge? → A: NPCs reuse the existing assistant archive access.
- Q: How should worlds manage unused character work? → A: Character animation, roaming, and related updates are reduced when a resident is not visible or nearby.
- Q: How are spaces created and contextualized? → A: Users create and edit Worlds from the Assistants area, and each world has separate editable context prompts for companion assistants and NPCs.
- Q: Where are NPCs managed and removed? → A: NPCs are assistant-backed records managed in a dedicated NPC section below Assistants. Worlds only add or remove their resident placements; permanent NPC deletion occurs only in NPC CRUD.
- Q: What happens to a world environment asset when its world is deleted? → A: The environment asset is owned by its world and is permanently deleted with that world.

### Session 2026-08-31

- Q: Should Worlds and NPCs live below Assistants on the same page, or be sibling sections of their own? → A: Assistants, Worlds, and NPCs are three sibling sections, each with its own page, reachable from a new Home page; Assistants keeps its own page with a back button to Home.
- Q: Can residents be selected while creating a world, or only after it's saved? → A: Resident selection is available on the create screen itself, staged locally and attached right after the world is created; the world editor keeps the same resident controls for later changes.
- Q: Should the resident picker show ineligible assistants/NPCs with a reason, or just the eligible ones? → A: Just the eligible ones, grouped separately as Assistants and NPCs; ineligible records are omitted rather than shown disabled.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Create and manage worlds (Priority: P1)

An authenticated user opens the Worlds section alongside Assistants, creates a world, names and describes it, sets its room assets and editable assistant/NPC context prompts, then returns later to edit or remove it.

**Why this priority**: Worlds are user-owned content, so the Connection Node must be a configurable example rather than a fixed application surface.

**Independent Test**: Create a world called Connection Node, save its prompts, reopen it, edit its settings, and confirm it appears as a world card.

**Acceptance Scenarios**:

1. **Given** the user is on Home, **When** they choose Worlds, **Then** they see existing world cards and an add-world card.
2. **Given** the user creates a world with valid required fields and optionally selects eligible residents, **When** they save it, **Then** the world appears in the Worlds section with any selected residents already placed, and can be edited or entered.
3. **Given** the user edits a world prompt, **When** they save it, **Then** later in-world conversations use the updated prompt.
4. **Given** the user removes a world, **When** they confirm deletion, **Then** the world, its environment asset, and all resident placements are removed without deleting assistants, NPCs, or their conventional conversations.

---

### User Story 2 - Enter and explore a world (Priority: P1)

The user selects a world card and enters a dedicated first-person, single-room experience. A graceful transition and loader prepare the approved environment and residents. The user walks with keyboard and mouse controls, while walls and selected furnishings prevent movement through them.

**Why this priority**: Exploration is the central experience that distinguishes a world from a conventional conversation page.

**Independent Test**: Enter a configured world, use every movement control, attempt to pass through collision-marked room features, release controls, and return to the Worlds section.

**Acceptance Scenarios**:

1. **Given** a world is ready, **When** the user selects its card, **Then** they see a clear loading transition before the room becomes interactive.
2. **Given** the world is active, **When** the user walks or looks using documented laptop controls, **Then** they can explore without becoming trapped in world input.
3. **Given** the user walks into a solid room boundary or furnishing, **When** they continue moving toward it, **Then** they remain outside the object and can move away normally.

---

### User Story 3 - Meet and chat with residents (Priority: P1)

The user configures eligible existing 3D assistants and NPCs as residents of a world. Approaching a resident presents a chat prompt; pressing `C` opens or resumes that resident's existing conversation in an in-world panel.

**Why this priority**: Embodied, proximity-based conversation is the purpose of the world.

**Independent Test**: Add two companion assistants and one existing NPC as residents, approach each one, use `C`, exchange messages, close chat, and verify the correct conversation and world context are retained.

**Acceptance Scenarios**:

1. **Given** a selected companion has a usable 3D character model, **When** the user enters the world, **Then** the companion appears at its configured location or roaming area.
2. **Given** the user is outside a resident's interaction range, **When** they press `C`, **Then** no chat opens.
3. **Given** the user is within interaction range, **When** they press `C`, **Then** an in-world panel opens for that resident and pauses world movement.
4. **Given** a world chat is active, **When** the resident responds, **Then** the response uses the resident's existing conversation behavior, prompt, archive knowledge, and the applicable world context prompt.
5. **Given** the user closes the panel, **When** exploration resumes, **Then** they remain near the selected resident.

---

### User Story 4 - Configure NPCs and resident behavior (Priority: P2)

The user manages NPCs in their own NPC section, reachable from Home alongside Assistants and Worlds. Each NPC has a name, one editable character prompt, optional archive, 3D character model, and pose setup. The user then adds existing NPCs to worlds and configures their placement and stationary or roaming behavior there.

**Why this priority**: NPCs make a world feel inhabited while preserving a coherent, professional configuration model.

**Independent Test**: Create an NPC in the NPC section, attach a model and pose, select an archive, add it to a world, configure roaming, enter the world, and verify both behavior and archive-grounded conversation.

**Acceptance Scenarios**:

1. **Given** the user edits a world, **When** they select companion residents, **Then** only owned assistants and NPCs with usable 3D models are listed as selectable, grouped separately by kind.
2. **Given** the user creates an NPC with required settings, **When** they save it, **Then** it appears in the NPC section and can later be added to a world as a resident.
3. **Given** a resident is set to roam, **When** the world is active, **Then** it stays inside its safe roaming area and stops roaming while in chat.

### Edge Cases

- A world without configured residents remains enterable and explains that no residents are available.
- A missing room or character asset produces a recoverable error without exposing another user's data or opening the wrong conversation.
- Losing browser focus stops movement until the user intentionally resumes world controls.
- A resident outside the camera view and interaction range does not continue nonessential animation or roaming work, but is ready before the user can see or interact with it.
- Removing a world removes only its resident placements; it never deletes assistants, NPCs, or their conversations.
- Removing an assistant or NPC from a world removes only that placement; permanent NPC deletion is available only through the NPC section and requires confirmation.
- Deleting a world permanently removes its world-owned environment asset; it never removes character assets owned by assistants or NPCs.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST provide Assistants, Worlds, and NPCs as sibling sections reachable from a Home page; each lists owned records and includes its appropriate add action.
- **FR-002**: Users MUST be able to create, edit, enter, and delete owned worlds; deletion MUST permanently remove the world-owned environment asset and resident placements only.
- **FR-003**: A world MUST have a non-empty name, description, approved environment configuration, assistant context prompt, and NPC context prompt.
- **FR-004**: The world editor MUST expose both context prompts as editable fields and MUST not hardcode their content.
- **FR-005**: The system MUST add the assistant context prompt only to companion-assistant conversations initiated through that world.
- **FR-006**: The system MUST add the NPC context prompt only to NPC conversations initiated through that world.
- **FR-007**: Conventional conversations outside a world MUST not receive a world context prompt.
- **FR-008**: A world MUST provide a first-person single-room experience with keyboard/mouse controls, an explicit input-release path, and focus-loss movement stop.
- **FR-009**: The system MUST prevent movement through configured room boundaries and solid furnishings, by blocking movement against any mesh or group in the environment GLB whose name contains "collision" (case-insensitive); a room asset with no such geometry has no furnishing/wall collision, only the outer room-bounds clamp.
- **FR-010**: The system MUST let the user add and remove only owned, usable 3D assistants or NPCs as world residents without changing the underlying character record.
- **FR-011**: The system MUST let the user create, edit, and permanently delete NPCs in the dedicated NPC section with a name, one character prompt, optional archive, 3D model, and pose configuration; worlds MUST configure only their placement and stationary or roaming behavior.
- **FR-012**: NPCs MUST reuse the existing assistant character, prompt, archive, provider, conversation, voice, and pose behavior.
- **FR-013**: The system MUST show a nearby resident's chat prompt only inside its interaction range and MUST open its chat with `C`.
- **FR-014**: The system MUST use the selected resident's existing conversation identity and history while preserving user and assistant ownership boundaries.
- **FR-015**: The system MUST display a graceful loading state and recoverable errors for unavailable environment, character, and chat resources.
- **FR-016**: The system MUST reduce nonessential resident animation, roaming, and visual updates outside a fixed distance from the player, and MUST release world-only resources when leaving a world.
- **FR-017**: World configuration, environment assets, and placements MUST be scoped to their owning user and world; assistants and NPCs MUST remain scoped to their owning user.
- **FR-018**: V1 MUST exclude mobile controls, multiple rooms inside one world, generated environmental backgrounds, and open-world traversal.

### Key Entities

- **World**: A user-owned, named single-room 3D space with environment settings and separate context prompts for companion assistants and NPCs.
- **World Resident**: A world-specific placement and behavior configuration for a companion assistant or NPC.
- **World NPC**: An assistant-backed NPC record created and managed in the NPC section, retaining the existing character, archive, and conversation capabilities and addable to one or more worlds.
- **World Context Prompt**: An editable instruction appended only to a conversation that occurs in its associated world, selected by resident kind.
- **World Asset**: An approved room, furnishing, or ambient asset with its source, license, and runtime suitability record.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can create a configured world and return to its editor in under 3 minutes, excluding time spent sourcing assets.
- **SC-002**: A user can enter a ready world from the Worlds section in no more than two selections.
- **SC-003**: In a world with up to 10 residents, every resident is visible or reachable through unobstructed walkable paths.
- **SC-004**: At least 95% of nearby interaction attempts open the intended resident's conversation after one press of `C`.
- **SC-005**: A world context prompt appears in 100% of applicable in-world responses and in 0% of conventional responses outside that world.
- **SC-006**: The world remains usable during a 15-minute visit without loss of chat availability or accumulated resource degradation after repeated entry and exit.

## Assumptions

- The Connection Node is the first configured world, not a hardcoded singleton.
- Each world contains one bounded room in v1; a user may create more than one world.
- Companion assistants and NPCs are scoped to the same authenticated user who owns the world.
- Final room and furnishing assets are user-supplied and approved before integration.
- NPCs use the same underlying assistant infrastructure while their dedicated CRUD intentionally exposes only the required subset of assistant configuration.
- World prompts describe the current environment and are dynamic conversation context, not permanent changes to an assistant's base prompt.
