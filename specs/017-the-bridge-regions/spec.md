# Feature Specification: The Bridge — One World Made of Connected Regions

**Feature Branch**: `017-the-bridge-regions`

**Created**: 2026-09-27

**Status**: Draft

**Input**: User description: "The Bridge — one world made of connected regions. Today each World is a single explorable map: one GLB environment whose Blender marker nodes (floor, zone, entry, object, spot) are parsed into a layout. Residents, sessions, images, music and AI prompts all belong to that one World. Maps cannot be connected to each other. A World becomes a container of connected Regions. The player moves between regions through passages, and regions can nest (e.g. Zenith District → Lua Building → Penthouse). The first world is 'The Bridge'. Passages are markers in a region's GLB with a descriptive name; in the UI each passage is linked to any other passage, in the same region or another one, through a dropdown grouped by region. Passages are two-way. The world's spawn point is one of its passages. Residents stay configured per region; residents living in another region of the same world are grayed out, show where they live, and can be brought here. Every existing world becomes a region of The Bridge; existing world sessions are deleted."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Existing maps live on as regions of The Bridge (Priority: P1)

The owner opens the Worlds page and sees a single world card, The Bridge. Every map that used to be its own world is now a region inside The Bridge, with its environment, layout, images, music, prompts and residents intact. Starting a new session in The Bridge places the player at the world's spawn point.

**Why this priority**: Nothing else works until the world/region structure exists and the owner's existing content has been carried over without loss.

**Independent Test**: Run the migration against data containing several existing worlds, then open the Worlds page, open The Bridge's configuration, and start a session.

**Acceptance Scenarios**:

1. **Given** three existing worlds with environments, images, music, prompts and residents, **When** the migration runs, **Then** the Worlds page shows one card, The Bridge, and its Regions tab lists three regions carrying each former world's content unchanged.
2. **Given** existing world sessions and their resident states, **When** the migration runs, **Then** those sessions and states no longer exist and the session list for The Bridge is empty.
3. **Given** The Bridge has no spawn passage configured, **When** the player starts a new session, **Then** the player appears at the origin of the world's first region.
4. **Given** The Bridge has a spawn passage configured, **When** the player starts a new session, **Then** the player appears in that passage's region, in front of that passage.

---

### User Story 2 - Link passages and travel between regions (Priority: P1)

The owner adds passage markers to region environments in Blender and uploads them. In a region's configuration, each passage found in the environment is listed by its descriptive name, and the owner picks which other passage it connects to from a dropdown grouped by region. In play, walking into a passage unloads the current region and loads the destination region, placing the player in front of the linked passage.

**Why this priority**: Connecting regions is the core purpose of the feature; without travel, regions are just separate maps again.

**Independent Test**: Configure two regions, each with one passage, link them, start a session and walk through the passage in both directions.

**Acceptance Scenarios**:

1. **Given** region A has passage "Lobby Door" and region B has passage "Lua Entrance", **When** the owner links "Lobby Door" to "Lua Entrance", **Then** "Lua Entrance" shows as linked back to "Lobby Door" without further configuration.
2. **Given** the two passages are linked and the player is in region A, **When** the player walks into "Lobby Door", **Then** region A is unloaded, region B is loaded, and the player stands in front of "Lua Entrance", facing the direction that passage marker faces.
3. **Given** the player has just arrived in front of "Lua Entrance", **When** the player stands still, **Then** no travel happens; travel back only happens once the player walks away and then into the passage again.
4. **Given** a region has two passages, **When** the owner links one to the other, **Then** walking into either moves the player to the other within the same region.
5. **Given** "Lua Entrance" is already linked to "Pier", **When** the owner selects "Lua Entrance" as the destination of "Lobby Door", **Then** the dropdown shows "Lua Entrance" as linked to "Pier", and the owner is asked to confirm before "Pier" is unlinked and the new link is made.
6. **Given** a passage has no link, **When** the player walks into it, **Then** nothing happens and the region shows a warning in its configuration.

---

### User Story 3 - Configure the world and its regions (Priority: P1)

The owner opens a world's configuration. The World tab holds the world's name, description, images, World Prompts and spawn passage. The Regions tab lists the world's regions as a tree reflecting nesting; selecting a region shows the same configuration a world has today (environment upload, layout, images, music, prompts, residents), plus its parent region and its passages.

**Why this priority**: The owner needs to create regions, arrange them, and set the spawn point before travel and nesting have anything to act on.

**Independent Test**: Create a world, add two regions (one nested in the other), upload environments, pick a spawn passage, and verify each setting persists after reloading the page.

**Acceptance Scenarios**:

