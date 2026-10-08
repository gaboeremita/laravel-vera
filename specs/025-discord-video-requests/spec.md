# Feature Specification: Discord Video Requests

**Feature Branch**: `146-discord-video-requests`

**Created**: 2026-10-07

**Status**: Draft

**Input**: User description: "Let users request videos from Discord with `/create-video`, as they can in the web chat. The assistant replies in character right away, and the finished video is posted later by the assistant's bot as a reply to the original message. An image attached to the request is used as the video's first frame. Laravel tells the Discord bridge (node-discord-api) when a video finishes or fails, retrying if the bridge does not answer. The bridge posts the video as an attachment, shrinking it with ffmpeg when it is over the destination's upload limit (first at a bitrate fitted to the limit, then at 480p), and posts a failure notice when it still does not fit or the video failed. Telegram and the assistant's own video ability on Discord are out of scope."

## Clarifications

### Session 2026-10-07

- Q: In a server channel, who should be allowed to start a video with `/create-video`? → A: Only people on the assistant's DM allowlist; anyone else gets a reply saying they can't request videos.
- Q: When Laravel rejects a Discord message with a reason, should the bot show that reason for every kind of message, or only for `/create-video`? → A: Every rejected message shows Laravel's reason; "Connection failed. Try again." stays only for when Laravel can't be reached or gives no reason.
- Q: When a Discord video fails or is too big to post, should the bot's notice be a plain system message or written in the assistant's voice? → A: A plain system message, for example "Video failed: <reason>" or "Video too large for Discord — watch it in the web app".

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ask for a video in Discord and get it as a reply (Priority: P1)

In a Discord channel where the assistant's bot answers, or in a DM with it, the user sends `/create-video` followed by a description. The bot answers in character right away, acknowledging the video it is making. When the video is ready, the bot posts it in the same channel as a reply to the user's `/create-video` message, and it plays inline in Discord. The same video also appears in the conversation's message in the web app.

**Why this priority**: This is the core of the feature: a working path from a Discord request to a playable video in Discord. Every other story builds on it.

**Independent Test**: With a video model selected for the assistant, send `/create-video a cat walking on a piano` in a channel the bot answers in, and confirm the in-character reply appears right away and the video is later posted as a reply to the request.

**Acceptance Scenarios**:

1. **Given** an assistant with a video model selected and a channel where its bot answers, **When** the user sends `/create-video` followed by a description, **Then** the bot replies in character without waiting for the video.
2. **Given** a video requested from Discord, **When** the video finishes, **Then** the bot posts it in the same channel as a reply to the `/create-video` message, and it plays inline.
3. **Given** a video requested from a DM with the bot, **When** the video finishes, **Then** the bot posts it in that DM as a reply to the request.
4. **Given** an assistant with no video model selected, **When** the user sends `/create-video` with a description, **Then** the bot replies that no video model is configured for this assistant, and nothing is generated.
5. **Given** the user sends `/create-video` with no description, **When** the message reaches the assistant, **Then** the bot asks the user to describe the video to generate.
6. **Given** a video requested from Discord, **When** the user opens that conversation in the web app, **Then** the video is shown in its message as it is for web requests.
7. **Given** a server channel where the bot answers, **When** someone who is not on the assistant's DM allowlist sends `/create-video`, **Then** the bot replies that they can't request videos, and nothing is generated.

---

### User Story 2 - Start a Discord video from an attached image (Priority: P1)

The user attaches an image to their Discord message along with `/create-video` and a description. The video opens on that image as its first frame and moves according to the description.

**Why this priority**: Animating a chosen picture is the main reason to request a video, and Discord already lets the user attach an image to a message.

**Independent Test**: With the public address set, attach an image to `/create-video the camera slowly pulls back` in Discord, and confirm the posted video starts on the attached image.

**Acceptance Scenarios**:

1. **Given** the public address is set, **When** the user sends `/create-video` with a description and an attached image, **Then** the posted video's first frame is the attached image.
2. **Given** the public address is not set, **When** the user sends `/create-video` with an attached image, **Then** the bot replies with the same message the web app shows, saying videos from an image need the public address, and nothing is generated.

---

### User Story 3 - Videos too large for Discord are shrunk to fit (Priority: P2)

