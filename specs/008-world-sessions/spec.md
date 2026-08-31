# Feature Specification: World Sessions

**Feature Branch**: `008-world-sessions`

**Created**: 2026-08-31

**Status**: Draft

**Input**: User description: "Just as we have conversations for a singular assistant, we should have sessions for worlds, we need a new page, similar to assistants page, for sessions, where we can select and continue new sessions, or start a new one, or delete current sessions"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - View and resume past sessions in a world (Priority: P1)

A user opens a world they've visited before and sees a list of their past sessions in that world, each identified by when it happened, so they can pick up where they left off.

**Why this priority**: Continuity is the core value of sessions — without the ability to see and resume past sessions, the feature provides nothing beyond a single always-fresh world visit.

**Independent Test**: Can be fully tested by creating multiple sessions in a world, navigating to the sessions list, and confirming each past session appears and can be opened to its prior state.

**Acceptance Scenarios**:

1. **Given** a world with two prior sessions, **When** the user opens that world's sessions page, **Then** both sessions are listed, most recently active first.
2. **Given** a listed session, **When** the user selects it, **Then** the user is taken into that world resuming from that session's saved state.
3. **Given** a world with no prior sessions, **When** the user opens that world's sessions page, **Then** the page shows an empty state with a way to start a new session.

---

### User Story 2 - Start a new session (Priority: P1)

A user wants a fresh start in a world without disturbing their existing sessions, so they start a new session from the sessions page.

**Why this priority**: Equally essential to resuming — users need a way to begin a new thread of activity in a world at will, the same way a new conversation can be started with an assistant.

**Independent Test**: Can be fully tested by selecting "new session" from the sessions page and confirming a new, empty session is created and the user is taken into the world within it, while prior sessions remain unchanged.

**Acceptance Scenarios**:

1. **Given** a user on a world's sessions page, **When** they choose to start a new session, **Then** a new session is created and the user enters the world in that fresh session.
2. **Given** a user starts a new session, **When** they later return to the sessions page, **Then** both the new session and all prior sessions are listed.

---

### User Story 3 - Delete a session (Priority: P2)

A user no longer wants a particular session and removes it from the list.

**Why this priority**: Important for keeping the session list manageable and letting users discard sessions they don't want to keep, but not required for the core create/resume loop to deliver value.

**Independent Test**: Can be fully tested by deleting a session from the list and confirming it no longer appears and can no longer be resumed.

**Acceptance Scenarios**:

1. **Given** a world with multiple sessions, **When** the user deletes one of them, **Then** it is removed from the list and its state is no longer accessible.
2. **Given** a user attempts to delete a session, **When** they confirm the deletion, **Then** the session and its associated data are permanently removed.

---

### Edge Cases

- What happens when a user deletes the only session in a world? The sessions page returns to the empty state described in User Story 1.
- What happens when a user tries to delete a session that is currently open/active? The active session view is exited and the user is returned to the sessions list.
- How does the system label a session that has no meaningful activity yet (e.g., just created, nothing happened)? It still appears in the list using a default label and creation time.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide a sessions page for each world, listing all sessions belonging to the current user for that world.
- **FR-002**: Sessions list MUST be ordered by most recently active first.
- **FR-003**: Each listed session MUST display an identifying label (a title, defaulting to a generic name, and/or a timestamp) so the user can distinguish between sessions.
- **FR-004**: Users MUST be able to select a listed session and resume it from its saved state.
- **FR-005**: Users MUST be able to start a new session for a world, distinct from any existing session, and immediately enter the world within it.
- **FR-006**: Users MUST be able to delete a session, with confirmation before permanent removal.
- **FR-007**: Deleting a session MUST remove it from the list and make its state permanently inaccessible.
- **FR-008**: The sessions page MUST show an empty state, with a way to start a new session, when a world has no sessions yet.
- **FR-009**: System MUST scope sessions to the world they belong to; sessions from one world MUST NOT appear on another world's sessions page.
- **FR-010**: System MUST scope sessions to the user who owns them; a user MUST NOT see or act on another user's sessions.

### Key Entities

- **World Session**: Represents one continuous thread of a user's activity within a specific world — analogous to how a Conversation represents one thread with an Assistant. Belongs to exactly one world and one user. Has an identifying label and tracks when it was last active, used for ordering and resuming.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can find and resume a prior session in a world in under 10 seconds from opening the world's sessions page.
- **SC-002**: A user can start a brand-new session in a world in two actions or fewer from the sessions page.
- **SC-003**: 100% of session deletions are reflected in the sessions list immediately, with no deleted session ever reappearing or remaining resumable.
- **SC-004**: Sessions from different worlds or different users never appear cross-mixed in a given sessions page.

## Assumptions

- "Sessions" for a world are per-user, mirroring how conversations are scoped per user/assistant pairing in the existing assistant feature.
- The sessions page follows the same list/select/create/delete interaction pattern already established by the Assistants and Conversations pages, applied to worlds instead.
- Renaming a session (analogous to renaming a conversation) is a reasonable extension but not explicitly requested; it is out of scope for this spec unless clarified otherwise.
- "Current sessions" in the request refers to a user's own existing sessions for a given world, not a global or shared session list.
- A session's saved state (what "resuming" restores) is whatever the world experience already persists per visit; this spec does not change what is saved, only how users navigate between saved threads.
