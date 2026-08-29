# Feature Specification: 3D Avatar Scene Backgrounds

**Feature Branch**: `005-avatar-backgrounds`

**Created**: 2026-08-28

**Status**: Draft

**Input**: User description: "we need to implement the ability to create backgrounds for the 3D avatars, this might be a skill or a command that can be called. If invoked as a tool a LLM call will shape the prompt for the image model generator and it can be done automatically when the setting of the conversation changes. Let's think about maybe a bar, maybe a penthouse, information can be replicated more accurately by checking RAG, for instance, if the characters are in a specific location that is described in an archive entry, When initiating a conversation, we shall get info from the initial message to create the first background, But backgrounds can also be invoked directly with a command, or if the AI is an agent mode, just asking it directly something like 'change the background to a futuristic park' The background must consist of two images actually, a floor and the actual surroundings, a floor might be place in the 3D space, obviously on the floor, and the other image in a curved plane to give the illusion of depth, since camera is not implemented yet and even when implemented it will be limited, no ceiling or front is necessary, only thing needed is placing the generated image in a curved plane and it will create a background for the character."

## Clarifications

### Session 2026-08-28

- Q: Should a cached background ever be reused across different conversations (e.g. two separate conversations with the same assistant both set "the neon bar"), or should the cache always be scoped to one conversation only? → A: Per-conversation only — each conversation caches and regenerates its own background independently, never reused elsewhere.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Change the background on request (Priority: P1)

A user chatting with a 3D avatar assistant asks for a specific setting — either through an explicit command or, when the assistant is in agent mode, by simply asking in natural language ("change the background to a futuristic park") — and the scene behind the avatar updates to match.

**Why this priority**: This is the simplest, most controllable entry point into the feature. It delivers the core value (a generated scene behind the avatar) without depending on automatic detection logic, and can be built, demoed, and validated on its own.

**Independent Test**: Start a conversation with a 3D-avatar assistant, issue a background request (via command or agent-mode request), and confirm a new scene appears behind the avatar that visually matches the requested description.

**Acceptance Scenarios**:

1. **Given** an assistant in agent mode with no background set yet, **When** the user asks to change the background to a described setting (e.g. "a futuristic park"), **Then** a scene matching that description appears behind the avatar.
2. **Given** a background is already showing, **When** the user requests a different setting, **Then** the previous scene is replaced by the newly generated one.
3. **Given** the assistant has an archive entry describing a specific named location (e.g. "the neon-lit bar downtown"), **When** the user asks for that location by name, **Then** the generated scene reflects the details documented in that archive entry rather than a generic guess.
4. **Given** an assistant that is not using a 3D avatar, **When** a background change is requested, **Then** the request has no effect on that assistant's presentation.

---

### User Story 2 - Automatic scene at conversation start (Priority: P2)

When a user starts a new conversation with a 3D-avatar assistant, the system infers a fitting setting from the opening exchange and generates a background automatically, so the avatar is never shown against an empty backdrop.

**Why this priority**: Establishes the scene without requiring the user to know a command exists, which meaningfully improves the first impression of the avatar experience. It depends on the generation capability from User Story 1 but adds its own trigger logic.

**Independent Test**: Start a brand-new conversation with an assistant whose opening message implies a setting, and confirm a matching background appears without the user issuing any explicit background request.

**Acceptance Scenarios**:

1. **Given** an assistant's opening message describes or implies a setting, **When** a user starts a new conversation, **Then** a background matching that setting is generated and shown automatically.
2. **Given** no particular location can be inferred from the opening exchange, **When** a new conversation starts, **Then** the system falls back to a reasonable default scene rather than showing nothing or failing visibly.

---

### User Story 3 - Automatic scene updates as the story moves (Priority: P3)

As an ongoing conversation's narrative setting shifts (for example, the characters move from a bar to a penthouse), the background updates automatically to reflect the new setting, without the user needing to issue a command.

**Why this priority**: This is the most "alive" version of the feature but also the most complex, since it depends on reliably noticing that the setting changed mid-conversation. It builds on Stories 1 and 2 and is the least critical to ship first.

**Independent Test**: Run a conversation where the narrated setting clearly changes partway through, and confirm the background updates to the new setting without any manual background request.

**Acceptance Scenarios**:

1. **Given** a conversation currently showing one setting, **When** the narrative clearly moves to a different location, **Then** the background is regenerated to reflect the new location without the user asking for it.
2. **Given** the setting has not meaningfully changed, **When** the conversation continues, **Then** the background is left as-is rather than being regenerated unnecessarily.

---

### Edge Cases

