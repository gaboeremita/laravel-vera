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
- Q: Should the uploaded pose animation be limited to `.vrma`, or also accept `.fbx`? → A: Accept both `.fbx` and `.vrma` as valid pose animation uploads. `.fbx` animations retarget onto the avatar's skeleton, so this only works reliably for Mixamo-rigged `.fbx` files (the common case for externally sourced humanoid animations, e.g. from Mixamo's own library) — `.fbx` files from other rigging pipelines are not guaranteed to retarget correctly. FR-005 and FR-010 updated accordingly; a new edge case and assumption cover the Mixamo-rig scope.
- Q: Should poses coexist with the existing emotion system on a 3D avatar assistant, or replace it? → A: Poses fully replace emotions for 3D avatar assistants — poses are the sole expression/action system in that mode, covering both mood/expression and physical actions/gestures. Emotions remain untouched and exclusive to image-portrait assistants. An existing 3D avatar assistant's configured emotions are automatically converted to equivalent poses. User Story 2, FR-016, Key Entities, and Assumptions have been rewritten accordingly; SC-007 has been replaced.
- Q: Should there be a baseline pose for when nothing is triggered, the way image mode has a Default Image? → A: Yes — a name-locked, undeletable "default" pose, optional like every other pose configuration. Its blendshape weights (if any) are the facial baseline whenever no other pose is currently active, and its uploaded animation (if any) loops continuously as the idle stance, interrupted by a triggered pose and resumed once that pose finishes. A new User Story 4 and supporting requirements cover this.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configure a Pose for an Assistant (Priority: P1)

A user editing an assistant that has 3D avatar mode enabled adds a new pose entry — for example, "happy" or "spin" — and names it. For that pose, they may set default blendshape weights (the same kind of weighted-expression controls already used for emotions, e.g. an expression at 80% intensity) for a facial expression, upload an animation file (`.vrma` or `.fbx`) for the body to play back, or both — the two are independent and can be combined on the same pose.

**Why this priority**: Without a way to define poses and their configuration, no other part of the feature can function. This is the foundational setup step every other story depends on.

**Independent Test**: Can be fully tested by opening an assistant's edit page, adding a pose, configuring blendshape weights and/or an uploaded file, saving, and confirming the pose is listed with the configured data persisted.

**Acceptance Scenarios**:

1. **Given** an assistant with 3D avatar mode enabled, **When** the user adds a new pose named "happy" and sets default blendshape weights (e.g., an expression at 80%) without uploading a file, **Then** the pose is saved with only the weighted facial expression configured.
2. **Given** an assistant with 3D avatar mode enabled, **When** the user adds a new pose named "spin" and uploads a valid `.vrma` or `.fbx` file without setting blendshape weights, **Then** the pose is saved with only the body animation configured.
3. **Given** an assistant with 3D avatar mode enabled, **When** the user adds a new pose named "wave" and both sets blendshape weights and uploads a valid animation file, **Then** the pose is saved with both the facial expression and the body animation configured, to be applied together.
4. **Given** a pose already configured with blendshape weights, **When** the user additionally uploads an animation file to the same pose, **Then** the previously entered weight values are retained and the pose now has both configurations active.
5. **Given** an assistant with 3D avatar mode disabled (image portrait mode), **When** the user views the assistant edit page, **Then** no pose configuration controls are shown, since poses only apply to 3D avatar mode.
6. **Given** a pose configured for one assistant, **When** a different assistant's edit page or chat session is loaded, **Then** that pose is not visible or usable by the other assistant.

---

### User Story 2 - LLM-Triggered Pose Playback in Chat (Priority: P2)

During a conversation with a 3D avatar assistant, the user asks the character to express a mood or do something physical — "you seem happy today," "do a spin," "dance for me." The LLM recognizes this maps to a configured pose and signals it. The avatar performs the corresponding animation and/or holds the corresponding weighted expression — whichever the pose has configured. Poses are the sole expression/action system for a 3D avatar assistant: they cover both mood/expression and physical actions/gestures, replacing the emotion-tag system that image-portrait assistants use instead.

**Why this priority**: This is the payoff of the feature — without playback triggered by the LLM, defining poses in Story 1 has no visible effect. It depends on Story 1 because a pose must exist and be configured before it can be triggered.

**Independent Test**: Can be fully tested by configuring a pose, sending a chat message that should trigger it (e.g., "do a spin"), and observing the avatar perform the associated animation and/or expression.

