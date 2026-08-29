# Feature Specification: VRMA Avatar Pose Animations

**Feature Branch**: `006-vrma-avatar-poses`

**Created**: 2026-08-29

**Status**: Draft

**Input**: User description: "we want to use vrma files for animations, instead of the current implementation. This will require a system different from the ones used in images mode. The avatar mode will work with "poses" and it will be divorced from the emotion system, although it will be similar and equivalent in 3d avatar form. In order to map we will add a new pose in the assistant edit/create page, let's say "happy" and then upload a vrma file for it, but this won't replace the previous system, rather, we will need to choose with a toggle between the default options (like setting happy to 0.8%) or uploading the file instead. The prompting for this might needed to be added to the assistants, just like we have emotions, adding a pose entry, explaining what to do. This system is more robust than the image system, so the prompt needs to let the LLM know what this is. For example, I could ask the character to do a spin, or a dance, this is not an emotion, but the character will still be able to do it"

## Clarifications

### Session 2026-08-29

- Q: What is the maximum allowed `.vrma` file size for an uploaded pose animation? → A: 10 MB, matching the existing image/emotion upload ceiling (FR-014 updated accordingly).
- Q: Should a triggered pose animation play once and return to idle, or loop until interrupted? → A: Play once, then return to the avatar's existing idle animation loop; the idle loop itself is pre-existing behavior, unaffected by this feature (FR-015 updated accordingly).
- Q: Should a pose require an exclusive choice between default blendshape weights or an uploaded `.vrma` file? → A: No — the two are combinable rather than mutually exclusive. A pose may have blendshape weights, an uploaded animation file, or both at once, since `.vrma` animations typically drive only the body skeleton and leave facial blendshapes untouched. User Story 1, FR-002 through FR-004, FR-010 through FR-012, Key Entities, and Assumptions have been rewritten accordingly.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configure a Pose for an Assistant (Priority: P1)

A user editing an assistant that has 3D avatar mode enabled adds a new pose entry — for example, "happy" or "spin" — and names it. For that pose, they may set default blendshape weights (the same kind of weighted-expression controls already used for emotions, e.g. an expression at 80% intensity) for a facial expression, upload a `.vrma` animation file for the body to play back, or both — the two are independent and can be combined on the same pose.

**Why this priority**: Without a way to define poses and their configuration, no other part of the feature can function. This is the foundational setup step every other story depends on.

**Independent Test**: Can be fully tested by opening an assistant's edit page, adding a pose, configuring blendshape weights and/or an uploaded file, saving, and confirming the pose is listed with the configured data persisted.

**Acceptance Scenarios**:

1. **Given** an assistant with 3D avatar mode enabled, **When** the user adds a new pose named "happy" and sets default blendshape weights (e.g., an expression at 80%) without uploading a file, **Then** the pose is saved with only the weighted facial expression configured.
2. **Given** an assistant with 3D avatar mode enabled, **When** the user adds a new pose named "spin" and uploads a valid `.vrma` file without setting blendshape weights, **Then** the pose is saved with only the body animation configured.
3. **Given** an assistant with 3D avatar mode enabled, **When** the user adds a new pose named "wave" and both sets blendshape weights and uploads a valid `.vrma` file, **Then** the pose is saved with both the facial expression and the body animation configured, to be applied together.
4. **Given** a pose already configured with blendshape weights, **When** the user additionally uploads a `.vrma` file to the same pose, **Then** the previously entered weight values are retained and the pose now has both configurations active.
5. **Given** an assistant with 3D avatar mode disabled (image portrait mode), **When** the user views the assistant edit page, **Then** no pose configuration controls are shown, since poses only apply to 3D avatar mode.
6. **Given** a pose configured for one assistant, **When** a different assistant's edit page or chat session is loaded, **Then** that pose is not visible or usable by the other assistant.

---

### User Story 2 - LLM-Triggered Pose Playback in Chat (Priority: P2)

During a conversation with a 3D avatar assistant, the user asks the character to do something physical that isn't an emotion — "do a spin," "dance for me." The LLM recognizes this maps to a configured pose and signals it. The avatar performs the corresponding animation and/or holds the corresponding weighted expression — whichever the pose has configured — distinct from and in addition to any emotional expression already on the character's face.

**Why this priority**: This is the payoff of the feature — without playback triggered by the LLM, defining poses in Story 1 has no visible effect. It depends on Story 1 because a pose must exist and be configured before it can be triggered.

