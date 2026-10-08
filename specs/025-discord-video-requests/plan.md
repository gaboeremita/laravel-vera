# Implementation Plan: Discord Video Requests

**Branch**: `146-discord-video-requests` | **Date**: 2026-10-07 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/025-discord-video-requests/spec.md`

## Summary

Discord messages get `/create-video`, on top of the video generation already in the web chat.

- **Starting a video:** `sendDiscordMessage` gets a `/create-video` branch that mirrors the web branch in `chat()` and reuses `startVideoMessage()`. The attached image becomes the first frame. The response is the in-character reply only. `reactToStartedVideo()` drops emotion and pose tags for Discord, as `reactToGeneratedImage()` does.
- **Delivering the outcome:** `VideoGenerationFinished` gains `videoId`. A queued listener, `DeliverVideoToDiscord`, sends each Discord video's outcome (video address or failure reason, plus the reply target) to the Discord API service. It retries 5 times when the service is unavailable and logs and stops when the post is refused.

## Technical Context

**Language/Version**: PHP 8.4 (Laravel 13)

**Primary Dependencies**: Existing only: the HTTP client, queued listeners (`database` queue driver), `VideoGenerationService`. No new packages.

**Storage**: No schema changes.

**Testing**: Pest 4 feature tests with factories, `Http::fake`, `Queue::fake` and `Log::spy`.

**Target Platform**: Laravel served locally (Herd / `composer run dev`), with the Discord API service on the same machine.

**Project Type**: Web application (Laravel API)

**Performance Goals**: The Discord answer to `/create-video` arrives as fast as one to `/create-image` minus the image generation itself (SC-001). Delivery is sent within 30 s of the video's final status (SC-003).

**Constraints**:
- The listener's `$timeout` (200 s) stays under the worker's `--timeout=210`. Its `$tries = 6` overrides `--tries=1`.
- The delivery request waits up to `DISCORD_API_DELIVERY_TIMEOUT` (default 180 s).

**Scale/Scope**: Single user, a few Discord bots, a handful of concurrent videos.

## Constitution Check

| Principle | Status |
|---|---|
| I. Lint-enforced style | Pint and ESLint run once at push/PR time, per CLAUDE.md |
| II. Append-only migrations | No migrations |
| III. Comments justify only non-obvious decisions | One comment, on why the reply target is the latest earlier user message (Discord requests arrive one at a time) |
| IV. Data isolation by ownership | The listener reads the assistant and channel from the video's own conversation. `sendDiscordMessage` already resolves the `AssistantUser` from the authenticated token. |
| V. Errors fail loudly | Every delivery that ends without a 2xx is logged with the video and conversation IDs. No empty catches. |
| VI. Feature-test-first, factory-backed | Feature tests through the Discord messages endpoint and through the listener, using the existing `Video`, `Message`, `Conversation` and `VideoGenModel` factories |
| VII. No speculative abstraction | One listener and one controller branch. No shared delivery layer for Telegram. |
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
│   ├── discord-messages.md
│   └── discord-video-delivery.md
└── tasks.md
```

### Source Code

```text
app/
├── Events/VideoGenerationFinished.php                # carries videoId for the listener
├── Http/Controllers/Api/ConversationController.php   # /create-video branch in sendDiscordMessage; Discord exclusions in reactToStartedVideo
└── Listeners/DeliverVideoToDiscord.php               # new queued listener on VideoGenerationFinished
config/ai.php                                         # discord.delivery_timeout
.env.example                                          # DISCORD_API_DELIVERY_TIMEOUT
tests/Feature/
├── DiscordCreateVideoTest.php                        # new
└── DeliverVideoToDiscordTest.php                     # new
README.md                                             # /create-video from Discord, DISCORD_API_DELIVERY_TIMEOUT
ARCHITECTURE.md                                       # delivery flow
```

**Structure Decision**: The controller branch sits next to `/create-image` in `sendDiscordMessage`, and the listener next to `UnlockQuests`.

## Complexity Tracking

No violations.
