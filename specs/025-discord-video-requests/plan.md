# Implementation Plan: Discord Video Requests

**Branch**: `146-discord-video-requests` | **Date**: 2026-10-07 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/025-discord-video-requests/spec.md`

## Summary

Discord gets `/create-video` on top of the video generation already in the web app. The work spans two repos.

- **Laravel, starting a video:** `sendDiscordMessage` gets a `/create-video` branch that mirrors the web branch in `chat()` and reuses `startVideoMessage()`. The attached image becomes the first frame. The response is the in-character reply only. `reactToStartedVideo()` drops emotion and pose tags for Discord, as `reactToGeneratedImage()` does.
- **Laravel, delivering a video:** a queued listener, `DeliverVideoToDiscord`, listens for `VideoGenerationFinished`. For Discord conversations it posts the video URL or the failure reason to a new bridge route, retries 5 times on an unreachable bridge, and logs and stops on a refused post.
- **Bridge, receiving:** the new route `POST /assistants/:assistantId/channels/:channelId/videos` downloads the video and works out the upload limit from the DM or the server's boost tier. Over the limit, it shrinks the video with ffmpeg: first at a bitrate fitted to the length, then at 480p. It posts the file, or a plain notice, as a reply to the request.
- **Bridge, forwarding:** a `/create-video` from someone not on the DM allowlist gets "You can't request videos." without reaching Laravel. Every Laravel rejection now shows its `message`; "Connection failed. Try again." stays only for unreachable or reasonless errors.

## Technical Context

**Language/Version**: PHP 8.4 (Laravel 13); Node.js with CommonJS (node-discord-api)

**Primary Dependencies**: Existing only. In Laravel: the HTTP client, queued listeners (`database` queue driver) and `VideoGenerationService`. In the bridge: discord.js 14.27, Express 5, and Node's `child_process`, `fs` and `os` calling the host's `ffmpeg` and `ffprobe`. No new packages in either repo.

**Storage**: No schema changes. Video files stay on the `public` disk; the bridge shrinks copies in the OS temp directory and deletes them.

**Testing**: Pest 4 feature tests with factories, `Http::fake`, `Queue::fake` and `Event::fake`. The bridge has no test runner and gets manual validation ([quickstart.md](quickstart.md)).

**Target Platform**: Laravel served locally (Herd / `composer run dev`), with the bridge on the same machine.

**Project Type**: Web application (Laravel API) plus a separate Node service (node-discord-api)

**Performance Goals**: The Discord reply to `/create-video` returns as fast as one to `/create-image` minus the image generation itself (SC-002). The video is posted within 30 s of being stored when no shrinking is needed (SC-004).

**Constraints**:
- The listener's `$timeout` (200 s) stays under the worker's `--timeout=210`. Its `$tries = 6` overrides `--tries=1`.
- The bridge answers only after posting, within `DISCORD_API_DELIVERY_TIMEOUT` (default 180 s).
- Upload limits are 10 / 50 / 100 MB (research R7).

**Scale/Scope**: Single user, a few bots, a handful of concurrent videos, files up to tens of MB.

## Constitution Check

| Principle | Status |
|---|---|
| I. Lint-enforced style | Pint and ESLint run once at push/PR time, per CLAUDE.md. The bridge has no linter configured, and none is added. |
| II. Append-only migrations | No migrations |
| III. Comments justify only non-obvious decisions | Comments only on why the reply target is the latest earlier user message (the bridge serializes requests), and on the 8% size margin in the bitrate formula |
| IV. Data isolation by ownership | The listener reads the assistant and channel from the video's own conversation. `sendDiscordMessage` already resolves the `AssistantUser` from the authenticated token. Video access in Discord is limited to the bot's own allowlist. |
| V. Errors fail loudly | Every delivery that ends without a post is logged with the video and conversation IDs. Bridge errors are written to `discord-api.log` through `logError`. ffmpeg failures produce the "too large" notice and a log entry. No empty catches. |
| VI. Feature-test-first, factory-backed | Feature tests through `/discord-messages` and through dispatching `VideoGenerationFinished`, using the existing `Video`, `Message`, `Conversation` and `VideoGenModel` factories |
| VII. No speculative abstraction | One listener, one bridge route, no shared "delivery" layer for Telegram. The shrink step is a local function in `index.js`. |
| VIII. Render-time derivation | No frontend changes |

Gate: pass. Re-checked after Phase 1 design: pass.

## Project Structure

### Documentation (this feature)

```text
specs/025-discord-video-requests/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── bridge-video-delivery.md
│   └── discord-messages.md
└── tasks.md             # /speckit-tasks
```

### Source Code

```text
laravel-vera/
├── app/
│   ├── Http/Controllers/Api/ConversationController.php   # /create-video branch in sendDiscordMessage; Discord exclusions in reactToStartedVideo
│   ├── Events/VideoGenerationFinished.php                # carries videoId for the listener
│   └── Listeners/DeliverVideoToDiscord.php               # new queued listener on VideoGenerationFinished
├── config/ai.php                                         # discord.delivery_timeout
├── .env.example                                          # DISCORD_API_DELIVERY_TIMEOUT
├── tests/Feature/
│   ├── DiscordCreateVideoTest.php                        # new
│   └── DeliverVideoToDiscordTest.php                     # new
├── README.md                                             # Discord video requests, ffmpeg for the bridge
└── ARCHITECTURE.md                                       # delivery flow and new bridge route

node-discord-api/
├── index.js                                              # allowlist check, rejection messages, POST /assistants/:assistantId/channels/:channelId/videos, upload limits, ffmpeg shrink
├── .env.example                                          # unchanged
└── README.md                                             # new route, ffmpeg requirement
```

**Structure Decision**: Laravel changes follow the existing layout: the controller branch next to `/create-image`, and the listener next to `UnlockQuests`. All bridge logic stays in `index.js`, the bridge's only source file. The bridge changes are committed in its own repository.

## Complexity Tracking

No violations.
