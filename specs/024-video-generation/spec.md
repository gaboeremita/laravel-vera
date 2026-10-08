# Feature Specification: Video Generation

**Feature Branch**: `145-video-generation`

**Created**: 2026-10-07

**Status**: Draft

**Input**: User description: "Add video generation alongside image generation. The user can ask for a video with a /create-video command, and agent-mode assistants get a generate_video tool, both mirroring /create-image and generate_image. An image attached to the request is used as the video's first frame. Videos are generated through OpenRouter's asynchronous video endpoint, so generation runs in the background: the chat shows a placeholder with the current status that turns into a playable video when it is ready, and an app-wide notice tells the user the video is ready (or failed) wherever they are in the app. Each assistant gets a Video Gen providers page, mirroring the Image Gen providers page, to configure providers and models and select the one it uses. Input images reach the provider through a public address set separately from the app's own address (a tunnel such as herd share). Videos are stored as their own records, separate from images."

## Clarifications

### Session 2026-10-07

- Q: When you ask for a video, which images can be used as its first frame? → A: Only an image attached to the same message as the request.
- Q: If you ask for a specific length or shape, should that request change the video's settings? → A: Yes. The assistant sets length, aspect ratio and sound from what you asked for, the model's defaults cover anything you didn't mention, and a value the model can't produce is replaced by the closest one it supports.
- Q: In later turns, how should the assistant know what's in a video it made earlier? → A: Each video message carries the description it was generated from and its current status (generating, ready or failed).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ask for a video and watch it arrive in the chat (Priority: P1)

The user types `/create-video` followed by a description in a conversation with an assistant that has a video model configured. The assistant answers in character right away, acknowledging the video it is making. Below that reply, a placeholder shows that the video is being generated and updates its status as the work progresses. When the video is ready, the placeholder turns into a video player in the same message, and the user can play it, pause it, and watch it full screen. If the user reloads the page or opens the conversation later, the finished video is still there in its message.

**Why this priority**: This is the core of the feature: a working path from a description to a playable video kept in the conversation. Every other story builds on it.

**Independent Test**: Configure a video model for an assistant, send `/create-video a cat walking on a piano`, and confirm the in-character reply appears with a placeholder, the placeholder becomes a playable video when generation finishes, and the video is still in the message after a reload.

**Acceptance Scenarios**:

1. **Given** an assistant with a video model selected, **When** the user sends `/create-video` followed by a description, **Then** the assistant replies in character and the message shows a placeholder stating the video is being generated.
2. **Given** a video being generated, **When** generation finishes, **Then** the placeholder is replaced by a playable video without the user reloading the page.
3. **Given** a finished video, **When** the user reloads the page or reopens the conversation, **Then** the video plays from its message.
4. **Given** an assistant with no video model selected, **When** the user sends `/create-video` with a description, **Then** the app tells the user that no video model is configured for this assistant and nothing is generated.
5. **Given** the user sends `/create-video` with no description, **When** the message is submitted, **Then** the app asks the user to describe the video to generate.

---

### User Story 2 - Start a video from an attached image (Priority: P1)

The user attaches an image to their message along with `/create-video` and a description. The video opens on that image as its first frame and moves according to the description.

**Why this priority**: Animating a chosen picture is the main reason the user wants video generation, and it is the part that needs the public image address to work at all.

**Independent Test**: With the public address set, attach an image to `/create-video the camera slowly pulls back`, and confirm the finished video starts on the attached image.

**Acceptance Scenarios**:

1. **Given** the public address is set, **When** the user sends `/create-video` with a description and an attached image, **Then** the video's first frame is the attached image.
2. **Given** the public address is not set, **When** the user sends `/create-video` with an attached image, **Then** the app tells the user that videos from an image need the public address, and nothing is generated.
3. **Given** the public address is set but the provider cannot reach the image (for example, the tunnel is down), **When** the provider rejects the request, **Then** the message shows the failure with the provider's reason.

---

### User Story 3 - Be told when a video is ready anywhere in the app (Priority: P2)

The user asks for a video, then moves to another conversation or another page while it generates. When the video finishes, a notice appears wherever they are, naming the assistant and saying the video is ready, with a way to go to the conversation that holds it. If generation fails, the notice says so instead.

**Why this priority**: A video takes from under a minute to several minutes. Without a notice, the user has to keep checking the conversation. The video itself is still delivered without this story, so it ranks below the core flow.

**Independent Test**: Start a video, switch to a different conversation, and confirm a notice appears when the video finishes and that following it opens the conversation with the video.

**Acceptance Scenarios**:

1. **Given** a video is being generated, **When** it finishes while the user is on any page of the app, **Then** a notice says the assistant's video is ready and lets the user open that conversation.
2. **Given** a video is being generated, **When** generation fails, **Then** the notice says the video failed, and the message shows the failure reason.
3. **Given** the user has no tab of the app open when the video finishes, **When** they next open the conversation, **Then** the video (or the failure) is shown in its message.

