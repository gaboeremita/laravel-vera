# Feature Specification: 3D VRM Avatar Portrait

**Feature Branch**: `004-vrm-3d-avatar`

**Created**: 2026-08-28

**Status**: Draft

**Input**: User description: "add an option for AI assistants to have an image (current) or a 3D avatar in the portrait section, exported from VRoid Studio, with facial expressions driven by the same LLM emotion tags that already drive static image swaps"

## Clarifications

### Session 2026-08-28

- Q: Does US3 require head movement (bone-based sway) alongside blinking in v1, or is blinking alone sufficient? → A: Both blinking and head sway in v1 — FR-008 and US3 Scenario 2 updated accordingly.
- Q: When switching portrait type from "3D avatar" back to "image", should the VRM file be retained or auto-deleted? → A: Retained — portrait type and uploaded files are fully independent in both directions (switching to 3D avatar does not delete emotion images; switching to image does not delete the VRM file). Assumptions and US1 Scenario 2 updated.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configure 3D Avatar for an Assistant (Priority: P1)

A user opens the assistant settings page and switches the portrait type from the default image mode to 3D avatar. They upload a `.vrm` file exported from VRoid Studio. After saving, the assistant's portrait area renders the 3D model instead of the static image.

**Why this priority**: This is the foundation of the feature — without configuring and storing the avatar file, nothing else works. It is also the only user-facing setup step; all other stories depend on this one having happened first.

**Independent Test**: Can be fully tested by configuring an assistant, uploading a VRM file, saving, and confirming the portrait panel renders the 3D model in neutral pose.

**Acceptance Scenarios**:

1. **Given** an assistant is in image portrait mode, **When** the user switches to 3D avatar mode and uploads a valid `.vrm` file and saves, **Then** the assistant portrait displays the 3D model in a neutral pose.
2. **Given** the assistant is in 3D avatar mode, **When** the user switches back to image mode and saves (without deleting the VRM file), **Then** the portrait reverts to the image-based display and the VRM file is preserved — re-enabling 3D avatar mode later restores the avatar without re-uploading.
3. **Given** the assistant is in image mode with a VRM file stored, **When** the user switches to 3D avatar mode and saves (without uploading new images), **Then** the existing emotion images are preserved and unaffected.
3. **Given** the assistant is in 3D avatar mode with no VRM file uploaded, **When** the user views the chat, **Then** the portrait falls back gracefully (e.g., shows the default VERA avatar image) rather than crashing or showing a blank area.
4. **Given** an assistant with a VRM file configured, **When** a different user's assistant is loaded, **Then** the VRM file is scoped to the correct assistant and not shared or leaked to others.

---

### User Story 2 - Emotion-Driven Facial Expressions (Priority: P2)

During a conversation, the LLM sends an emotion tag (e.g., `[happy]`, `[sad]`, `[annoyed]`). The 3D avatar's face responds by transitioning to the matching expression — corners of the mouth lift, eyes narrow, etc. — and transitions back smoothly when the emotion changes.

**Why this priority**: Expression reactivity is what makes the 3D avatar meaningfully different from a static image. Without it, the avatar offers no behavioral advantage over the current system.

**Independent Test**: Can be fully tested by sending chat messages that produce known emotion tags and observing the avatar's face change expression in response, with smooth transitions between states.

**Acceptance Scenarios**:

1. **Given** a chat session with a 3D avatar assistant, **When** the LLM response contains `[happy]`, **Then** the avatar's face transitions to a happy expression within 500 ms.
2. **Given** the avatar is currently displaying `[sad]`, **When** the next response contains `[neutral]`, **Then** all expression blendshapes return to zero smoothly over 300–500 ms rather than snapping.
3. **Given** an emotion tag that does not map to a VRM expression (e.g., a custom tag like `[flustered]`), **When** the response is received, **Then** the avatar renders the closest mapped expression (or neutral) and does not error.
4. **Given** a chat session in image portrait mode, **When** an emotion tag arrives, **Then** the existing image-swap behavior is unchanged — the 3D expression system only activates for 3D avatar mode.

---

### User Story 3 - Idle Animations (Priority: P3)

Between responses, the avatar performs subtle idle behaviors — eyes blink at randomized intervals, the head makes small natural movements — so the character feels alive rather than frozen.

**Why this priority**: A static 3D model looks more uncanny than a static image. Idle animations are the minimum viable "life" signal that makes the 3D mode feel like an upgrade rather than a regression in polish.

**Independent Test**: Can be fully tested by loading a 3D avatar assistant and observing the portrait panel for at least 10 seconds without any chat activity to confirm blinking and subtle motion occur without any user interaction.

**Acceptance Scenarios**:

1. **Given** a 3D avatar assistant is displayed, **When** no new messages are sent for 5 seconds, **Then** the avatar blinks at least once within a randomized interval of 2–6 seconds.
2. **Given** the avatar is idle, **When** observed for 5 seconds, **Then** the head exhibits subtle continuous sway (gentle rotation on at least one axis, amplitude visually perceptible but not exaggerated).
3. **Given** the avatar is performing an idle blink, **When** an emotion expression arrives simultaneously, **Then** the expression takes priority and the blink resolves without visual conflict.
4. **Given** the portrait panel is not visible (e.g., the user has scrolled or navigated away), **When** the avatar is off-screen, **Then** idle animations pause to avoid unnecessary computation.

---

### Edge Cases

- What happens when a VRM file is malformed or not a valid VRM format on upload?
- What happens if the VRM file references expressions that do not match the standard VRM expression names?
- What happens when the portrait renders on a device that does not support WebGL (3D canvas)?
- What happens when two chat messages arrive in rapid succession with different emotion tags?
- What is the maximum acceptable VRM file size, and what happens when the file exceeds it?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow each assistant to be individually configured with a portrait type of either "image" (current behavior) or "3D avatar".
- **FR-002**: System MUST allow users to upload a single `.vrm` file per assistant when portrait type is "3D avatar".
- **FR-003**: System MUST store the uploaded VRM file associated with the assistant that owns it, scoped so no other assistant can access it without explicit assignment.
- **FR-004**: System MUST render the 3D avatar model in the portrait area when portrait type is "3D avatar" and a VRM file is present.
- **FR-005**: System MUST apply facial expressions to the 3D avatar in response to the same emotion tags already parsed from LLM responses (`[happy]`, `[sad]`, `[annoyed]`, `[flustered]`, `[neutral]`, and any others the system currently recognizes).
- **FR-006**: System MUST transition between expressions smoothly over 300–500 ms rather than switching instantaneously.
- **FR-007**: System MUST support combining multiple blendshape values simultaneously (e.g., an emotion can activate more than one expression at partial intensity).
- **FR-008**: System MUST animate the avatar with idle behaviors — periodic blinking at randomized intervals (2–6 s) and continuous subtle head sway (bone-based rotation) — when no expression transition is in progress.
- **FR-009**: System MUST fall back to the default VERA avatar image when portrait type is "3D avatar" but no VRM file has been uploaded.
- **FR-010**: System MUST leave all existing image-based portrait behavior unchanged when portrait type is "image".
- **FR-011**: System MUST validate uploaded files are `.vrm` format and reject uploads that are not.
- **FR-012**: System MUST enforce a maximum VRM file size of 50 MB per upload.

### Key Entities *(include if feature involves data)*

- **Assistant**: Gains a `portrait_type` field (image or 3D avatar) and an association to an uploaded VRM file.
- **VRM File**: A stored binary asset associated with exactly one assistant, containing a VRM-format 3D avatar model.
- **Emotion-to-Expression Mapping**: A configuration (fixed at the application level for v1) that maps each recognized emotion tag to one or more blendshape names and their target intensities.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can configure an assistant for 3D avatar mode and see the avatar rendering in under 5 seconds from page load on a standard broadband connection for VRM files up to 50 MB.
- **SC-002**: Facial expressions respond to LLM emotion tags within 500 ms of the message being rendered, including the lerp transition completing within that window.
- **SC-003**: Idle blinking occurs at least once every 6 seconds and no more than once every 2 seconds while the avatar is visible and idle; head sway is visually perceptible throughout idle state.
- **SC-004**: All existing image-mode assistants continue to function with no regression — zero behavior changes for assistants not using 3D avatar mode.
- **SC-005**: VRM files larger than 50 MB are rejected at upload with a clear error message; files at or below 50 MB upload successfully.
- **SC-006**: Switching portrait type from 3D avatar back to image in assistant settings persists correctly and is reflected immediately on next page load.

## Assumptions

- VRM files are created externally (e.g., in VRoid Studio) and uploaded by the user; the application does not generate or edit them.
- The emotion-to-blendshape mapping is a fixed application-level configuration in v1; per-assistant customization of blendshape values is out of scope.
- Lip sync (driving mouth viseme shapes from audio) is out of scope for v1.
- The portrait area's existing dimensions and layout remain unchanged; the 3D canvas fills the same space as the current image.
- WebGL support is assumed for any browser where a user would configure a 3D avatar — no server-side rendering fallback for the 3D model is required.
- VRM file storage uses the same underlying storage mechanism as existing emotion images and videos in the application.
- Portrait type and uploaded files are fully independent in both directions: changing portrait type never deletes stored files. A user must explicitly delete a VRM file or emotion image via the respective delete controls.
- Spring bone physics (hair/clothing movement) may or may not be active in v1 — this is a rendering-quality detail left to implementation, not a specified requirement.
- The `[flustered]` emotion and any other custom tags not matching a standard VRM expression name will map to the closest reasonable combination of standard blendshapes, documented in the implementation plan.