**Independent Test**: Can be fully tested by configuring a pose, sending a chat message that should trigger it (e.g., "do a spin"), and observing the avatar perform the associated animation and/or expression.

**Acceptance Scenarios**:

1. **Given** an assistant with a "spin" pose configured using only an uploaded `.vrma` file, **When** the user asks the character to spin and the LLM response signals the pose, **Then** the avatar plays the uploaded spin animation once and returns to its idle state.
2. **Given** an assistant with a "happy" pose configured using only default blendshape weights, **When** the LLM response signals that pose, **Then** the avatar applies the configured weighted expression, the same way the existing emotion system applies weighted expressions.
3. **Given** an assistant with a "wave" pose configured with both blendshape weights and an uploaded `.vrma` file, **When** the LLM response signals that pose, **Then** the avatar plays the body animation and applies the facial expression simultaneously.
4. **Given** an assistant with no poses configured, **When** the user asks the character to perform a physical action, **Then** the LLM is not prompted with any pose options and does not attempt to signal a pose.
5. **Given** a chat session with a 3D avatar assistant, **When** the LLM signals both an emotion tag and a pose in the same response (e.g., a happy expression while performing a dance), **Then** both are applied to the avatar without one overriding or blocking the other.
6. **Given** an assistant in image portrait mode (no 3D avatar), **When** the LLM would otherwise signal a pose, **Then** no pose-related prompt guidance is provided to the LLM for that assistant, and nothing errors if a pose-like request is made in chat.

---

### User Story 3 - Pose-Aware Prompt Guidance for the LLM (Priority: P3)

The assistant's system prompt is updated to describe the pose system to the LLM: what poses are available by name, and that poses represent physical actions or gestures the character can perform on request — separate from and in addition to emotional expressions.

**Why this priority**: Without prompt guidance, the LLM has no way to know poses exist or how to invoke them, making Story 2 unreliable in practice. It is lower priority than Stories 1 and 2 because a reasonable initial version could ship with a minimal, generic explanation; refining that explanation is incremental polish.

**Independent Test**: Can be fully tested by configuring one or more poses on an assistant, inspecting the system prompt sent to the LLM, and confirming it lists the configured pose names along with guidance distinguishing poses from emotions.

**Acceptance Scenarios**:

1. **Given** an assistant with poses named "spin" and "dance" configured, **When** a chat session starts, **Then** the system prompt sent to the LLM includes both pose names and an explanation that they represent physical actions, distinct from the assistant's emotion tags.
2. **Given** an assistant with no poses configured, **When** a chat session starts, **Then** the system prompt contains no pose-related guidance.
3. **Given** an assistant with both emotions and poses configured, **When** a chat session starts, **Then** the system prompt clearly presents emotions and poses as two separate, non-overlapping sets of signals the LLM may emit.

---

### Edge Cases

- What happens when a pose is triggered while a previously triggered pose's animation is still playing?
- What happens when an uploaded `.vrma` file is malformed or not a valid VRMA-format animation?
- What happens when an uploaded `.vrma` animation references a skeleton or bone structure incompatible with the assistant's avatar model?
- What happens when a pose is signaled by the LLM for an assistant currently in image portrait mode?
- What happens when a user deletes a pose that has an uploaded animation file — is the file removed too?
- What happens when a pose's own configured blendshape weights and a simultaneously active emotion's blendshape weights target the same facial expression?
- What is the maximum acceptable `.vrma` file size, and what happens when it is exceeded?
- What happens when two poses are given the same name on the same assistant?
- What happens when a pose has neither blendshape weights nor an uploaded file configured (an empty pose)?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow users to define one or more named poses per assistant, independently of that assistant's existing emotions list.
- **FR-002**: System MUST only expose pose configuration controls for assistants in 3D avatar portrait mode; poses are not applicable to image portrait mode.
- **FR-003**: For each pose, users MUST be able to configure default blendshape-weight facial expression, an uploaded `.vrma` body animation file, or both simultaneously — the two are independent and combinable rather than mutually exclusive.
- **FR-004**: System MUST allow a user to add, change, or remove a pose's blendshape weights and its uploaded animation file independently of one another, without one affecting the other's configured data.
- **FR-005**: System MUST validate uploaded pose files are valid `.vrma` format and reject uploads that are not.
- **FR-006**: System MUST store each uploaded `.vrma` file associated with the specific pose and assistant that owns it, scoped so no other assistant can access it.
- **FR-007**: System MUST allow users to rename, reconfigure, and delete existing poses.
- **FR-008**: System MUST include each assistant's configured pose names in the system prompt sent to the LLM, alongside guidance that poses represent physical actions or gestures the character can perform, distinct from emotional expressions.
- **FR-009**: System MUST omit pose-related prompt guidance entirely for assistants with no poses configured, and for assistants in image portrait mode.
- **FR-010**: System MUST play the assigned `.vrma` animation on the 3D avatar's body when the LLM signals a pose that has an uploaded animation file configured, during an active chat session with that assistant in 3D avatar mode.
- **FR-011**: System MUST apply the pose's configured blendshape weights as a facial expression when the LLM signals a pose that has blendshape weights configured, using the same expression-application mechanism as the existing emotion system.
- **FR-012**: System MUST apply both the body animation and the facial expression together, without either blocking the other, when the LLM signals a pose that has both an uploaded file and blendshape weights configured.
- **FR-013**: System MUST NOT error or crash the chat session if a pose is signaled for an assistant currently in image portrait mode; the signal is silently ignored for display purposes.
- **FR-014**: System MUST enforce a maximum `.vrma` file size of 10 MB per upload.
- **FR-015**: A triggered pose animation MUST play once and then return the avatar to its existing idle animation loop; the idle loop itself is pre-existing behavior and is unaffected by this feature.
- **FR-016**: System MUST allow a pose signal and an emotion tag to be applied to the avatar concurrently within the same response without either overriding the other.

