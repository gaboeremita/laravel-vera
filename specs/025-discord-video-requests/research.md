# Research: Discord Video Requests

## R1: How Laravel tells the bridge a video is ready

**Decision**: A queued listener, `DeliverVideoToDiscord`, listens for the existing `VideoGenerationFinished` event. It does nothing for conversations without a `discord_channel_id`. For Discord conversations it calls a new bridge route, `POST {DISCORD_API_URL}/assistants/{assistantId}/channels/{channelId}/videos`, with the `X-Internal-Secret` header from `config('ai.discord.api_secret')`.

**Rationale**: `PollVideoGeneration` already dispatches `VideoGenerationFinished` exactly once per video, for both the completed and the failed outcome, so the listener covers both without touching the job. `app/Listeners/UnlockQuests.php` is the existing example of a listener on a domain event. A queued listener carries its own `$tries` and `backoff()`, which gives the retry schedule (FR-017) without a separate job class. The bridge already trusts Laravel through the same secret for discovery (`DiscordController::discovery`).

**Alternatives considered**: Calling the bridge from inside `PollVideoGeneration`. That would make video polling depend on the bridge being up and mix two retry schedules in one job. The bridge checking Laravel for finished videos adds a polling loop and a delay, and the bridge would need to know which videos belong to Discord.

## R2: Retry schedule and failure classes

**Decision**: The listener sets `$tries = 6` (the first attempt plus 5 retries) and `backoff()` returns `[10, 30, 60, 120, 300]`. Bridge responses map as follows:

| Bridge result | Listener behaviour |
|---|---|
| 200 | Done |
| Connection error or timeout | Throw, so the queue retries |
| 404 (no bot for the assistant) | Throw, so the queue retries |
| 502 (bridge could not download the video from Laravel) | Throw, so the queue retries |
| 422 (Discord refused the post) | Log and `$this->fail()`, with no retry (FR-019) |
| 401 | Log and `$this->fail()`: a configuration error that retrying cannot fix |

`failed()` logs the video ID, the conversation ID and the last error (FR-018). The video stays in the web app either way.

**Rationale**: A job-level `$tries` overrides the worker's `--tries=1` from `composer run dev`. The waits add up to about 9 minutes (SC-006).

## R3: Bridge request timeout

**Decision**: A new config key, `ai.discord.delivery_timeout` (`DISCORD_API_DELIVERY_TIMEOUT`, default 180 seconds), separate from the 10-second discovery `timeout`. The listener's own `$timeout` is 200 seconds, under the worker's `--timeout=210`. The bridge downloads, shrinks and posts within that request, then answers.

**Rationale**: Shrinking runs ffmpeg up to twice. A 30-second 1080p clip re-encodes in well under a minute on the host, so 180 seconds leaves room. Answering only after the post lets the bridge report download failures (retry) and Discord refusals (no retry) separately. A Laravel timeout after the bridge has already posted would post the video twice on retry; the long timeout keeps that case out of normal operation.

## R4: Which Discord message to reply to

**Decision**: The listener reads the `discord_message_id` of the latest user message in the conversation whose ID is lower than the video's assistant message. When it is null (the request came without one), the bridge posts without a reply reference. When it is set but no longer exists in Discord, the bridge also posts without one (FR-010).

**Rationale**: `sendDiscordMessage` creates the user message and then, in the same request, the assistant message that carries the video. The bridge sends one request to Laravel at a time (`enqueue` in `index.js`), so no other message for that channel lands between the two. This needs no new column.

## R5: Who can request videos in server channels

**Decision**: The bridge checks it. When a message starts with `/create-video` (case-insensitive, same pattern as Laravel's `extractVideoGenPrompt`) and the author's ID is not in that bot's DM allowlist, the bot replies "You can't request videos." and does not forward the message to Laravel.

**Rationale**: The allowlist only exists in the bridge's environment (`dmAllowlistEnv` per bot). DMs already pass only for allowlisted IDs, so the check changes nothing there. Other bots are never on the allowlist unless added by ID.

## R6: Showing Laravel's rejection reason

**Decision**: When `/discord-messages` answers with a non-2xx status, the bridge reads `message` from the JSON body and posts it. It posts "Connection failed. Try again." only when the request itself throws or the body has no `message` (FR-005, FR-005a).

**Rationale**: Every rejection in `sendDiscordMessage` already returns `{message}` with a 422 or 502, and Laravel's validation errors use the same key.

## R7: Discord upload limits

**Decision**: The bridge reads the limit from the destination:

| Destination | Limit |
|---|---|
| DM | 10 MB |
| Server, boost tier 0 or 1 | 10 MB |
| Server, boost tier 2 | 50 MB |
| Server, boost tier 3 | 100 MB |

The tier comes from `channel.guild.premiumTier` in discord.js 14.27.

**Rationale**: These are Discord's limits since its 2025 change to the free upload size. discord.js has no helper that returns the upload limit, so the bridge keeps the table as a constant.

## R8: Shrinking with ffmpeg

**Decision**: The bridge writes the downloaded MP4 to a temporary file and reads its length with `ffprobe`. Over the limit, it re-encodes with H.264 and AAC at a video bitrate of `(limit × 8 × 0.92 ÷ seconds) − 128 kb/s`, using `-maxrate` and `-bufsize` so the file stays near the target, and `-movflags +faststart` so it plays inline before fully loading. If the result is still over the limit, it runs the same encode with `-vf scale=-2:480`. If that is still over, it posts the "too large" notice. Temporary files are removed in a `finally`.

**Rationale**: A bitrate fitted to the length gives the best quality that fits in a single pass. The 8% margin covers container overhead. ffmpeg and ffprobe are already installed on the host for voice transcription (README). The bridge calls them with `child_process.execFile`, so no new npm package is added.

## R9: Discord video replies from Laravel

**Decision**: `sendDiscordMessage` gets a `/create-video` branch right after the `/create-image` branch, mirroring the web branch in `chat()`: the same empty-description, no-model and public-address rejections, then `startVideoMessage()`. It returns `{content}` only. The attached image from `images[0]` is kept as the `Image` returned by `Image::storeFromBase64` and passed as the first frame. `reactToStartedVideo()` gets the same Discord exclusions as `reactToGeneratedImage()` (emotion and pose tags), since Discord has nothing to render them with.

**Rationale**: Reusing `startVideoMessage()` keeps Discord requests on exactly the web rules (FR-002). The in-character reply is posted by the bridge like any other text reply.

## R10: Testing the bridge

**Decision**: The bridge has no test runner (`npm test` is a placeholder), and adding one would change its dependencies. Bridge behaviour is validated by the manual scenarios in `quickstart.md`. Laravel behaviour (the Discord command branch, the listener's payload, retries and failure classes) is covered by Pest feature tests with `Http::fake`, `Queue::fake` and factories.
