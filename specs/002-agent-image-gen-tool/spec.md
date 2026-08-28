# Feature Specification: Agent Image Generation Tool

**Feature Branch**: `002-agent-image-gen-tool`

**Created**: 2026-08-27

**Status**: Draft

**Input**: User description: "Create a new tool or skill for creating images, which can be called by agents. This implementation must reuse the already created chat command /create-image, so most of the implementation might need to be abstracted into its own classes that both the skill and the command will call"

## Clarifications

### Session 2026-08-27

- Q: Should the spec's "looks and behaves the same as the manual command" language (User Story 2, FR-004) be loosened to describe consistency in storage/rendering mechanism rather than requiring the image and the assistant's comment to land in one combined message? → A: Yes — update the spec to describe consistency as the same underlying storage and in-conversation rendering mechanism; the image and the assistant's accompanying comment may appear as two separate messages rather than one combined message.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Assistant generates an image mid-task (Priority: P1)

While an agent-mode assistant is working through a task, the user asks for an image (e.g. "show me what that would look like") without typing any special command. The assistant recognizes the request, generates the image as one of its tool calls, and continues the conversation with the image delivered inline — the user never has to know a manual image command even exists.

**Why this priority**: This is the entire point of the feature — without it, agent-mode assistants can talk about images but can't produce them, which is a visible capability gap compared to what a user can already do manually.

**Independent Test**: Enable agent mode with image generation configured on a test assistant, send a message that implies wanting an image, and confirm an image is generated and shown in the conversation without any manual command being used.

**Acceptance Scenarios**:

1. **Given** an agent-mode assistant with image generation configured, **When** the user's message leads the assistant to decide an image is needed, **Then** the assistant generates the image and it appears in the conversation within the same task.
2. **Given** an agent-mode assistant without image generation configured, **When** the assistant is working a task, **Then** the assistant does not have an image-generation capability available to it.

---

### User Story 2 - Consistent results with the existing manual command (Priority: P2)

A user who sometimes manually asks for an image and sometimes lets the assistant generate one automatically expects the same experience either way: same visual style, same underlying storage and viewing mechanism in the conversation, same per-assistant provider configuration.

**Why this priority**: Divergent behavior between the manual command and the automated tool would confuse users and make the assistant's image output feel inconsistent or lower quality depending on how it was triggered.

**Independent Test**: Generate an image manually via the existing command and generate one automatically via the assistant tool for the same assistant, and confirm both use the same provider/model configuration, the same prompt handling, and the same in-conversation presentation.

**Acceptance Scenarios**:

1. **Given** an assistant with a specific image generation provider/model configured, **When** an image is generated automatically by the assistant's tool, **Then** it uses that same configuration rather than a separate default.
2. **Given** an image generated automatically by the assistant, **When** it is displayed in the conversation, **Then** it uses the same underlying storage and viewing mechanism as an image generated through the manual command (stored, attached to the conversation, viewable the same way), even though the image and the assistant's accompanying comment may appear as two separate messages rather than one combined message.

---

### User Story 3 - Graceful handling of slow or failed generation (Priority: P3)

Unlike the assistant's existing instant tools (looking up the time, doing arithmetic), image generation takes real time and can fail (misconfigured provider, upstream error). The task loop must not stall silently or crash the conversation when this happens.

**Why this priority**: This protects the reliability of the overall task loop — a slow or failing tool must degrade gracefully rather than breaking the assistant's ability to finish the task or respond to the user.

**Independent Test**: Trigger an image generation that fails (e.g. invalid configuration) and confirm the assistant surfaces a clear failure in the conversation instead of the task hanging or ending with no response.

**Acceptance Scenarios**:

1. **Given** an image generation request that fails, **When** the failure occurs, **Then** the assistant informs the user something went wrong instead of leaving the task incomplete with no explanation.
2. **Given** an image generation request that takes as long as a typical successful generation normally takes, **When** it completes successfully, **Then** it is not mistakenly treated as a timed-out or failed tool call.

---

### Edge Cases

- What happens when the assistant is in agent mode but has no image generation provider/model configured? The image-generation capability is simply not available to it.
- What happens when generation takes longer than the time normally allotted to a tool call? It is treated as a failed tool call and surfaced to both the assistant and the user, consistent with how other tool failures are already handled.
- What happens when the assistant invokes the tool with a missing or empty prompt? The request is rejected with a clear error, without generating an image or crashing the task.
- What happens if the assistant generates more than one image within the same task? Each generation is treated as its own tool call and counts toward the task's existing overall step limit; no separate image-specific limit applies.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST allow agent-mode assistants to generate images as part of their automated tool-calling capability, without requiring the end user to invoke a manual image-generation command.
- **FR-002**: Images generated through the assistant's tool MUST be produced using the same per-assistant image generation provider and model configuration already used for manually requested images.
- **FR-003**: Images generated through the assistant's tool MUST have their prompts enhanced using the same enhancement process already applied to manually requested images.
- **FR-004**: Images generated through the assistant's tool MUST use the same underlying storage and in-conversation rendering mechanism as manually requested images; the image and the assistant's accompanying comment MAY appear as two separate messages rather than one combined message.
- **FR-005**: The system MUST only offer the image-generation tool to assistants that are both in agent mode and have image generation configured.
- **FR-006**: If image generation fails for any reason (misconfiguration, provider error, timeout), the system MUST surface a clear failure to the assistant's task loop and to the user, rather than leaving the task stalled or silently incomplete.
- **FR-007**: The system MUST accommodate the normal time a successful image generation takes without treating it as a tool failure due to timing alone.
- **FR-008**: The system MUST reject a tool invocation that has a missing or empty image prompt, without generating an image.
- **FR-009**: An assistant MUST be able to use the image-generation tool more than once within a single task, subject to the same overall step limits already applied to its other tools.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can receive an assistant-generated image within an ongoing conversation without ever needing to know a manual image command exists.
- **SC-002**: 100% of images generated automatically by an assistant use that assistant's own configured provider/model, matching what the manual command would have used.
- **SC-003**: At least 95% of successful image generations complete without the task loop reporting a timeout failure.
- **SC-004**: When generation fails, the user sees a clear explanation in the conversation within the same turn, rather than a stalled or broken chat.
- **SC-005**: Turning on automated image generation for an assistant requires no configuration beyond what is already required for the existing manual image command.

## Assumptions

- The existing manual `/create-image` command's own behavior and UI are unaffected by this feature; this feature only adds an equivalent automated path for agent-mode assistants.
- No new image generation provider or service is introduced — this feature reuses the same provider/model configuration, prompt handling, and storage already in place for the manual command, ensuring consistent results by construction (per User Story 2).
- No additional per-task cap on the number of images generated is needed beyond the task's existing overall step limit.
- The assistant's own tool result does not need to convey the image's visual content back to the model — a confirmation/reference is sufficient, since the image itself is delivered to the user directly in the conversation, matching today's manual command behavior.
- Assistants without image generation configured simply do not have this capability offered to them; this is not treated as an error state.