### Key Entities *(include if feature involves data)*

- **Pose**: A named, per-assistant configuration representing a physical action or gesture the 3D avatar can perform. Has a name (the tag the LLM uses to invoke it), and optionally: weighted-expression data for a facial expression (structurally similar to the existing per-emotion blendshape weights) and an association to an uploaded `.vrma` animation file for a body animation. Either, both, or (transiently) neither may be set.
- **Pose Animation File**: A stored `.vrma`-format asset associated with exactly one pose, containing a playable body animation clip for the 3D avatar.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can add a new pose to an assistant, configure blendshape weights and/or an uploaded file, and save it in under 2 minutes.
- **SC-002**: When a chat message clearly requests a configured pose (e.g., "do a spin"), the avatar begins performing the corresponding animation and/or expression within 2 seconds of the response being received.
- **SC-003**: Assistants with no poses configured, and assistants in image portrait mode, show zero behavior change from the pose system — full backward compatibility with existing emotion and image behavior.
- **SC-004**: Uploading a file that is not valid `.vrma` format is rejected with a clear error message 100% of the time.
- **SC-005**: Adding or removing a pose's blendshape weights or its uploaded animation file leaves the other, independently configured half of that pose unaffected, with no data loss.
- **SC-006**: A pose configured with both a body animation and a facial expression displays both simultaneously when triggered, with no dropped signal.
- **SC-007**: An assistant with both a pose and an emotion signaled in the same response displays both simultaneously with no visual conflict or dropped signal.

## Assumptions

- Poses apply only to assistants in 3D avatar portrait mode; the feature has no effect on assistants using the existing image-based portrait system.
- `.vrma` files are authored externally (e.g., via motion-capture or animation tooling compatible with the VRM ecosystem) and uploaded by the user; the application does not generate or edit animation content.
- `.vrma` animation clips typically animate the body skeleton (arms, spine, legs, etc.) and do not include facial blendshape data. This is why a pose's uploaded animation and its blendshape-weight expression can be configured and applied together without conflicting on the same channels.
- The blendshape-weight side of a pose configuration reuses the same weighting mechanism already used by the emotion system (name plus intensity) — it is not a new kind of control.
- Pose names and emotion names are independent, separately-purposed tag namespaces communicated to the LLM in separate parts of the system prompt; the same name (e.g., "happy") may exist as both an emotion and a pose on the same assistant without conflict.
- If a pose's own blendshape weights and a simultaneously active emotion's weights target the same facial expression, the more recently triggered signal takes precedence; this is not expected to be a common configuration since poses are intended primarily for body movement.
- There is no hard limit on the number of poses an assistant can define, consistent with there being no stated limit on emotions today.
- Pose animation playback is a client-side visual effect layered on top of an ongoing chat session; triggering a pose does not block or delay the LLM's response generation.
- `.vrma` file storage follows the same underlying storage mechanism already used for VRM model files and emotion images/videos in the application.
- Deleting a pose also deletes its associated uploaded animation file, mirroring how other per-assistant uploaded assets are cleaned up when their owning record is removed.