**Acceptance Scenarios**:

1. **Given** an assistant with a "spin" pose configured using only an uploaded animation file (`.vrma` or `.fbx`), **When** the user asks the character to spin and the LLM response signals the pose, **Then** the avatar plays the uploaded spin animation once and returns to its idle state.
2. **Given** an assistant with a "happy" pose configured using only default blendshape weights, **When** the LLM response signals that pose, **Then** the avatar applies the configured weighted expression, the same way the existing emotion system applies weighted expressions for image-portrait assistants.
3. **Given** an assistant with a "wave" pose configured with both blendshape weights and an uploaded `.vrma` file, **When** the LLM response signals that pose, **Then** the avatar plays the body animation and applies the facial expression simultaneously.
4. **Given** an assistant with no poses configured, **When** the user asks the character to perform a physical action, **Then** the LLM is not prompted with any pose options and does not attempt to signal a pose.
5. **Given** an assistant in 3D avatar mode, **When** a chat session starts, **Then** the LLM is never prompted with emotion tags for that assistant — only pose tags.
6. **Given** an assistant in image portrait mode (no 3D avatar), **When** the LLM would otherwise signal a pose, **Then** no pose-related prompt guidance is provided to the LLM for that assistant, and nothing errors if a pose-like request is made in chat.

---

### User Story 3 - Pose-Aware Prompt Guidance for the LLM (Priority: P3)

The assistant's system prompt gains a "pose tags" section listing the assistant's configured pose names, the same way the emotion system exposes an "emotion tags" section — a list of available names the assistant creator's own prompt content (authored the same way emotion-tag instructions already are) can reference and explain to the LLM.

**Why this priority**: Without prompt guidance, the LLM has no way to know poses exist or how to invoke them, making Story 2 unreliable in practice. It is lower priority than Stories 1 and 2 because a reasonable initial version could ship with a minimal, generic explanation; refining that explanation is incremental polish.

**Independent Test**: Can be fully tested by configuring one or more poses on an assistant, inspecting the system prompt sent to the LLM, and confirming it lists the configured pose names under a pose tags section.

**Acceptance Scenarios**:

1. **Given** an assistant with poses named "spin" and "dance" configured, **When** a chat session starts, **Then** the system prompt sent to the LLM includes a pose tags section listing both names.
2. **Given** an assistant with no poses configured, **When** a chat session starts, **Then** the system prompt contains no pose-related guidance.
3. **Given** a 3D avatar assistant, **When** a chat session starts, **Then** the system prompt never includes an emotion tags section for that assistant — only pose tags, exclusively.

---

### User Story 4 - Default Pose as the Idle Baseline (Priority: P2)

A user editing a 3D avatar assistant configures a dedicated "default" pose — a name-locked, undeletable entry that always exists in the pose list, mirroring how image-portrait assistants have a "Default Image". Its blendshape weights, if set, are the facial baseline shown whenever no triggered pose is currently active. Its uploaded animation file, if set, loops continuously as the avatar's idle stance, is interrupted by a triggered pose, and resumes automatically once that pose finishes. Left entirely unconfigured, the avatar falls back to its existing hardcoded idle/neutral behavior.

**Why this priority**: Without a configurable baseline, every 3D avatar assistant is stuck with the same generic idle stance and expression. It's independent of a specific triggered pose existing, but depends on Story 1's pose infrastructure.

**Independent Test**: Can be fully tested by configuring the default pose's blendshape weights and/or animation file, confirming the avatar reflects them while idle, and confirming a triggered pose correctly interrupts and then hands back to the default pose afterward.

**Acceptance Scenarios**:

1. **Given** a 3D avatar assistant, **When** the user opens the assistant's edit page, **Then** a dedicated "default" pose section is always present, cannot be renamed, and cannot be deleted.
2. **Given** the default pose has an uploaded animation file configured, **When** no other pose is currently triggered, **Then** the avatar continuously loops that animation as its idle stance.
3. **Given** the default pose's animation is looping, **When** the LLM signals a different pose, **Then** the triggered pose's animation plays and the default loop resumes automatically once it finishes.
4. **Given** the default pose has blendshape weights configured, **When** no other pose's expression is currently active, **Then** the avatar's face reflects the default pose's weights instead of a neutral expression.
5. **Given** the default pose is left entirely unconfigured (no weights, no animation), **When** the avatar is idle, **Then** it uses the same hardcoded neutral/idle behavior it used before this feature existed.