---

### User Story 4 - Assistants make videos on their own (Priority: P2)

In a conversation with an agent-mode assistant that has a video model selected, the user asks in plain words for a video ("can you make me a short clip of us at the beach?"). The assistant decides to make one, writes the description itself, and the video arrives in the chat the same way as with `/create-video`. If the user attached an image to that message, the assistant's video starts on it.

**Why this priority**: It mirrors what agent-mode assistants already do for images, and it reuses everything from Stories 1 to 3.

**Independent Test**: In an agent-mode conversation with a video model selected, ask for a short clip in plain words and confirm a placeholder appears and becomes a video.

**Acceptance Scenarios**:

1. **Given** an agent-mode assistant with a video model selected, **When** the user asks for a video in plain words, **Then** the assistant starts a video and the chat shows its placeholder.
2. **Given** an agent-mode assistant with no video model selected, **When** the user asks for a video, **Then** the assistant is not offered the ability to make one.
3. **Given** the assistant has started a video, **When** it finishes its reply, **Then** the reply arrives without waiting for the video.
4. **Given** an agent-mode assistant with a video model selected, **When** the user chats without asking for a video, **Then** the assistant replies without starting one.

---

### User Story 5 - Configure video providers and models per assistant (Priority: P1)

From an assistant's menu, the user opens a Video Gen page laid out like the Image Gen page. There they add a video provider (name, address, API key), add models under it (model name and default settings such as length, resolution, aspect ratio, and whether to generate sound), edit or remove them, and select the model this assistant uses for videos.

**Why this priority**: Nothing can be generated until a provider and model are configured, so this is required for Story 1 to work.

**Independent Test**: Add an OpenRouter provider and a Seedance model on the Video Gen page, select it for the assistant, and confirm `/create-video` uses it.

**Acceptance Scenarios**:

1. **Given** the user is on an assistant's Video Gen page, **When** they add a provider with a name, address and API key, **Then** the provider is listed and can hold models.
2. **Given** a provider, **When** the user adds a model with its default length, resolution, aspect ratio and sound setting, **Then** the model is listed under the provider and can be selected for the assistant.
3. **Given** a selected model, **When** the user generates a video, **Then** the request uses that model and its default settings.
4. **Given** a provider or model, **When** the user edits or deletes it, **Then** the change is saved, and deleting the selected model leaves the assistant with no video model selected.
5. **Given** two users, **When** either opens the Video Gen page, **Then** each sees only their own providers and models.

---

### Edge Cases

- The provider reports that the job expired or was cancelled: the message shows the video failed with that reason, and the notice reports the failure.
- The provider never reports a final status: after a maximum wait, the video is marked as failed with a timeout reason.
- The provider finishes but the video file cannot be downloaded: the video is marked as failed with the download error, and the error is logged.
- The user asks for a second video while the first is still generating: both proceed independently, each in its own message with its own placeholder and notice.
- The conversation is deleted while its video is still generating: the background work stops without error, and no notice is shown for it.
- The user is in a world conversation: `/create-video` and the assistant's video ability work the same as in a regular conversation.
- The user asks for a setting the selected model cannot produce (for example, a 40 second clip from a model that tops out at 30): the video is generated with the closest value the model supports.
- An attached image is too large or in a format the provider rejects: the provider's rejection is shown as the failure reason.
- The provider's list of supported values cannot be read: the request is sent as asked, and a rejection by the provider is shown as the failure reason.
- The assistant starts a video on its own while the user's message has an attached image and no public address is configured: no video is started, and the assistant is told why so it can tell the user.
- Writing the video description or the in-character reply fails: the app shows the error and no video is started.

## Requirements *(mandatory)*

### Functional Requirements

**Requesting a video**

- **FR-001**: Users MUST be able to request a video by sending `/create-video` followed by a description in a conversation.
- **FR-002**: The system MUST reject `/create-video` with no description, asking the user to describe the video.
- **FR-003**: The system MUST reject a video request when the assistant has no video model available, telling the user no video model is configured.
- **FR-004**: The system MUST improve the user's description into a fuller video description before sending it to the provider, the same way it does for image requests.
- **FR-005**: When the user's message carries an attached image, the system MUST send that image to the provider as the video's first frame. Only an image attached to the same message as the request is used; earlier images in the conversation are never used as a first frame.
- **FR-005a**: When the request asks for a length, aspect ratio, or sound on or off, the video MUST use what was asked; anything not asked for MUST use the selected model's defaults. With `/create-video` and with the assistant's own videos alike, the assistant reads these from the request.
- **FR-005b**: A requested value the selected model cannot produce MUST be replaced by the closest value the model supports.
- **FR-006**: The system MUST make an attached image reachable by the provider through the configured public address, and MUST reject a request with an attached image when no public address is configured.
- **FR-007**: After a `/create-video` request is accepted, the assistant MUST reply in character, aware of what the video will show, without waiting for the video to finish.
- **FR-008**: Agent-mode assistants with a video model available MUST be offered the ability to start a video with a description they write; assistants without one MUST NOT be offered it. The assistant decides from the conversation whether the user is asking for a video, guided by an instruction to start one when the user asks for a video, clip or animation.
- **FR-009**: When an agent-mode assistant starts a video, its reply MUST finish without waiting for the video.