A finished video is larger than Discord allows for the place it is going (a DM, an unboosted server, or a boosted server with a higher limit). Before posting, the bot re-encodes it so it fits, and posts the smaller copy. The full-size video stays in the web app.

**Why this priority**: Short videos fit without any change, so Story 1 works on its own. Longer or higher-resolution videos would otherwise never reach Discord.

**Independent Test**: Generate a video longer than the destination's upload limit allows at its original size, and confirm the bot posts a playable copy under the limit while the web app keeps the original.

**Acceptance Scenarios**:

1. **Given** a finished video within the destination's upload limit, **When** the bot posts it, **Then** the video is posted unchanged.
2. **Given** a finished video over the destination's upload limit, **When** the bot posts it, **Then** it posts a re-encoded copy at the same resolution that fits under the limit.
3. **Given** a finished video that is still over the limit after re-encoding at the same resolution, **When** the bot posts it, **Then** it posts a copy re-encoded at 480p that fits under the limit.
4. **Given** a finished video that does not fit even at 480p, **When** the bot tries to post it, **Then** it posts a notice as a reply to the request, saying the video was too large for Discord and can be watched in the web app.
5. **Given** a video shrunk for Discord, **When** the user opens the conversation in the web app, **Then** the web app plays the original, full-size video.

---

### User Story 4 - Failures reach the user in Discord (Priority: P2)

When a video requested from Discord fails, the bot posts a short notice as a reply to the request, with the reason. When the Discord bridge cannot be reached as the video finishes, delivery is retried for a while before giving up.

**Why this priority**: Without this, a failed video or a short bridge outage leaves the Discord user waiting for something that never comes. The success path in Story 1 works without it.

**Independent Test**: Request a video with a model that rejects the request, and confirm the bot replies with the failure reason. Separately, stop the bridge while a video is generating, start it again within a few minutes of the video finishing, and confirm the video is still posted.

**Acceptance Scenarios**:

1. **Given** a video requested from Discord, **When** generation fails, **Then** the bot posts a notice as a reply to the request, stating the failure reason.
2. **Given** a video finishes while the bridge is unreachable, **When** the bridge comes back within the retry window, **Then** the video is posted as a reply to the request.
3. **Given** a video finishes while the bridge is unreachable, **When** the bridge stays unreachable for the whole retry window, **Then** delivery stops, the failure is logged, and the video remains available in the web app.

---

### Edge Cases

- The user's `/create-video` message is deleted in Discord before the video finishes: the bot posts the video (or the failure notice) in the same channel without a reply reference.
- The conversation is deleted while its video is generating: nothing is posted to Discord.
- A video is requested in the web app: it is never posted to Discord, even if the same assistant has a Discord bot.
- The assistant's bot is not running in the bridge when delivery is attempted: delivery is retried like an unreachable bridge, then logged and dropped.
- The assistant's DM allowlist is empty: nobody can start a video from Discord, and every `/create-video` gets the "can't request videos" reply.
- The user requests a second video while the first is still generating: each is posted separately as a reply to its own request.
- Writing the video description or the in-character reply fails: the bot replies with the error, and no video is started.
- The bot no longer has access to the channel when the video finishes (for example, it was removed from the server): the bridge reports the post failure, and it is logged without retrying.
- The video file cannot be downloaded from the app by the bridge: the bridge reports the failure, and delivery is retried like an unreachable bridge.

## Requirements *(mandatory)*

### Functional Requirements

**Requesting a video from Discord**

- **FR-001**: Users MUST be able to request a video by sending `/create-video` followed by a description in any Discord channel or DM where the assistant's bot answers.
- **FR-001a**: Only people on the assistant's DM allowlist MUST be able to start a video. A `/create-video` from anyone else, including other bots, MUST get a reply saying they can't request videos, and MUST NOT start a video.
- **FR-002**: A Discord video request MUST follow the same rules as a web request: the same description improvement, the same in-character reply, the same use of the requested length, aspect ratio and sound, and the same closest-supported-value fallback.
- **FR-003**: When the Discord message carries an attached image, the system MUST use that image as the video's first frame, under the same public-address rule as the web app.
- **FR-004**: The in-character reply MUST be posted in Discord without waiting for the video.
- **FR-005**: When the app rejects any Discord message with a reason, the bot MUST reply with that reason, the same wording the web app shows. This covers `/create-video` rejections (no description, no video model, image with no public address, or a failure writing the description or reply) and every other message, including `/create-image`, voice messages and normal chat.
- **FR-005a**: The bot MUST reply "Connection failed. Try again." only when the app cannot be reached or its rejection carries no reason.

