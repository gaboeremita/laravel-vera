# Research: Video Generation

## R1. OpenRouter video API

**Decision**: The OpenRouter provider class talks to four endpoints, all derived from the provider's `url` (default `https://openrouter.ai/api/v1/videos`):

| Purpose | Request | Notes |
|---|---|---|
| Submit | `POST {url}` with `model`, `prompt`, `duration`, `aspect_ratio`, `resolution`, `generate_audio`, `frame_images` | `202` with `{ id, polling_url, status: "pending" }` |
| Status | `GET {url}/{id}` | `status` is `pending`, `in_progress`, `completed`, `failed`, `cancelled` or `expired`; `completed` carries `unsigned_urls`, `failed` carries `error` |
| Download | `GET {unsigned_urls[0]}` | Needs the `Authorization: Bearer` header; the link is not presigned |
| Supported settings | `GET {url}/models` | Each entry has `id`, `supported_durations`, `supported_resolutions`, `supported_aspect_ratios` |

A first frame is sent as `frame_images: [{ "type": "image_url", "image_url": { "url": "https://…" }, "frame_type": "first_frame" }]`. Only public HTTPS URLs are documented, so the image goes through the tunnel address (R4).

**Rationale**: These are the only documented endpoints. Deriving them from one stored `url` keeps the provider record shaped like `image_gen_providers` (one `url` per provider).

**Alternatives considered**: Storing separate status and download URLs on the provider. That adds fields nobody would set differently, since OpenRouter returns the status and download links itself.

## R2. Waiting for the result: background job that re-queues itself