---

### Edge Cases

- What happens when a pose is triggered while a previously triggered pose's animation is still playing?
- What happens when an uploaded animation file is malformed or not a valid `.vrma`/`.fbx` file?
- What happens when an uploaded animation references a skeleton or bone structure incompatible with the assistant's avatar model?
- What happens when an uploaded `.fbx` file is not Mixamo-rigged (different bone-naming convention than the supported retargeting mapping expects)?
- What happens when a pose is signaled by the LLM for an assistant currently in image portrait mode?
- What happens when a user deletes a pose that has an uploaded animation file — is the file removed too?
- What is the maximum acceptable animation file size, and what happens when it is exceeded?
- What happens when two poses are given the same name on the same assistant, or a user tries to name a pose "default"?
- What happens when a pose has neither blendshape weights nor an uploaded file configured (an empty pose)?
- What happens to an existing 3D avatar assistant's configured emotions when this feature ships?
- What happens when a triggered pose's own uploaded animation is a single-frame/near-instant clip rather than a multi-second motion?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow users to define one or more named poses per assistant.
- **FR-002**: System MUST only expose pose configuration controls for assistants in 3D avatar portrait mode, and MUST NOT expose the emotion system for those assistants — poses are the sole expression/action system for 3D avatar mode. Image-portrait assistants keep the emotion system exactly as before and never see pose configuration.
- **FR-003**: For each pose, users MUST be able to configure default blendshape-weight facial expression, an uploaded body animation file, or both simultaneously — the two are independent and combinable rather than mutually exclusive.
- **FR-004**: System MUST allow a user to add, change, or remove a pose's blendshape weights and its uploaded animation file independently of one another, without one affecting the other's configured data.
- **FR-005**: System MUST validate uploaded pose files are valid `.vrma` or `.fbx` format and reject uploads that are neither.
- **FR-006**: System MUST store each uploaded animation file associated with the specific pose and assistant that owns it, scoped so no other assistant can access it.
- **FR-007**: System MUST allow users to rename, reconfigure, and delete existing poses.
- **FR-008**: System MUST include each assistant's configured pose names in the system prompt sent to the LLM, alongside guidance that poses represent physical actions or gestures the character can perform, distinct from emotional expressions.
- **FR-009**: System MUST omit pose-related prompt guidance entirely for assistants with no poses configured, and for assistants in image portrait mode.
- **FR-010**: System MUST play the assigned animation (`.vrma` or `.fbx`) on the 3D avatar's body when the LLM signals a pose that has an uploaded animation file configured, during an active chat session with that assistant in 3D avatar mode.
- **FR-011**: System MUST apply the pose's configured blendshape weights as a facial expression when the LLM signals a pose that has blendshape weights configured, using the same expression-application mechanism as the existing emotion system.
- **FR-012**: System MUST apply both the body animation and the facial expression together, without either blocking the other, when the LLM signals a pose that has both an uploaded file and blendshape weights configured.
- **FR-013**: System MUST NOT error or crash the chat session if a pose is signaled for an assistant currently in image portrait mode; the signal is silently ignored for display purposes.
- **FR-014**: System MUST enforce a maximum animation file size of 10 MB per upload, regardless of format (`.vrma` or `.fbx`).
- **FR-015**: A triggered pose animation MUST play once and then return the avatar to its idle baseline — the default pose's looping animation if one is configured, otherwise the existing hardcoded idle behavior.
- **FR-016**: System MUST treat pose tags and emotion tags as mutually exclusive by portrait type: a 3D avatar assistant is never prompted with emotion tags and an image-portrait assistant is never prompted with pose tags, so the two signal types never need to be reconciled within one response.
- **FR-017**: System MUST retarget an uploaded `.fbx` animation onto the assistant's avatar skeleton using a Mixamo bone-naming mapping; `.fbx` files rigged with a different bone-naming convention are not guaranteed to animate correctly.
- **FR-018**: System MUST provide a dedicated "default" pose per 3D avatar assistant that always exists, cannot be renamed, and cannot be deleted — configuring it is optional, mirroring how image-portrait assistants have an undeletable "Default Image".
- **FR-019**: When no triggered pose is currently active, the system MUST apply the default pose's blendshape weights (if configured) as the facial baseline and loop the default pose's uploaded animation (if configured) as the idle stance; a triggered pose interrupts the loop and it resumes once that pose finishes.
- **FR-020**: When a 3D avatar assistant already has configured emotions at the time this feature is enabled, the system MUST automatically convert each existing emotion into an equivalent pose, so no existing expressive configuration is lost.

