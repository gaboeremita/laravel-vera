# Implementation Plan: Video Generation

**Branch**: `145-video-generation` | **Date**: 2026-10-07 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/024-video-generation/spec.md`

## Summary

Video generation is image generation's twin, with one structural difference: the result arrives in the background.

- **Settings:** `VideoGen*` providers, models, controllers, routes and settings page copy their `ImageGen*` counterparts file for file.
- **Starting a video:** `/create-video` and the `generate_video` agent tool call `VideoGenerationService::start()`, which:
  1. improves the description;
  2. resolves length, aspect ratio and sound, adjusting each to the closest value the model supports;
  3. creates a `queued` `Video` on an assistant message;
  4. dispatches `PollVideoGeneration`.
- **The job:** submits to OpenRouter, re-queues itself every 30 s until the provider finishes or the model's maximum wait passes, then downloads the MP4 to the `public` disk.
- **Broadcasts:**
  - Every status change goes out on the conversation channel. The message's placeholder becomes a player or a failure.
  - Final states also go out on a new per-user channel, which shows a toast with an OPEN action anywhere in the app.
- **Attached images:** linked through `PUBLIC_TUNNEL_URL`.
- **Later turns:** each video's description and status are shown to the assistant in its message.

## Technical Context

**Language/Version**: PHP 8.4 (Laravel 13), JavaScript/JSX (React 19)

**Primary Dependencies**: Existing only: Laravel HTTP client and queue (`database` driver), Reverb + Laravel Echo, `PromptDirector`, `LlmManager`, `AgentLoopRunner`, `SchemaForm`/`SchemaEditor`, `Accordion`, `useToast`, Tailwind v4, lucide-react. No new packages.

**Storage**: PostgreSQL, with two new tables (`video_gen_providers`, `video_gen_models`) and nullable generation columns on the existing polymorphic `videos` table. Files go on the `public` disk under `messages/{userId}/{conversationId}/`, like images.

**Testing**: Pest 4 feature tests with factories for the three new models, `Http::fake` sequences for the provider, `Queue::fake`/`Bus::fake` for dispatch, `Event::fake` for broadcasts, and `Storage::fake('public')`.

**Target Platform**: Web chat served locally (Herd / `composer run dev`), with a tunnel for input images.

**Project Type**: Web application (Laravel API + React SPA)

**Performance Goals**: The `/create-video` reply returns as fast as a `/create-image` reply minus the image generation itself (SC-002). The notice arrives within 30 s of the provider finishing (SC-004): the check interval plus broadcast latency.

**Constraints**:
- The job sets `retryUntil()`, which overrides the worker's `--tries=1` (`composer run dev`) for released jobs.
- Each job run stays well under the worker's `--timeout=210`: one HTTP call per run, plus the download on the last.
- No push from OpenRouter; the app checks the status itself.

**Scale/Scope**: Single user, a handful of concurrent videos, files up to tens of MB.

## Constitution Check

| Principle | Status |
|---|---|
| I. Lint-enforced style | Pint and ESLint run once at push/PR time, per CLAUDE.md |
| II. Append-only migrations | Two new create-table migrations and one that adds columns to `videos`. No existing migration is edited. |
| III. Comments justify only non-obvious decisions | Comments only on why the conversation-channel hook doesn't call `echo.leave`, and why the job re-queues itself instead of sleeping |
| IV. Data isolation by ownership | Providers and models are scoped through `$request->user()->videoGenProviders()`. Model resolution checks the provider's `user_id`, as `ImageGenManager::resolveImageGenModel` does. The `user.{id}` channel authorizes only its owner. Videos are reached through the user's own conversation. |
| V. Errors fail loudly | Every failure ends in `failed` with a reason, is logged, and broadcasts. JSON fallback and unreadable supported-settings lists log warnings. No empty catches. |
| VI. Feature-test-first, factory-backed | New factories `VideoGenProviderFactory`, `VideoGenModelFactory`, `VideoFactory` (with `completed()`/`failed()` states). Feature tests through the HTTP endpoints, the tool and the job. |
| VII. No speculative abstraction | Only the OpenRouter format exists. The enum and `providerClass()` copy image generation's shape, which the manager needs. Generated videos reuse the existing polymorphic `videos` table, as generated images reuse `images`. |
| VIII. Render-time derivation | `useConversationVideos` and `useVideoGenerationNotices` define their listeners inside the effect, as `useAvatarBackground` does. Message updates are applied in the event callback, not by syncing state in an effect. |

Gate: pass. Re-checked after Phase 1 design: pass.

## Project Structure

### Documentation (this feature)

```text
specs/024-video-generation/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/api.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

Each new file names the existing file it is modelled on.

