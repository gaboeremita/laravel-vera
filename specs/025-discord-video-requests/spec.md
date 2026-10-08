# Feature Specification: Discord Video Requests

**Feature Branch**: `146-discord-video-requests`

**Created**: 2026-10-07

**Status**: Draft

**Input**: User description: "Let users request videos from Discord with `/create-video`, as they can in the web chat. The assistant replies in character right away. An image attached to the request is used as the video's first frame. When the video finishes or fails, the app sends the outcome to the Discord API service so the assistant's bot can post it, retrying when the service does not answer. Telegram and the assistant's own video ability on Discord are out of scope."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Request a video from Discord (Priority: P1)

A message starting with `/create-video` and a description arrives from Discord, from a channel or a DM. The app starts the video exactly as it does for the web chat and answers right away with the assistant's in-character reply, which the Discord API service posts. When the request can't be accepted, the app answers with the same reason the web chat shows.

**Why this priority**: This is the entry point of the feature. Every other story builds on a video started from Discord.

**Independent Test**: Send `/create-video a cat walking on a piano` to the Discord messages endpoint for an assistant with a video model selected, and confirm the response holds only the in-character reply and a video is queued on the assistant's message.

**Acceptance Scenarios**:

1. **Given** an assistant with a video model selected, **When** a Discord message `/create-video` followed by a description arrives, **Then** the app answers with the in-character reply without waiting for the video, and a video is queued on the assistant's message.
2. **Given** the same request, **When** the in-character reply is written, **Then** it carries no emotion or pose tags, as Discord replies never do.
3. **Given** an assistant with no video model selected, **When** a Discord `/create-video` with a description arrives, **Then** the app rejects it with "No video generation model is configured for this assistant." and starts nothing.
4. **Given** a Discord `/create-video` with no description, **When** it arrives, **Then** the app rejects it with "Describe what video to generate after /create-video." and starts nothing.
5. **Given** a video requested from Discord, **When** the user opens that conversation in the web app, **Then** the video is shown in its message as it is for web requests.

---

### User Story 2 - Start a Discord video from an attached image (Priority: P1)

The Discord message carries an attached image along with `/create-video` and a description. The video opens on that image as its first frame.

**Why this priority**: Animating a chosen picture is the main reason to request a video, and Discord messages can already carry an image.

**Independent Test**: With the public address set, send `/create-video the camera slowly pulls back` with an image to the Discord messages endpoint, and confirm the queued video uses the stored image as its first frame.

**Acceptance Scenarios**:

1. **Given** the public address is set, **When** a Discord `/create-video` with a description and an attached image arrives, **Then** the video's first frame is that image.
2. **Given** the public address is not set, **When** a Discord `/create-video` with an attached image arrives, **Then** the app rejects it with "Set PUBLIC_TUNNEL_URL to generate a video from an image." and starts nothing.

---

### User Story 3 - Hand the finished video to Discord (Priority: P1)

When a video requested from Discord finishes or fails, the app sends the outcome to the Discord API service: which assistant's bot posts it, in which channel, in reply to which Discord message, and either where to download the video or why it failed.

**Why this priority**: Without it, a video requested from Discord never reaches Discord.

**Independent Test**: Finish a video in a Discord conversation and confirm the app sends one delivery request to the Discord API service with the assistant, channel, reply target and video address.

**Acceptance Scenarios**:

1. **Given** a video requested from Discord, **When** it finishes, **Then** the app sends the assistant, the channel, the Discord message ID of the `/create-video` message and the video's address to the Discord API service.
2. **Given** a video requested from Discord, **When** it fails, **Then** the app sends the failure reason in place of the video's address.
3. **Given** a video requested in the web app, **When** it finishes or fails, **Then** nothing is sent to the Discord API service.
4. **Given** the `/create-video` message arrived without a Discord message ID, **When** the video finishes, **Then** the delivery carries no reply target.

---

### User Story 4 - Retry delivery when the Discord API service is unavailable (Priority: P2)

When the Discord API service can't be reached as a video finishes, the app tries again for a while before giving up. When the service reports that Discord refused the post, the app stops right away.

**Why this priority**: A short outage of the Discord API service would otherwise lose the video in Discord. The success path in Story 3 works without it.

**Independent Test**: Make the Discord API service unreachable when a video finishes, and confirm the app retries on the stated schedule and logs the failure after the last attempt.