### Key Entities *(include if feature involves data)*

- **Pose**: A named, per-assistant configuration representing a mood/expression or a physical action/gesture the 3D avatar can perform — the sole expression/action system for 3D avatar assistants, replacing emotions in that mode. Has a name (the tag the LLM uses to invoke it), and optionally: weighted-expression data for a facial expression (structurally similar to the existing per-emotion blendshape weights) and an association to an uploaded animation file (`.vrma` or `.fbx`) for a body animation. Either, both, or (transiently) neither may be set. The pose named "default" is a distinguished instance: always present, name-locked, undeletable, and used as the idle baseline rather than a one-off trigger.
- **Pose Animation File**: A stored `.vrma` or `.fbx` asset associated with exactly one pose, containing a playable body animation clip for the 3D avatar. `.fbx` uploads are retargeted onto the avatar's skeleton at playback using a Mixamo bone-naming mapping. On the default pose, this clip loops continuously instead of playing once.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can add a new pose to an assistant, configure blendshape weights and/or an uploaded file, and save it in under 2 minutes.
- **SC-002**: When a chat message clearly requests a configured pose (e.g., "do a spin"), the avatar begins performing the corresponding animation and/or expression within 2 seconds of the response being received.
- **SC-003**: Assistants in image portrait mode show zero behavior change from the pose system — full backward compatibility with existing emotion and image behavior.
- **SC-004**: Uploading a file that is not a valid `.vrma` or `.fbx` format is rejected with a clear error message 100% of the time.
- **SC-005**: Adding or removing a pose's blendshape weights or its uploaded animation file leaves the other, independently configured half of that pose unaffected, with no data loss.
- **SC-006**: A pose configured with both a body animation and a facial expression displays both simultaneously when triggered, with no dropped signal.
- **SC-007**: A 3D avatar assistant that had configured emotions before this feature shipped has each converted to an equivalent pose, with zero manual reconfiguration required.
- **SC-008**: A configured default-pose animation loops indefinitely whenever no other pose is active, and correctly resumes after any triggered pose finishes, with no dropped loop and no visible snap on either transition.

## Assumptions

- Poses apply only to assistants in 3D avatar portrait mode and fully replace the emotion system there; the feature has no effect on assistants using the existing image-based portrait system, which keep emotions unchanged.
- Animation files (`.vrma` and `.fbx`) are authored externally (e.g., via motion-capture tooling, the VRM ecosystem, or Mixamo's animation library) and uploaded by the user; the application does not generate or edit animation content.
- Uploaded animation clips (`.vrma` and `.fbx`) typically animate the body skeleton (arms, spine, legs, etc.) and do not include facial blendshape data. This is why a pose's uploaded animation and its blendshape-weight expression can be configured and applied together without conflicting on the same channels.
- `.fbx` support in v1 is scoped to Mixamo-rigged animations — the common case for externally sourced humanoid animations (e.g. downloaded directly from Mixamo's library). An `.fbx` file exported from a different rigging pipeline, with different bone names, is accepted by upload validation but is not guaranteed to retarget or animate correctly on playback.
- The blendshape-weight side of a pose configuration reuses the same weighting mechanism already used by the emotion system (name plus intensity) — it is not a new kind of control.
- Pose names and emotion names are namespaces belonging to mutually exclusive portrait types — an assistant is in exactly one mode at a time, so there is never a case where the same assistant has both an active emotion and an active pose to reconcile.
- The LLM-facing wording of the pose-tag instruction (what poses are, how to signal them) is authored per assistant in that assistant's own prompt configuration, the same way emotion-tag wording already is — the system only ever injects the assistant's own configured pose names into that section, not a fixed instruction string.
- There is no hard limit on the number of poses an assistant can define, consistent with there being no stated limit on emotions today.
- Pose animation playback is a client-side visual effect layered on top of an ongoing chat session; triggering a pose does not block or delay the LLM's response generation.
- Animation file storage (`.vrma` and `.fbx`) follows the same underlying storage mechanism already used for VRM model files and emotion images/videos in the application.
- Deleting a pose also deletes its associated uploaded animation file, mirroring how other per-assistant uploaded assets are cleaned up when their owning record is removed.