1. **Given** a world with regions, **When** the owner opens the World tab and chooses a spawn passage, **Then** the dropdown lists every passage in the world grouped under its region's path, and the chosen passage is marked as the spawn point in its region's passage list.
2. **Given** a region, **When** the owner sets another region as its parent, **Then** the region tree shows it nested under that parent and its path reads "Parent › Region".
3. **Given** a region, **When** the owner tries to set one of its own descendants as its parent, **Then** the change is rejected with a clear message.
4. **Given** a region with an unlinked passage or an environment without passages, **When** the owner views the region tree, **Then** that region shows a warning indicator.
5. **Given** the Worlds page, **When** the owner creates a new world, **Then** it appears as a card and can be given regions of its own.

---

### User Story 4 - Residents belong to one region of the world (Priority: P2)

Each assistant or NPC can live in at most one region per world. In a region's residents list, residents who already live in another region of the same world appear grayed out with the name of the region they live in, and a "Bring here" action moves their home to the current region. During play, residents following the player travel with them through passages.

**Why this priority**: Keeps each character unique within a world and lets the owner rearrange where characters live without deleting and re-creating their setup, but travel and configuration work without it.

**Independent Test**: Place an assistant in region A, open region B's residents list, verify the grayed-out row, use "Bring here", then start a session, have the resident follow the player, and walk through a passage.

**Acceptance Scenarios**:

1. **Given** assistant Luna lives in region "Harbor", **When** the owner opens the residents list of region "Lua Building" in the same world, **Then** Luna appears grayed out, cannot be selected, and reads "lives in Harbor".
2. **Given** that grayed-out row, **When** the owner chooses "Bring here" and confirms, **Then** Luna lives in "Lua Building" with the default placement, and "Harbor" no longer lists her as a resident.
3. **Given** an assistant living in a region of world X, **When** the owner opens a region of a different world Y, **Then** the assistant is available to add there as normal.
4. **Given** a resident is following the player, **When** the player walks through a passage, **Then** the resident arrives in the destination region near the player and remains in that region for the rest of the session.
5. **Given** a resident is not following the player, **When** the player walks through a passage, **Then** the resident stays in their current region and is not present in the destination region.

---

### User Story 5 - Location path, regional music and layered prompts (Priority: P2)

While playing, the player's location reads as a path such as "Zenith District › Lua Building › Penthouse". Each region plays its own music. AI characters receive the world's World Prompts together with the current region's prompts and the player's location path.

**Why this priority**: Makes the world feel continuous and gives AI characters awareness of where they are, but travel and configuration deliver value without it.

**Independent Test**: Configure two nested regions with different music and prompts, travel between them, and check the location readout, the music, and the context an AI character receives.

**Acceptance Scenarios**:

1. **Given** the player is in "Penthouse", nested under "Lua Building", nested under "Zenith District", **When** the location readout is shown, **Then** it reads "Zenith District › Lua Building › Penthouse".
2. **Given** two regions with different music, **When** the player travels from one to the other, **Then** the first region's music stops and the destination region's music plays.
3. **Given** World Prompts and region prompts are configured, **When** a resident in a region responds to the player, **Then** the resident's context includes the World Prompts, that region's prompts and the region's location path.

---

### Edge Cases

- A region environment contains no passage markers: the region is still valid, cannot be travelled to or from, and shows a warning.
- A passage marker is missing its identifier or name, or two markers in the same environment share an identifier: they are reported as layout warnings like other malformed markers, and only valid passages are listed.
- A new environment is uploaded that no longer contains a linked passage: the link is removed from both sides; if that passage was the spawn point, the world's spawn point is cleared.
- A new environment keeps a passage's identifier but changes its name or position: the link is kept and the new name and position are used.
- A region is deleted: every link to its passages is removed, its residents are removed from the world, its child regions move up to the deleted region's parent, and if the spawn passage was in it the spawn point is cleared.
- The spawn passage exists but its region has no environment uploaded: the session starts at the origin of that region.
- The player is in a region when an active session is resumed after the region was deleted: the session resumes at the world's spawn point.
- Travelling while a conversation with a resident is open: the conversation ends as when walking out of conversation range.
- Migration finds the same assistant or NPC placed in more than one former world: see FR-026.

## Requirements *(mandatory)*

### Functional Requirements

**World and regions**

- **FR-001**: A world MUST be a container of one or more regions and MUST hold a name, description, images and World Prompts.
- **FR-002**: A region MUST hold everything a world holds today: environment file, parsed layout, images, music, companion and NPC prompts, and residents.
- **FR-003**: A region MAY have a parent region in the same world; a region MUST NOT be its own ancestor.
- **FR-004**: The system MUST present each region's location as the path of region names from the top-level region down to it, joined by "›".
- **FR-005**: The owner MUST be able to create, edit and delete worlds and regions.

**Passages**

- **FR-006**: The environment parser MUST recognise passage markers carrying an identifier and a descriptive name, and MUST record each passage's position, facing direction and, when present, a trigger radius.
- **FR-007**: Passage identifiers MUST be unique within a region's environment; invalid or duplicate passage markers MUST be reported as layout warnings.
- **FR-008**: The owner MUST be able to link any passage to any other passage in the same world, in the same region or a different one.
- **FR-009**: Links MUST be two-way: linking A to B links B to A, and changing or removing the link from either side changes it for both.
- **FR-010**: A passage MUST have at most one linked partner; linking to a passage that is already linked MUST ask the owner to confirm before its previous link is removed.
- **FR-011**: Links and the spawn point MUST refer to a passage by its region and identifier, so re-uploading an environment that keeps the identifier keeps the link.
- **FR-012**: When an uploaded environment no longer contains a linked or spawn passage, the system MUST remove that link from both sides and clear the spawn point if affected.