**Acceptance Scenarios**:

1. **Given** the Discord API service is unreachable, **When** a video finishes, **Then** the app retries 5 times, waiting 10 seconds, 30 seconds, 1 minute, 2 minutes and 5 minutes between attempts.
2. **Given** every attempt fails, **When** the last retry ends, **Then** the app logs the failure with the video and conversation, and the video remains available in the web app.
3. **Given** the Discord API service answers that Discord refused the post, or that the app's secret is wrong, **When** delivery is attempted, **Then** the app logs the failure and does not retry.

---

### Edge Cases

- The conversation is deleted after the video finishes but before delivery runs: nothing is sent.
- The user requests a second video while the first is still generating: each video is delivered separately, each with its own reply target.
- Writing the video description or the in-character reply fails: the app answers with the error, and no video is started.
- The Discord API service has no bot running for the assistant: the app retries as for an unreachable service.
- The Discord API service can't download the video from the app: the app retries as for an unreachable service.

## Requirements *(mandatory)*

### Functional Requirements

**Requesting a video from Discord**

- **FR-001**: The app MUST accept `/create-video` followed by a description in Discord messages, from channels and DMs.
- **FR-002**: A Discord video request MUST follow the same rules as a web request: the same description improvement, the same in-character reply, the same use of the requested length, aspect ratio and sound, and the same closest-supported-value fallback.
- **FR-003**: The in-character reply to a Discord video request MUST leave out emotion and pose tags.
- **FR-004**: When the Discord message carries an attached image, the app MUST use that image as the video's first frame, under the same public-address rule as the web chat.
- **FR-005**: The app MUST answer a Discord video request with the in-character reply without waiting for the video.
- **FR-006**: The app MUST reject a Discord video request with the same reasons and wording as the web chat: no description, no video model, an image with no public address, or a failure writing the description or reply.

**Delivering the outcome**

- **FR-007**: When a video belonging to a Discord conversation finishes or fails, the app MUST send the Discord API service the assistant, the Discord channel, the Discord message ID of the request, and either the video's address or the failure reason.
- **FR-008**: The reply target MUST be the Discord message ID of the user message that requested the video, or none when that message has no Discord message ID.
- **FR-009**: The app MUST authenticate delivery with the same shared secret it uses for channel discovery.
- **FR-010**: Videos requested in the web app MUST NOT be sent to the Discord API service.

**Retrying delivery**

- **FR-011**: When the Discord API service can't be reached, has no bot for the assistant, can't download the video, or answers with any other error besides those in FR-013, the app MUST retry delivery 5 times, waiting 10 seconds, 30 seconds, 1 minute, 2 minutes and 5 minutes between attempts.
- **FR-012**: After the last retry fails, the app MUST log the failure with the video and conversation, and stop.
- **FR-013**: When the Discord API service answers that Discord refused the post or that the secret is wrong, the app MUST log the failure and not retry.

### Key Entities

- **Discord delivery**: A request from the app to the Discord API service carrying one video's outcome: the assistant, the Discord channel, the reply target, and either the video's address or the failure reason. Not stored; it exists only while being sent and retried.
- **Video** (existing): A generated video attached to an assistant message. Its conversation's Discord channel and the requesting user message's Discord message ID decide where its outcome is delivered.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The app's answer to a Discord video request arrives as quickly as its answer to a Discord image request, regardless of how long the video takes.
- **SC-002**: Every video requested from Discord produces exactly one delivery to the Discord API service when the service is reachable.
- **SC-003**: The delivery is sent within 30 seconds of the video being stored or marked as failed.
- **SC-004**: An outage of the Discord API service shorter than 9 minutes does not lose a delivery.
- **SC-005**: Videos requested in the web app never produce a delivery.

## Assumptions

- Discord turns run as a single in-character reply without the agent loop, so assistants do not start videos on their own in Discord. Only `/create-video` is offered there.
- Telegram is out of scope.
- The Discord API service runs on the same machine as the app and downloads the finished video from the app's own address. The public address is needed only for a first-frame image, as in the web chat.
- The Discord API service answers a delivery only after posting, so the app waits up to a configurable time for its answer.
- Discord conversation history already includes each video message's description and status, so the assistant can talk about earlier videos in later Discord turns.
- Retries cover only delivery. Generation itself keeps its existing timeout and failure handling.
