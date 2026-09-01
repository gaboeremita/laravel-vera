# Feature Specification: World Music Tracks

**Feature Branch**: `010-world-music-tracks`

**Created**: 2026-08-31

**Status**: Draft

**Input**: User description: "add a field so we are able to add tracks (music) to the open world"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Attach music tracks to a world (Priority: P1)

A world creator wants their world to have background music, so while editing a world they upload one or more audio tracks that become part of that world's setup.

**Why this priority**: Without the ability to attach tracks in the first place, no other part of this feature has anything to work with. This is the foundation the rest of the feature depends on.

**Independent Test**: Can be fully tested by opening a world's edit screen, uploading an audio file, and confirming it now appears in that world's list of tracks.

**Acceptance Scenarios**:

1. **Given** a world the user owns, **When** they upload a valid audio file as a track, **Then** the track is saved and appears in the world's track list.
2. **Given** a world with no tracks, **When** the user views the world's edit screen, **Then** they see an empty track list with an option to add one.
3. **Given** a world the user owns with existing tracks, **When** they upload another valid audio file, **Then** it is added to the list without affecting the existing tracks.
4. **Given** a user who does not own a world, **When** they attempt to add a track to it, **Then** the request is rejected.

---

### User Story 2 - Manage existing tracks (Priority: P2)

A world creator wants to rename, reorder, or remove tracks they've already added, so the track list stays accurate as the world's music evolves.

**Why this priority**: Once tracks can be added, creators need to correct mistakes and curate the set over time; this is a natural extension of Story 1 but not required for the minimum viable version.

**Independent Test**: Can be fully tested by adding a track, then renaming it, reordering it relative to another track, and deleting it, confirming each change is reflected immediately.

**Acceptance Scenarios**:

1. **Given** a world with a track, **When** the owner renames the track, **Then** the new name is shown in the track list.
2. **Given** a world with multiple tracks, **When** the owner changes their order, **Then** the new order is preserved and reflected wherever the tracks are listed or played.
3. **Given** a world with a track, **When** the owner deletes it, **Then** it no longer appears in the track list and is no longer played during world sessions.

---

### User Story 3 - Hear world music during a session (Priority: P1)

A user playing in a world wants the world's attached tracks to play as background music while they're in that world, so the environment feels alive.

**Why this priority**: Attaching tracks only delivers value once they're actually heard during play; this closes the loop opened by Story 1 and is equally essential to the feature's purpose.

**Independent Test**: Can be fully tested by entering a world session that has at least one track and confirming audio plays, and by entering one with no tracks and confirming none does.

**Acceptance Scenarios**:

1. **Given** a world with one or more tracks, **When** a user enters a session in that world, **Then** one of the tracks begins playing as background music.
2. **Given** a world with multiple tracks, **When** the current track finishes playing, **Then** the next track in the world's list begins playing automatically.
3. **Given** a world with no tracks, **When** a user enters a session in that world, **Then** no background music plays.
4. **Given** background music is playing during a session, **When** the user mutes or adjusts the music volume, **Then** their preference is applied immediately and does not affect other users in the same world.

### Edge Cases

- What happens when a user uploads a file that isn't a supported audio format?
- What happens when a user uploads an audio file that exceeds the maximum allowed size or duration?
- What happens when the last remaining track on a world is deleted while a session is actively playing it?
- What happens when a world owner deletes a track that another user is currently listening to mid-session?
- What happens when a world reaches the maximum number of tracks allowed?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow a world's owner to add one or more music tracks to that world by uploading an audio file.
- **FR-002**: System MUST reject audio uploads that are not in a supported audio format.
- **FR-003**: System MUST reject audio uploads that exceed a maximum file size of 20 MB.
- **FR-004**: System MUST allow a world's owner to give each track a display name, defaulting to the uploaded file's name if none is provided.
- **FR-005**: System MUST allow a world's owner to view the list of tracks currently attached to a world, in their play order.
- **FR-006**: System MUST allow a world's owner to reorder the tracks attached to a world.
- **FR-007**: System MUST allow a world's owner to rename a track already attached to a world.
- **FR-008**: System MUST allow a world's owner to remove a track from a world.
- **FR-009**: System MUST prevent users who do not own a world from adding, renaming, reordering, or removing that world's tracks.
- **FR-010**: System MUST play a world's tracks as background audio for any user in an active session within that world.
- **FR-011**: System MUST automatically advance to the next track in the world's order when the current one finishes, looping back to the first track after the last one.
- **FR-012**: System MUST NOT play any background music during a session in a world that has no tracks.
- **FR-013**: System MUST allow a user in a session to control the background music volume, including muting it, independently of other users in the same session.
- **FR-014**: System MUST limit the number of tracks a single world can have to 20.

### Key Entities

- **Track**: A single music/audio clip attached to a world. Attributes: display name, the audio file itself, its position within the world's play order, and which world it belongs to.
- **World**: The existing environment entity that a track now belongs to; gains a collection of tracks in addition to its existing attributes (name, description, environment, images, prompts).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A world owner can add a new track to their world in under 30 seconds, from selecting a file to seeing it appear in the track list.
- **SC-002**: Background music begins playing within 2 seconds of a user entering a session in a world that has tracks.
- **SC-003**: 100% of sessions in worlds with zero tracks produce no background audio.
- **SC-004**: World owners can reorder or remove tracks with the change visible in the track list immediately, with no page reload required.

## Assumptions

- Tracks are scoped per world, not shared across worlds; a track uploaded to one world is not available in another.
- Only the world's owner can manage its tracks; other users who visit or play in the world can only listen, and can only control their own local playback volume.
- Background music plays for every user in a session; there is no per-user selection of which world track plays, only local volume/mute control.
- Supported audio formats follow common web-playable formats (e.g. MP3, WAV, OGG); exact format list is a reasonable technical default rather than a product decision.
- A world can hold up to 20 tracks, matching the kind of small curated soundtrack an open-world scene would reasonably need.