**Delivering the finished video**

- **FR-006**: When a video belonging to a Discord conversation finishes or fails, the system MUST send it to the Discord bridge for posting, identifying the assistant, the channel, and the Discord message to reply to.
- **FR-007**: The bridge MUST accept delivery only from the app, using the same shared secret that protects channel discovery.
- **FR-008**: The bridge MUST post a finished video with the assistant's own bot, as a file attachment that plays inline, in reply to the `/create-video` message.
- **FR-009**: The bridge MUST post a failed video as a plain system notice in reply to the `/create-video` message, in the form "Video failed: <reason>". Notices are fixed text, never written by the assistant.
- **FR-010**: When the message to reply to no longer exists, the bridge MUST post in the same channel without a reply reference.
- **FR-011**: Videos requested in the web app MUST NOT be posted to Discord.

**Fitting Discord's upload limit**

- **FR-012**: Before posting, the bridge MUST determine the upload limit for the destination: the DM limit for a DM, and the server's limit, based on its boost level, for a server channel.
- **FR-013**: A video within the limit MUST be posted unchanged.
- **FR-014**: A video over the limit MUST be re-encoded at a bitrate fitted to the limit and the video's length, keeping its resolution; if the result is still over the limit, it MUST be re-encoded at 480p.
- **FR-015**: When no re-encoded copy fits, the bridge MUST post a plain system notice in reply to the request, in the form "Video too large for Discord — watch it in the web app".
- **FR-016**: Shrinking MUST affect only the copy posted to Discord; the app MUST keep and show the original video.

**Retrying delivery**

- **FR-017**: When the bridge cannot be reached, or fails to download the video from the app, the system MUST retry delivery 5 times, waiting 10 seconds, 30 seconds, 1 minute, 2 minutes and 5 minutes between attempts.
- **FR-018**: After the last retry fails, the system MUST log the failure and stop; the video MUST remain available in the web app.
- **FR-019**: When the bridge reports that the post itself was refused by Discord (for example, missing access to the channel), the system MUST log the failure without retrying.

### Key Entities

- **Discord delivery**: A request from the app to the Discord bridge to post one video's outcome. Carries the assistant, the Discord channel, the Discord message to reply to, and either the address of the finished video or the failure reason. Not stored; it exists only while being sent and retried.
- **Video** (existing): A generated video attached to an assistant message. Its conversation's Discord channel and the triggering user message's Discord message ID determine where and in reply to what it is posted.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can go from sending `/create-video` in Discord to watching the finished video in Discord without opening the web app.
- **SC-002**: The bot's in-character reply to a Discord video request appears as quickly as its reply to a Discord image request, regardless of how long the video takes.
- **SC-003**: Every video requested from Discord ends in exactly one visible outcome in Discord, a playable video or a notice with a reason, as long as the bridge is reachable within the retry window.
- **SC-004**: A finished video is posted in Discord within 30 seconds of the app storing it, when the bridge is reachable.
- **SC-005**: Every video posted in Discord plays inline, regardless of its original size, unless it cannot fit even at 480p.
- **SC-006**: A short bridge outage (under 9 minutes) while a video finishes does not lose the video in Discord.

## Assumptions

- Discord turns run as a single in-character reply without the agent loop, so assistants do not start videos on their own in Discord. Only `/create-video` is offered there.
- Telegram is out of scope.
- The Discord bridge (node-discord-api) runs on the same machine as the app and downloads the finished video from the app's own address, as it already does for generated images. The public address is needed only for a first-frame image, as in the web app.
- ffmpeg is installed on the machine running the bridge. The setup for voice transcription already requires it.
- Discord's upload limit is 10 MB for DMs and unboosted servers, with higher limits on servers at higher boost levels. The bridge reads the server's boost level to decide.
- The bot does not post a separate "video is being generated" message; the in-character reply serves that purpose.
- Discord's conversation history already includes each video message's description and status, so the assistant can talk about earlier videos in later Discord turns.
- Retries cover only delivery to the bridge. Generation itself keeps its existing timeout and failure handling.
