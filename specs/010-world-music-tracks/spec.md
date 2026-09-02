# Feature Specification: World Music Track

**Feature Branch**: `010-world-music-tracks`

**Created**: 2026-08-31

**Status**: Draft

**Input**: User description: "add a field so we are able to add tracks (music) to the open world"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Attach a music track to a world (Priority: P1)

A world creator wants their world to have background music, so while editing a world they upload an audio track that becomes part of that world's setup.

**Why this priority**: Without the ability to attach a track in the first place, no other part of this feature has anything to work with. This is the foundation the rest of the feature depends on.

**Independent Test**: Can be fully tested by opening a world's edit screen, uploading an audio file, and confirming it's now set as that world's track.

**Acceptance Scenarios**:

1. **Given** a world the user owns with no track set, **When** they upload a valid audio file, **Then** it is saved as the world's track.
2. **Given** a world the user owns that already has a track, **When** they upload a different valid audio file, **Then** it replaces the existing track.
3. **Given** a world the user owns with a track set, **When** they remove it, **Then** the world has no track.
4. **Given** a user who does not own a world, **When** they attempt to add, replace, or remove its track, **Then** the request is rejected.

---

### User Story 2 - Hear world music during a session (Priority: P1)

A user playing in a world wants the world's track to play as background music while they're in that world, so the environment feels alive.

**Why this priority**: Attaching a track only delivers value once it's actually heard during play; this closes the loop opened by Story 1 and is equally essential to the feature's purpose.

**Independent Test**: Can be fully tested by entering a session in a world that has a track and confirming audio plays, and by entering one with no track and confirming none does.

**Acceptance Scenarios**:

1. **Given** a world with a track set, **When** a user enters a session in that world, **Then** the track begins playing as background music.
2. **Given** a world's track is playing during a session, **When** it finishes, **Then** it fades out, pauses briefly, and then fades back in as it plays again from the start.
3. **Given** a world with no track set, **When** a user enters a session in that world, **Then** no background music plays.
4. **Given** background music is playing during a session, **When** the user mutes or adjusts the music volume, **Then** their preference is applied immediately and does not affect other users in the same session.
5. **Given** background music is playing during a session, **When** the user presses the M key, **Then** the music mutes; **When** they press M again, **Then** it unmutes at the previous volume.

### Edge Cases

- What happens when a user uploads a file that isn't a supported audio format?
- What happens when a user uploads an audio file that exceeds the maximum allowed size?
- What happens when a world's track is replaced or removed while a session is actively playing it?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow a world's owner to set a music track for that world by uploading an audio file.
- **FR-002**: System MUST reject audio uploads that are not MP3 or WAV format.
- **FR-003**: System MUST reject audio uploads that exceed a maximum file size of 20 MB.
- **FR-004**: System MUST allow a world's owner to replace the world's existing track with a newly uploaded one.
- **FR-005**: System MUST allow a world's owner to remove the world's track, leaving it with none.
- **FR-006**: System MUST prevent users who do not own a world from adding, replacing, or removing that world's track.
- **FR-007**: System MUST play a world's track as background audio for any user in an active session within that world.
- **FR-008**: System MUST automatically restart a world's track from the beginning after a short pause (a few seconds of silence) once it finishes playing, for as long as the session continues — it MUST NOT loop back-to-back with no gap.
- **FR-012**: System MUST fade the track's volume out as it ends and fade it back in as it restarts, rather than cutting or resuming at full volume abruptly.
- **FR-009**: System MUST NOT play any background music during a session in a world that has no track.
- **FR-010**: System MUST allow a user in a session to control the background music volume, including muting it, independently of other users in the same session.
- **FR-011**: System MUST let a user toggle background music mute/unmute by pressing the M key, restoring the previous volume level on unmute.

### Key Entities

- **World**: The existing environment entity; gains an optional music track in addition to its existing attributes (name, description, environment, images, prompts). A world has at most one track at a time.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A world owner can set or replace their world's track in under 30 seconds, from selecting a file to seeing it confirmed as the world's track.
- **SC-002**: Background music begins playing within 2 seconds of a user entering a session in a world that has a track.
- **SC-003**: 100% of sessions in worlds with no track produce no background audio.

## Assumptions

- Only the world's owner can manage its track; other users who visit or play in the world can only listen, and can only control their own local playback volume.
- Background music plays for every user in a session; there is no per-user selection, only local volume/mute control.
- Supported audio formats are MP3 and WAV; the 20 MB maximum file size still applies to both.
- A world holds at most one track at a time; adding a new one replaces the previous one rather than building a playlist.