**Travel**

- **FR-013**: Walking into a linked passage MUST unload the current region and load the destination region.
- **FR-014**: On arrival, the player MUST be placed a short distance in front of the destination passage, facing its marker's direction.
- **FR-015**: A passage MUST only trigger when the player enters its trigger area, never while the player is already inside it on arrival.
- **FR-016**: An unlinked passage MUST do nothing when the player walks into it.

**Spawn and sessions**

- **FR-017**: A world MUST have an optional spawn passage, chosen from all of its passages.
- **FR-018**: A new session MUST start in front of the spawn passage, or at the origin of the world's first region when no spawn passage is set or it no longer exists.
- **FR-019**: A session MUST belong to a world and MUST record which region the player is currently in, so a resumed session reopens in that region.

**Residents**

- **FR-020**: An assistant or NPC MUST live in at most one region per world.
- **FR-021**: A region's residents list MUST show residents living in another region of the same world as unselectable, with the name of the region they live in, and MUST offer a "Bring here" action.
- **FR-022**: "Bring here" MUST, after confirmation, move the resident's home to the current region and reset their placement to the default.
- **FR-023**: During a session, residents following the player MUST travel through passages with the player and remain in the destination region for the rest of that session; other residents MUST stay in their current region.

**Prompts and music**

- **FR-024**: AI characters MUST receive the world's World Prompts, the current region's prompts and the current location path as context.
- **FR-025**: Each region MUST play its own music, switching when the player travels.

**Migration**

- **FR-026**: The migration MUST create a world named "The Bridge" and turn every existing world into a top-level region of it, keeping environment, layout, images, music, prompts and residents. When the same assistant or NPC is a resident of more than one former world, the migration MUST [NEEDS CLARIFICATION: which placement is kept — the one in the most recently updated former world, the oldest one, or should migration stop and report the conflicts for manual resolution?].
- **FR-027**: The migration MUST delete all existing world sessions and their resident states.
- **FR-028**: The migration MUST give every user who had access to any former world access to The Bridge.

**Configuration UI**

- **FR-029**: The Worlds page MUST list worlds as cards.
- **FR-030**: A world's configuration MUST have a World tab (name, description, images, World Prompts, spawn passage) and a Regions tab (region tree plus the selected region's configuration).
- **FR-031**: Passage destination and spawn passage dropdowns MUST group options under each region's location path and show each passage by its descriptive name.
- **FR-032**: The region tree MUST mark the region holding the spawn passage and show a warning on regions with unlinked passages or without passages.

### Key Entities

- **World**: The whole universe (e.g. The Bridge). Has a name, description, images, World Prompts, an optional spawn passage, its regions and its sessions.
- **Region**: One explorable map belonging to a world. Has an environment file and parsed layout, images, music, prompts, an optional parent region, its passages and its residents.
- **Passage**: A marker inside a region's environment with an identifier, a descriptive name, a position, a facing direction and an optional trigger radius. Linked to at most one other passage in the same world.
- **Passage Link**: A two-way connection between two passages of the same world.
- **Resident**: An assistant or NPC living in exactly one region of a world, with its placement and behaviour settings.
- **World Session**: A play session in a world. Records the player's current region and position, and the per-session state of residents, including the region each resident is currently in.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of existing worlds appear as regions of The Bridge after migration, with environment, images, music, prompts and residents unchanged.
- **SC-002**: The owner can link a passage to another passage in under 30 seconds from opening the region's configuration.
- **SC-003**: Travelling between two regions of typical size completes, from entering the passage to control returning to the player, in under 5 seconds.
- **SC-004**: In 100% of travels the player arrives in front of the linked passage and does not immediately travel back.
- **SC-005**: No assistant or NPC is ever resident in more than one region of the same world.
- **SC-006**: The location path shown to the player and given to AI characters matches the region nesting in 100% of regions.

## Assumptions

- Passage markers are added to environment files by the owner in Blender using the same custom-property convention as existing markers; migrated regions have no passages until their environments are updated.
- The trigger radius defaults to about one metre and the arrival distance in front of a passage to about one metre when the marker does not specify them.
- "Bring along" during play uses the existing behaviour where a resident follows the player; no new command is introduced.
- A resident that travelled with the player returns to its home region at the start of the next session.
- The theme setting stays with the region, alongside the rest of the configuration a world has today.
- Only the owner configures worlds and regions; access rules for who can play a world are unchanged apart from moving to The Bridge.
- A world's first region, used for the origin fallback, is the earliest-created top-level region.
