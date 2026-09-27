# Feature Specification: The Bridge — One World Made of Connected Regions

**Feature Branch**: `017-the-bridge-regions`

**Created**: 2026-09-27

**Status**: Draft

**Input**: User description: "The Bridge — one world made of connected regions. Today each World is a single explorable map: one GLB environment whose Blender marker nodes (floor, zone, entry, object, spot) are parsed into a layout. Residents, sessions, images, music and AI prompts all belong to that one World. Maps cannot be connected to each other. A World becomes a container of connected Regions. The player moves between regions through passages. Passages are markers in a region's GLB with a descriptive name; in the UI each passage is linked to any other passage, in the same region or another one, through a dropdown grouped by region. Passages are two-way. The world's spawn point is one of its passages. Any number of worlds can be created. Residents stay configured per region; residents living in another region of the same world are grayed out, show where they live, and can be brought here."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Link passages and travel between regions (Priority: P1)

Region environments contain passage markers. In a region's configuration, each passage found in the environment is listed by its descriptive name, and the user picks which other passage it connects to from a dropdown grouped by region. In play, walking into a passage unloads the current region and loads the destination region, placing the player in front of the linked passage.

**Why this priority**: Connecting regions is the core purpose of the feature; without travel, regions are just separate maps again.

**Independent Test**: Configure two regions, each with one passage, link them, start a session and walk through the passage in both directions.

**Acceptance Scenarios**:

1. **Given** region A has passage "Lobby Door" and region B has passage "Lua Entrance", **When** the user links "Lobby Door" to "Lua Entrance", **Then** "Lua Entrance" shows as linked back to "Lobby Door" without further configuration.
2. **Given** the two passages are linked and the player is in region A, **When** the player walks into "Lobby Door", **Then** region A is unloaded, region B is loaded, and the player stands in front of "Lua Entrance", facing the direction that passage marker faces.
3. **Given** the player has just arrived in front of "Lua Entrance", **When** the player stands still, **Then** no travel happens; travel back only happens once the player walks away and then into the passage again.
4. **Given** a region has two passages, **When** the user links one to the other, **Then** walking into either moves the player to the other within the same region.
5. **Given** "Lua Entrance" is already linked to "Pier", **When** the user selects "Lua Entrance" as the destination of "Lobby Door", **Then** the dropdown shows "Lua Entrance" as linked to "Pier", and the user is asked to confirm before "Pier" is unlinked and the new link is made.
6. **Given** a passage has no link, **When** the player walks into it, **Then** nothing happens and the region shows a warning in its configuration.

---

### User Story 2 - Configure the world and its regions (Priority: P1)

The user opens a world's configuration. The World tab holds the world's name, description, images, World Prompts and spawn passage. The Regions tab lists the world's regions; selecting a region shows the same configuration a world has today (environment upload, layout, images, music, prompts, theme, residents), plus its passages.

**Why this priority**: Regions and a spawn passage must exist before travel has anything to act on.

**Independent Test**: Create a world, add two regions, upload environments, pick a spawn passage, and verify each setting persists after reloading the page.

**Acceptance Scenarios**:

1. **Given** a world with regions, **When** the user opens the World tab and chooses a spawn passage, **Then** the dropdown lists every passage in the world grouped under its region's name, and the chosen passage is marked as the spawn point in its region's passage list.
2. **Given** a region with an unlinked passage or an environment without passages, **When** the user views the regions list, **Then** that region shows a warning indicator.
3. **Given** a world has no spawn passage chosen, **When** the user views the world, **Then** it shows a warning and starting a new session is not possible.
4. **Given** a world has a spawn passage chosen, **When** the player starts a new session, **Then** the player appears in that passage's region, in front of that passage.
5. **Given** the Worlds page, **When** the user creates a new world, **Then** it appears as a card and can be given regions of its own.
6. **Given** a world with several regions, **When** the user deletes a region, **Then** its residents and every link to its passages are removed, and the spawn passage is cleared if it was in that region.

---

### User Story 3 - Residents belong to one region of the world (Priority: P2)

Each assistant or NPC can live in at most one region per world. In a region's residents list, residents who already live in another region of the same world appear grayed out with the name of the region they live in, and an option moves them to the region being edited. During play, residents following the player travel with them through passages.