**Decision**: `PollVideoGeneration` is a queued job that handles one video. Each run either submits the job (first run) or checks its status once. While the status is `pending` or `in_progress`, the job calls `$this->release(self::POLL_SECONDS)` (30 s, OpenRouter's suggested interval). `retryUntil()` returns the video's `created_at` plus the model's `timeout` (default 600 s). When that deadline passes, Laravel fails the job, and `failed()` marks the video failed with a timeout reason. The job stops without broadcasting when the video's message is gone because its conversation was deleted (FR-015, see R9), and `$deleteWhenMissingModels = true` drops it when the video row itself is gone.

**Rationale**:
- Releasing back to the queue frees the worker between checks; a `sleep` loop would hold it for minutes.
- `retryUntil` turns the maximum wait into a framework-enforced deadline.
- The queue already runs on the `database` driver for `GenerateAvatarBackground`.

**Alternatives considered**:
- OpenRouter's `callback_url` webhook. It needs a public signed route and only arrives if the tunnel is up when the video finishes.
- A scheduled command polling all open videos. That's a second mechanism next to the queue, with its own locking.

## R3. Submitting from the job

**Decision**: Submission to the provider also happens in `PollVideoGeneration`'s first run, not in the HTTP request. The request only improves the description, writes the in-character reply, and creates the message with a `queued` video.

**Rationale**: every provider failure, including a rejected image or a tunnel that is down, then ends on the message as a failed video with the provider's reason (US2 scenario 3, FR-013). The chat request also stays as fast as an image request's reply (SC-002), with no wait on the provider.

**Alternatives considered**: Submitting in the request and returning a 502 on rejection, as `/create-image` does. That would show some failures as an error toast and others on the message.

## R4. Public address for input images

**Decision**: New config key `ai.video_gen.public_url` (env `PUBLIC_TUNNEL_URL`). The link for an attached image is `rtrim(public_url, '/').'/storage/'.$image->path`, built only when the video is submitted. `APP_URL` and every other URL in the app are unchanged. A request with an attached image and no `public_url` is rejected with a 422 before anything is created (FR-006).

**Rationale**: the `public` disk is served at `/storage`. A tunnel such as `herd share` exposes the whole site, so the same path works through the tunnel host. The app keeps loading its own pages and files locally.

**Alternatives considered**: Pointing `APP_URL` at the tunnel. That routes all assets through it and breaks them whenever the tunnel is off.

## R5. Closest supported value

**Decision**: `VideoGenerationService` reads the model's supported durations and aspect ratios from the provider (`VideoGenProvider::supportedSettings()`), cached for a day per provider URL and model. A requested duration is replaced by the nearest supported number. A requested aspect ratio is replaced by the supported ratio whose width/height quotient is nearest. Sound is passed through unchanged, because OpenRouter ignores it for models without audio. If the listing can't be read, the request is sent unchanged and a warning is logged; the provider's rejection then becomes the failure reason (spec edge case).

**Rationale**: the provider already publishes these lists, so the user never types them, and the model record stays shaped like `image_gen_models`.

**Alternatives considered**: Storing supported values on `video_gen_models`. They would go stale when providers change their models.

## R6. Reading length, shape and sound from the request

**Decision**:
- **Assistant's own videos:** `generate_video` takes optional `duration`, `aspect_ratio` and `generate_audio` arguments next to `prompt`. The model fills them from what the user asked.
- **`/create-video`:** `VideoGenPromptEnhancer` asks the model for a JSON object `{ "description", "duration", "aspect_ratio", "generate_audio" }`, with `null` for anything the user didn't ask for. If the reply isn't valid JSON, the whole reply is used as the description with the model's defaults, and a warning is logged.

**Rationale**: the model decides what the user asked for, in line with the app's approach of leaving judgments to it, and both paths end with the same four values.

**Alternatives considered**: Parsing numbers and words like "vertical" out of the command in PHP, which turns the model's judgment into a word list.

## R7. What the assistant sees in later turns

**Decision**: `BuildConversationHistory` includes messages that have a video even when their text is empty. `forChat()` appends one line to such a message: `[Video: "<description>" — <generating|ready|failed: reason>]`.

**Rationale**: FR-012a requires both the description and the status. A message's text changes once when its video finishes. That changes the cached history from that message on for one turn, the same cost as any edited message.

**Alternatives considered**: Putting the video status in the per-turn section. It would grow with every video in the conversation and repeat descriptions the history already holds.

## R8. Live updates and the app-wide notice

**Decision**:
- **Conversation updates:** `VideoGenerationStatusUpdated` (`ShouldBroadcastNow`, `video-generation.updated`) on `private conversation.{id}`, sent on every status change. Its payload is the video in the same shape `show()` returns.
- **App-wide notice:** `VideoGenerationFinished` (`ShouldBroadcastNow`, `video-generation.finished`) on a new `private user.{id}` channel, sent once when the video completes or fails. Its payload is `conversationId`, `assistantId`, `assistantName`, `status` and `failureReason`.
- **Frontend:** `useConversationVideos` listens on the conversation channel and updates the matching message by video id. `useVideoGenerationNotices` is mounted in `AuthenticatedLayout`, which already owns the toasts, and shows a toast with an OPEN action that navigates to the conversation.

**Gotcha**: `useAvatarBackground` calls `echo.leave('conversation.{id}')` on cleanup, which unsubscribes every listener on that channel. The new hook calls only `stopListening` for its own event, and does not call `leave`, so the two hooks don't cut each other off.

**Rationale**: this copies `AvatarBackgroundStatusUpdated`/`useAvatarBackground`, the existing pattern for background work reported to the chat.

## R9. Video record shape

**Decision**: Generated videos are rows in the existing polymorphic `videos` table, which already holds emotion videos, with the message as their `videoable`. A new migration adds nullable generation columns and makes `path` nullable. The existing `Video` model is extended rather than replaced.

**Rationale**: this is how generated images work: they share `images` with every other image through `imageable`. Emotion rows keep working with the new columns null.

**Alternatives considered**: A separate table for generated videos. It would split one kind of media file across two tables and two models, unlike images.

**Gotcha**: a morph relation has no database cascade. Deleting a conversation cascades to its messages, but a message's videos are left behind. `PollVideoGeneration` therefore treats a video whose message is gone like a missing model and stops (FR-015).

## R10. Where the feature is offered

**Decision**: Web chat only. `/create-video` is handled in `sendMessage`, and the `generate_video` tool is added where `generate_image` is, for agent-mode assistants with a video model available. Discord and Telegram paths don't recognize the command, and they never build the agent tool list.

**Rationale**: those channels can't show a placeholder or the notice.