```text
app/
├── Contracts/VideoGenProvider.php                    # ← Contracts/ImageGenProvider: submit(), status(), download(), supportedSettings(), fromModel()
├── DTOs/VideoGenJobStatus.php                        # ← DTOs/ImageGenResult: status, contentUrl, error
├── DTOs/VideoGenSupportedSettings.php                # durations, aspectRatios
├── Enums/VideoGenProviderFormat.php                  # ← Enums/ImageGenProviderFormat
├── Enums/VideoStatus.php                             # queued, generating, completed, failed
├── Models/VideoGenProvider.php                       # ← Models/ImageGenProvider
├── Models/VideoGenModel.php                          # ← Models/ImageGenModel
├── Models/Video.php                                  # existing; + generation fields, model(), firstFrame(), storeDownloaded(), historyNote()
├── Models/Message.php                                # + video() MorphOne, like image()
├── Models/User.php                                   # + videoGenProviders()
├── Services/VideoGenProviders/
│   ├── VideoGenManager.php                           # ← ImageGenManager (resolveVideoGenModel, fromModel, fromConfig)
│   ├── OpenRouterVideoGenProvider.php                # ← OpenRouterImageGenProvider (research R1)
│   ├── VideoGenPromptEnhancer.php                    # ← ImageGenPromptEnhancer, JSON reply (research R6)
│   └── VideoGenerationService.php                    # ← ImageGenerationService: start(), isAvailableFor(), closest-value adjustment (R5), first-frame link (R4)
├── Services/AgentLoop/Tools/VideoGenerationTool.php  # ← ImageGenerationTool
├── Jobs/PollVideoGeneration.php                      # ← Jobs/GenerateAvatarBackground (research R2/R3)
├── Events/VideoGenerationStatusUpdated.php           # ← Events/AvatarBackgroundStatusUpdated
├── Events/VideoGenerationFinished.php                # user channel
├── Http/Controllers/Api/VideoGenProviderController.php  # ← ImageGenProviderController
├── Http/Controllers/Api/VideoGenModelController.php     # ← ImageGenModelController
├── Http/Controllers/Api/SettingsController.php       # + selectVideoGenModel, video_gen_model_id in show
├── Http/Controllers/Api/ConversationController.php   # /create-video (← /create-image block + reactToGeneratedImage), tool registration, video on messages in show()
└── Actions/BuildConversationHistory.php              # include video messages, append historyNote() (research R7)

config/ai.php                                         # + video_gen block (contracts/api.md Environment)
routes/api.php                                        # + video-gen-providers/models routes, settings.selectVideoGenModel
routes/channels.php                                   # + user.{userId}
database/migrations/
├── xxxx_create_video_gen_providers_table.php         # ← create_image_gen_providers_table (prompt as json from the start)
├── xxxx_create_video_gen_models_table.php            # ← create_image_gen_models_table
└── xxxx_add_generation_columns_to_videos_table.php
database/factories/{VideoGenProvider,VideoGenModel,Video}Factory.php

resources/js/
├── pages/VideoGenProvidersPage.jsx                   # ← pages/ImageGenProvidersPage
├── components/VideoGenProviderAccordion.jsx          # ← components/ImageGenProviderAccordion
├── components/VideoGenModelAccordion.jsx             # ← components/ImageGenModelAccordion
├── components/MessageVideo.jsx                       # placeholder / <video controls> / failure, styled like the image block in ChatMessage
├── components/ChatMessage.jsx                        # render <MessageVideo> where msg.image renders
├── components/ToastContainer.jsx                     # optional action button (OPEN)
├── hooks/useVideoGenProviders.js                     # ← hooks/useImageGenProviders
├── hooks/useConversationVideos.js                    # ← hooks/useAvatarBackground
├── hooks/useVideoGenerationNotices.js                # user channel → addToast with action
├── hooks/useToast.js                                 # carry an optional action
├── hooks/useConversationChat.js                      # map msg.video; /create-video response; placeholders for generate_video tool results
├── layouts/AuthenticatedLayout.jsx                   # keep the user id from user.show, mount useVideoGenerationNotices
├── utils/assistantMenu.jsx                           # + { label: 'Video Gen', icon: Video }
└── app.jsx                                           # + video-gen-providers route

tests/Feature/
├── VideoGenProviderControllerTest.php                # ← image gen provider/model tests
├── CreateVideoCommandTest.php
├── VideoGenerationToolTest.php
├── PollVideoGenerationTest.php
└── VideoHistoryTest.php
```

**Structure Decision**: Laravel API + React SPA in the existing directories. Every new backend and frontend file sits next to its image-generation counterpart and copies its structure, naming, validation and Tailwind classes. The background job, the broadcasts and the toast action are the only parts with no image counterpart; they follow `GenerateAvatarBackground`, `AvatarBackgroundStatusUpdated` and `useAvatarBackground`.

## Complexity Tracking

No constitution violations.