**Why this priority**: Keeps each character unique within a world and lets the user rearrange where characters live without deleting and re-creating their setup, but travel and configuration work without it.

**Independent Test**: Place an assistant in region A, open region B's residents list, verify the grayed-out row, use the option to move them there, then start a session, have the resident follow the player, and walk through a passage.

**Acceptance Scenarios**:

1. **Given** assistant Luna lives in region "Harbor", **When** the user opens the residents list of region "Lua Building" in the same world, **Then** Luna appears grayed out, cannot be selected, and reads "lives in Harbor".
2. **Given** that grayed-out row, **When** the user chooses to move Luna there and confirms, **Then** Luna lives in "Lua Building" with the default placement, and "Harbor" no longer lists her as a resident.
3. **Given** an assistant living in a region of world X, **When** the user opens a region of a different world Y, **Then** the assistant is available to add there as normal.
4. **Given** a resident is following the player, **When** the player walks through a passage, **Then** the resident arrives in the destination region near the player and remains in that region, across later visits of the same session, until they move again.
5. **Given** a resident is not following the player, **When** the player walks through a passage, **Then** the resident stays in their current region and is not present in the destination region.

---

### User Story 4 - Regional location, music and layered prompts (Priority: P2)

While playing, the player's location shows the current region's name. Each region plays its own music. AI characters receive the world's World Prompts together with the current region's prompts and the region's name.

**Why this priority**: Makes the world feel continuous and gives AI characters awareness of where they are, but travel and configuration deliver value without it.

**Independent Test**: Configure two linked regions with different music and prompts, travel between them, and check the location readout, the music, and the context an AI character receives.

**Acceptance Scenarios**:

1. **Given** the player is in region "Penthouse", **When** the location readout is shown, **Then** it includes "Penthouse".
2. **Given** two regions with different music, **When** the player travels from one to the other, **Then** the first region's music stops and the destination region's music plays.
3. **Given** World Prompts and region prompts are configured, **When** a resident in a region responds to the player, **Then** the resident's context includes the World Prompts, that region's prompts and the region's name.

---

### Edge Cases

- A region environment contains no passage markers, or no linked passage leads to it: the region is still valid and configurable, cannot be reached in play, and shows a warning.
- A passage marker is missing its identifier or name, or two markers in the same environment share an identifier: they are reported as layout warnings like other malformed markers, and only valid passages are listed.
- A new environment is uploaded that no longer contains a linked passage: the link is removed from both sides; if that passage was the spawn point, the world's spawn point is cleared.
- A new environment keeps a passage's identifier but changes its name or position: the link is kept and the new name and position are used.
- A region is deleted: every link to its passages is removed, its residents are removed from the world, and if the spawn passage was in it the spawn point is cleared.
- A session whose current region was deleted is entered: it resumes in front of the world's spawn passage; while no spawn passage is chosen, it cannot be entered.
- A resident's current region in a session is deleted while the region they belong to still exists: the next visit finds them back in that region.
- Travelling while a conversation with a resident is open: the conversation ends as when walking out of conversation range.

## Requirements *(mandatory)*

### Functional Requirements

**World and regions**

- **FR-001**: A world MUST be a container of one or more regions and MUST hold a name, description, images and World Prompts.
- **FR-002**: A region MUST hold everything a world holds today: environment file, parsed layout, images, music, companion and NPC prompts, and residents.
- **FR-003**: Regions MUST NOT contain other regions; every region of a world is at the same level.
- **FR-004**: The system MUST present the player's location by the current region's name.
- **FR-005**: Users MUST be able to create, edit and delete any number of worlds and regions, as with assistants and NPCs.

**Passages**

- **FR-006**: The environment parser MUST recognise passage markers carrying an identifier and a descriptive name, and MUST record each passage's position, facing direction and, when present, a trigger radius.
- **FR-007**: Passage identifiers MUST be unique within a region's environment; invalid or duplicate passage markers MUST be reported as layout warnings.
- **FR-008**: Users MUST be able to link any passage to any other passage in the same world, in the same region or a different one.
- **FR-009**: Links MUST be two-way: linking A to B links B to A, and changing or removing the link from either side changes it for both.
- **FR-010**: A passage MUST have at most one linked partner; linking to a passage that is already linked MUST ask the user to confirm before its previous link is removed.
- **FR-011**: Links and the spawn point MUST refer to a passage by its region and identifier, so re-uploading an environment that keeps the identifier keeps the link.
- **FR-012**: When an uploaded environment no longer contains a linked or spawn passage, the system MUST remove that link from both sides and clear the spawn point if affected.