**Generation and delivery**

- **FR-010**: The system MUST generate each video in the background and track its status as queued, generating, completed or failed.
- **FR-011**: The system MUST keep its own copy of every finished video, since the provider keeps results only briefly.
- **FR-012**: The system MUST attach each video to the assistant message it belongs to, so it is shown in that message on every later view of the conversation.
- **FR-012a**: In every later turn of the conversation, the assistant MUST see each video message's description and its current status (generating, ready or failed), so it can talk about the video.
- **FR-013**: The system MUST record a failure reason for every failed video: the provider's error, an expiry or cancellation, a download failure, or a timeout after the maximum wait.
- **FR-014**: The system MUST log every failure.
- **FR-015**: The system MUST stop tracking a video without error if its conversation is deleted before it finishes.

**Showing videos in the chat**

- **FR-016**: While a video is queued or generating, its message MUST show a placeholder stating the current status.
- **FR-017**: When the video finishes, the placeholder MUST turn into a video player with play, pause, seek and full-screen controls, without a page reload, for anyone viewing the conversation.
- **FR-018**: When the video fails, the placeholder MUST turn into a failure message showing the reason, without a page reload.

**App-wide notice**

- **FR-019**: When a video finishes or fails, the system MUST show the owning user a notice on whatever page of the app they have open, naming the assistant and saying whether the video is ready or failed.
- **FR-020**: The notice MUST let the user open the conversation that holds the video.

**Video providers and models**

- **FR-021**: Each assistant MUST have a Video Gen page, reachable from the assistant's menu next to Image Gen, for managing video providers and models.
- **FR-022**: Users MUST be able to create, edit and delete video providers (name, address, API key) and models under them (model name, default length, resolution, aspect ratio, sound on or off, and a maximum wait).
- **FR-023**: Users MUST be able to select which video model an assistant uses, and clear that selection.
- **FR-024**: Video providers and models MUST be visible and usable only by the user who created them.
- **FR-025**: API keys MUST be stored encrypted and MUST NOT be returned to the browser; the page shows only whether a key is set.

### Key Entities

- **Video Gen Provider**: A service the user's videos are generated with. Belongs to one user. Has a name, an address, an API key and a format that says how to talk to it.
- **Video Gen Model**: A model offered by a provider, with default settings for length, resolution, aspect ratio, sound and maximum wait, and the lengths and aspect ratios it supports, as the provider lists them. An assistant can have one selected.
- **Video**: A generated video. Belongs to one assistant message. Records the provider's job reference, the status (queued, generating, completed, failed), the failure reason when it failed, the description it was generated from, the requested length, aspect ratio and sound, and, once finished, the stored file and its size.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can go from typing `/create-video` with a description to watching the finished video without leaving or reloading the conversation.
- **SC-002**: The assistant's in-character reply to a video request appears as quickly as its reply to an image request, regardless of how long the video takes.
- **SC-003**: Every requested video ends in exactly one of two visible outcomes in its message, a playable video or a failure with a reason; none stays in "generating" past the model's maximum wait.
- **SC-004**: When a video finishes while the user is anywhere else in the app, a notice appears within 30 seconds of the provider finishing it, and one click opens the conversation with the video.
- **SC-005**: A video started from an attached image opens on that image as its first frame.
- **SC-006**: A user can set up a provider and model and generate their first video from the Video Gen page alone, with no manual configuration outside the app beyond setting the public address.

## Assumptions

- OpenRouter is the only video provider format in this feature. Its video service accepts jobs, reports their status, and serves the finished file for a limited time.
- The public address is a tunnel to the user's local site (for example, `herd share`). It is set in the environment, separate from the app's own address, and is used only to build image links for the provider. The app keeps loading its own pages and files from its usual address.
- The tunnel only needs to be running while the provider fetches the input image at the start of a request. The app checks the job's status itself and does not depend on the provider calling back.
- A message carries at most one attached image, as it does today, so a video can use one image as its first frame. Last-frame control and multiple reference images are out of scope.
- Extending an existing video is out of scope. The provider does not offer it.
- Videos are stored as their own records, separate from images, because they carry status, job and length details that images do not have.
- The assistant's in-character reply to `/create-video` is generated when the request starts. The assistant does not react again when the video arrives.
- `/create-video` and the assistant's video ability are available in the web chat. Discord and Telegram conversations do not offer them.
- The notice is shown only in open tabs of the app. System notifications, sounds, and messages over Telegram or Discord are out of scope.
- Video generation costs come from the user's own provider account. The app shows no prices.
