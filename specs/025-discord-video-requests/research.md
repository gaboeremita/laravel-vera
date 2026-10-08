# Research: Discord Video Requests

## R1: How the app tells the Discord API service a video is ready

**Decision**: A queued listener, `DeliverVideoToDiscord`, listens for the existing `VideoGenerationFinished` event. It does nothing for conversations without a `discord_channel_id`. For Discord conversations it sends `POST {DISCORD_API_URL}/assistants/{assistantId}/channels/{channelId}/videos` with the `X-Internal-Secret` header from `config('ai.discord.api_secret')`. `VideoGenerationFinished` gains a `videoId` field so the listener can load the video, since the event only carries IDs.

**Rationale**: `PollVideoGeneration` already dispatches `VideoGenerationFinished` exactly once per video, for both completed and failed, so the listener covers both without touching the job. `app/Listeners/UnlockQuests.php` is the existing example of a listener on a domain event, discovered automatically. A queued listener carries its own `$tries` and `backoff()`, which gives the retry schedule (FR-011) without a separate job class. The app already calls the Discord API service with the same secret for channel discovery (`DiscordController::discovery`).

**Alternatives considered**: Calling the Discord API service from inside `PollVideoGeneration`, which would make video polling depend on that service being up and mix two retry schedules in one job.

## R2: Retry schedule and failure classes

**Decision**: The listener sets `$tries = 6` (the first attempt plus 5 retries) and `backoff()` returns `[10, 30, 60, 120, 300]`. Responses map as follows:

| Response | Listener behaviour |
|---|---|
| 2xx | Done |
| Connection error or timeout | Throw, so the queue retries |
| 404 (no bot for the assistant) | Throw, so the queue retries |
| 502 (the service couldn't download the video) | Throw, so the queue retries |
| Any other non-2xx | Throw, so the queue retries |
| 422 (Discord refused the post) | `$this->fail()`, no retry (FR-013) |
| 401 (wrong secret) | `$this->fail()`, no retry (FR-013) |

`failed()` logs the video ID, the conversation ID and the error, once per delivery that ends without a post (FR-012, FR-013). `$this->fail()` runs `failed()` too, so refused posts are logged there as well.

**Rationale**: A job-level `$tries` overrides the worker's `--tries=1` from `composer run dev`. The waits add up to about 9 minutes (SC-004).

## R3: Request timeout

**Decision**: A new config key, `ai.discord.delivery_timeout` (`DISCORD_API_DELIVERY_TIMEOUT`, default 180 seconds), separate from the 10-second discovery `timeout`. The listener's own `$timeout` is 200 seconds, under the worker's `--timeout=210`.

**Rationale**: The Discord API service answers only after it has posted, which can include re-encoding the video. Waiting for that answer lets the app tell a retryable failure from a refused post. A long wait also keeps a timed-out-but-posted delivery, which a retry would post twice, out of normal operation.

## R4: Which Discord message is the reply target

**Decision**: The listener reads the `discord_message_id` of the latest user message in the conversation whose ID is lower than the video's assistant message. A null value is sent as no reply target (FR-008).

**Rationale**: `sendDiscordMessage` creates the user message and then, in the same request, the assistant message that carries the video. The Discord API service sends the app one request at a time, so no other message for that channel lands between the two. This needs no new column.

## R5: Starting a video from a Discord message

**Decision**: `sendDiscordMessage` gets a `/create-video` branch right after the `/create-image` branch. It mirrors the web branch in `chat()`: the same empty-description, no-model and public-address rejections, then `startVideoMessage()`. It answers `{content}` only. The `Image` returned by `Image::storeFromBase64` for `images[0]` is passed as the first frame. `reactToStartedVideo()` gets the same Discord exclusions as `reactToGeneratedImage()` (emotion and pose tags).

**Rationale**: Reusing `startVideoMessage()` keeps Discord requests on exactly the web rules (FR-002). The rejections already return `{message}` with 422 or 502, as every other `sendDiscordMessage` rejection does.