- What happens when background image generation fails or times out? The scene MUST keep showing its previous background (or the default) rather than an error state or a blank scene.
- What happens when a requested or inferred setting is too abstract or vague to depict visually (e.g. "nowhere in particular", a purely internal/emotional scene)? The system MUST fall back to a reasonable default rather than failing the request.
- What happens when a manual background request and an automatic detected change happen close together? The most recent request MUST be the one reflected in the final displayed background.
- What happens when the assistant has no linked archive, or the archive has no entry matching the requested/inferred location? The background MUST still be generated from the description alone.
- What happens when a conversation is reopened later? If that conversation's background is still cached, it MUST still be showing; if the cache has been cleared, the system MUST automatically regenerate a background from the conversation's current context rather than showing a blank scene or requiring a manual request.
- What happens while a background is generating? The ongoing conversation MUST NOT be blocked or delayed — the user can keep exchanging messages while generation completes and the scene updates once it's ready.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST generate a background scene for a conversation's 3D avatar from a natural-language description of a setting.
- **FR-002**: System MUST support triggering background generation via an explicit command in the conversation, independent of whether the assistant is in agent mode.
- **FR-003**: System MUST support triggering background generation through a natural-language request directed at the assistant when it is operating in agent mode (e.g. "change the background to a futuristic park").
- **FR-004**: System MUST automatically generate an initial background for a conversation, inferred from the assistant's opening message and/or the user's first message, when a new conversation begins.
- **FR-005**: System MUST detect when the narrated setting of an ongoing conversation has changed and automatically regenerate the background to match, without requiring an explicit user request.
- **FR-006**: Before generating an image, System MUST translate the raw setting description into a detailed image-generation prompt informed by the assistant's persona and the current conversation, consistent with how the assistant's existing image-generation feature shapes prompts.
- **FR-007**: System MUST check the assistant's linked archive for entries describing the requested or inferred location and incorporate any matching documented details into the generated background.
- **FR-008**: A generated background MUST consist of two distinct images: one depicting the ground/floor beneath the avatar, and one depicting the surrounding environment.
- **FR-009**: The floor image MUST be positioned as a flat surface beneath the avatar's feet in the 3D scene.
- **FR-010**: The surrounding-environment image MUST be positioned on a curved surface behind the avatar so as to suggest depth, without requiring any ceiling or front-facing geometry.
- **FR-011**: The generated background MUST read as coherent under the avatar scene's fixed/limited camera view, without depending on a full 360° or free-roaming camera.
- **FR-012**: System MUST cache the most recently generated background for a conversation, scoped to that conversation only, so returning to it while the cache is still available shows the same scene without regenerating it. This cache is temporary, not a permanent archival record — the system is not required to keep a cached background indefinitely.
- **FR-012a**: If a conversation's cached background is no longer available when the user returns to it, System MUST automatically regenerate a background from that conversation's current context, without requiring a manual request.
- **FR-013**: System MUST leave the previously displayed background (or default) in place until generation completes, rather than showing a blank or broken scene while a new background generates.
- **FR-014**: System MUST apply generated backgrounds only to assistants presented via the 3D avatar; assistants without a 3D avatar are unaffected by this feature.
- **FR-015**: Cached backgrounds MUST NOT be reused across different conversations, even for the same assistant and the same setting.
- **FR-016**: Automatically triggered background changes (initial scene and mid-conversation updates) MUST apply immediately without requiring user confirmation.
- **FR-017**: Background generation MUST run without blocking the ongoing conversation — the user MUST be able to send and receive messages normally while a background is being generated, with the scene updating in place once generation completes.
- **FR-018**: Whenever the displayed background changes — from no background to a generated one, or from one generated background to another — the transition MUST use a smooth fade-out/fade-in effect rather than an abrupt swap.

### Key Entities

- **Avatar Background**: The generated scene currently cached for a conversation — a floor image, a surrounding-environment image, and the description/prompt it was generated from. One active background per conversation, held only for as long as it remains cached; not a permanent record.
- **Archive Entry** *(existing)*: A documented piece of assistant knowledge (e.g. a named location) that background generation consults so scenes for documented places stay accurate to what's written about them.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A requested background change is fully generated and displayed within 60 seconds in at least 95% of requests.
- **SC-002**: At least 90% of new conversations show an automatically generated, setting-appropriate background without any manual action from the user.
- **SC-003**: When the requested or inferred location matches a documented archive entry, the generated scene visibly reflects the details written in that entry, confirmed by human review of a sample of generations.
- **SC-004**: Users can continue chatting normally while a background is being generated — no part of the conversation is blocked or delayed by scene generation.
- **SC-005**: Reviewers observing the avatar scene perceive the avatar as standing within an environment (grounded on a floor, surrounded by scenery) rather than floating in front of a flat image.
- **SC-006**: Reviewers observing a background change describe the transition as smooth, not jarring or abrupt.

## Assumptions

- This feature applies only to assistants configured with a 3D avatar; assistants presented through the existing 2D portrait mode are unaffected.
- Background generation reuses the assistant's existing image-generation provider and prompt-shaping pipeline (the same one used for in-chat generated images), including its existing retrieval step against the assistant's linked archive.
- Detecting that the narrated setting has changed during an ongoing conversation is inferred by the system from conversation content itself, the same way the assistant's emotional expression is already inferred from its replies, rather than requiring any new user-facing setting field.
- If background generation fails, the previously displayed background (or the default scene, if none has been generated yet) remains in place rather than leaving a blank or broken scene.
- Manual background requests are available to the same users who can already converse with the assistant; no new permission tier is introduced.
- Cached backgrounds are held in temporary storage rather than the assistant's permanent media storage; exact cache lifetime and eviction policy are implementation decisions left to planning, not fixed by this specification.
- Non-blocking generation (FR-017) means the conversation is never held up waiting on a background; how that concurrency is implemented is left to planning.