**Travel**

- **FR-013**: Walking into a linked passage MUST unload the current region and load the destination region.
- **FR-014**: On arrival, the player MUST be placed a short distance in front of the destination passage, facing its marker's direction.
- **FR-015**: A passage MUST only trigger when the player enters its trigger area, never while the player is already inside it on arrival.
- **FR-016**: An unlinked passage MUST do nothing when the player walks into it.

**Spawn and sessions**

- **FR-017**: A world MUST have a spawn passage, chosen from all of its passages, before a new session can be started; until one is chosen, the world MUST show a warning and MUST NOT allow starting a session.
- **FR-018**: A new session MUST start in front of the world's spawn passage.
- **FR-019**: A session MUST belong to a world, not to a region, and MUST keep the player's current region and position and each resident's current region and state across visits.
- **FR-020**: Entering a session MUST resume it in the region and position where the previous visit ended; if that region no longer exists, the visit MUST start in front of the spawn passage.

**Residents**

- **FR-021**: An assistant or NPC MUST live in at most one region per world.
- **FR-022**: A region's residents list MUST show residents living in another region of the same world as unselectable, with the name of the region they live in, and MUST offer an option to move them to this region.
- **FR-023**: Moving a resident from the residents list MUST, after confirmation, make the region being edited their region and reset their placement to the default. Existing sessions MUST keep the resident where they were; only new sessions start them in the new region.
- **FR-024**: Residents following the player MUST travel through passages with the player and remain in the destination region, across visits of the same session, until they move again; other residents MUST stay in their current region. The region a resident belongs to decides where they start in a new session, and where they return when their current region is deleted.

**Prompts and music**

- **FR-025**: AI characters MUST receive the world's World Prompts, the current region's prompts and the current region's name as context.
- **FR-026**: Each region MUST play its own music, switching when the player travels.

**Configuration UI**

- **FR-027**: The Worlds page MUST list worlds as cards.
- **FR-028**: A world's configuration MUST have a World tab (name, description, images, World Prompts, spawn passage) and a Regions tab (regions list plus the selected region's configuration).
- **FR-029**: Passage destination and spawn passage dropdowns MUST group options under each region's name and show each passage by its descriptive name.
- **FR-030**: The regions list MUST mark the region holding the spawn passage and show a warning on regions with unlinked passages or without passages.

### Key Entities

- **World**: The whole universe (e.g. The Bridge). Has a name, description, images, World Prompts, a spawn passage, its regions and its sessions.
- **Region**: One explorable map belonging to a world. Has an environment file and parsed layout, images, music, prompts, theme, its passages and its residents.
- **Passage**: A marker inside a region's environment with an identifier, a descriptive name, a position, a facing direction and an optional trigger radius. Linked to at most one other passage in the same world.
- **Passage Link**: A two-way connection between two passages of the same world.
- **Resident**: An assistant or NPC living in exactly one region of a world, with its placement and behaviour settings.
- **World Session**: A saved state of a world that persists across visits. Records the player's current region and position, and the state of each resident, including the region each resident is currently in. A world can have several sessions.
- **Visit**: One period of play in a session, from entering the world until leaving it.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can link a passage to another passage in under 30 seconds from opening the region's configuration.
- **SC-002**: Travelling between two regions of typical size completes, from entering the passage to control returning to the player, in under 5 seconds.
- **SC-003**: In 100% of travels the player arrives in front of the linked passage and does not immediately travel back.
- **SC-004**: No assistant or NPC is ever resident in more than one region of the same world.

## Assumptions

- The trigger radius defaults to about one metre and the arrival distance in front of a passage to about one metre when the marker does not specify them.
- A resident travels with the player when they are following the player through the existing follow behaviour; no new command is introduced.
- The theme setting stays with the region, alongside the rest of the configuration a world has today.
