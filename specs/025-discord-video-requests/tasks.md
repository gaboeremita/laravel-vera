---

description: "Task list for Discord video requests"
---

# Tasks: Discord Video Requests

**Input**: Design documents from `specs/025-discord-video-requests/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/discord-messages.md, contracts/discord-video-delivery.md, quickstart.md

**Tests**: Included. CLAUDE.md requires every change to be tested, and the constitution (Principle VI) requires factory-backed Pest feature tests. Per CLAUDE.md, the test suite, Pint and ESLint run once, when the owner says it is time to push (T019).

**Organization**: Tasks are grouped by user story, in the order US1 → US2 → US3 → US4.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1–US4)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Configuration for the delivery request

- [X] T001 Add `'delivery_timeout' => (int) env('DISCORD_API_DELIVERY_TIMEOUT', 180),` to the `discord` block in `config/ai.php`, after `timeout`. Add `DISCORD_API_DELIVERY_TIMEOUT=180` to `.env.example` next to the other `DISCORD_API_*` keys.

---

## Phase 2: User Story 1 - Request a video from Discord (Priority: P1) 🎯 MVP

**Goal**: `/create-video` from Discord starts a video and answers with the in-character reply.

**Independent Test**: quickstart.md end-to-end scenarios 1, 3, 4 and 5, plus the US1 tests below.

### Tests for User Story 1

- [X] T002 [US1] Create `tests/Feature/DiscordCreateVideoTest.php`. Use `RefreshDatabase`, `Queue::fake()`, `setUpAgentAssistant('assistant')` and `configureVideoGenModel()` from `tests/Pest.php`, and post to `/api/assistants/{id}/discord-messages` with `actingAs($user)` as `tests/Feature/DiscordVoiceMessageTest.php` does. Fake the LLM with `Http::fake` the way `tests/Feature/CreateVideoCommandTest.php` does for the description and the reply. Cover:
  - `/create-video a cat` with `channel_id` and `message_id` returns 200 with `content` only (no `video` key), creates an assistant message with a `queued` `Video`, and pushes `PollVideoGeneration`.
  - The user message stores the sent `message_id` as `discord_message_id`.
  - `/create-video` with no description returns 422 with `Describe what video to generate after /create-video.` and pushes nothing.
  - No video model returns 422 with `No video generation model is configured for this assistant.`
  - When the faked LLM answers the description request with a 500, the endpoint returns 502 with the error message, creates no `Video` and pushes nothing.
  - The in-character reply request to the LLM leaves out the emotion and pose tag sections: give the assistant a pose with `Pose::factory()` and `portrait_type` `avatar3d`, then assert `promptOfRequest()` does not contain `# POSE TAGS` or `# EMOTION TAGS`, as `tests/Feature/Api/ConversationPosePromptTest.php` asserts sections.

### Implementation for User Story 1

- [X] T003 [US1] In `app/Http/Controllers/Api/ConversationController.php`, `sendDiscordMessage()`:
  - Keep the `Image` returned by `Image::storeFromBase64($validated['images'][0], ...)` in `$attachedImage` (null when there is no image).
  - After the `/create-image` branch, add a `/create-video` branch mirroring the one in `chat()`: `extractVideoGenPrompt()`, the empty-description 422, `isAvailableFor()` and its 422, the `hasPublicUrl()` check for `$attachedImage` and its 422, then `startVideoMessage($request, $assistantUser, $conversation, $videoGenerationService, $videoGenPrompt, $attachedImage)` inside a `try`/`catch (\RuntimeException $e)` that returns 502 with the message.
  - Update the conversation title as the `/create-image` branch does, and return `response()->json(['content' => $started['content']])`.
- [X] T004 [US1] In `reactToStartedVideo()` in the same controller, add the `if ($conversation->discord_channel_id)` block from `reactToGeneratedImage()` that appends `'emotion tags'` and `'pose tags'` to `$excludedSections`, before the `PromptDirector` is built. Keep the comment wording from `reactToGeneratedImage()`.

**Checkpoint**: Discord requests start videos and get the in-character reply.

---

## Phase 3: User Story 2 - Start a Discord video from an attached image (Priority: P1)

**Goal**: An image attached to `/create-video` from Discord becomes the first frame.

**Independent Test**: quickstart.md end-to-end scenarios 6 and 7, plus the US2 tests below.

### Tests for User Story 2

- [X] T005 [US2] Add to `tests/Feature/DiscordCreateVideoTest.php`:
  - With `config(['ai.video_gen.public_url' => 'https://tunnel.test'])` and `Storage::fake('public')`, `/create-video` with `images: [<base64 png>]` creates the `Video` with `first_frame_image_id` set to the `Image` stored on the user message.
  - With `public_url` null, the same request returns 422 with `Set PUBLIC_TUNNEL_URL to generate a video from an image.` and pushes nothing.

### Implementation for User Story 2

- [X] T006 [US2] Confirm T003 passes `$attachedImage` to `startVideoMessage()` and runs the `hasPublicUrl()` check before it. Fix `sendDiscordMessage()` in `app/Http/Controllers/Api/ConversationController.php` if the US2 tests fail.

**Checkpoint**: First-frame videos work from Discord.

---

## Phase 4: User Story 3 - Hand the finished video to Discord (Priority: P1)

**Goal**: Each Discord video's outcome is sent to the Discord API service with the assistant, channel, reply target and video address or failure reason.

**Independent Test**: quickstart.md end-to-end scenario 2, plus the US3 tests below.

### Tests for User Story 3

- [X] T007 [US3] Create `tests/Feature/DeliverVideoToDiscordTest.php`. Use `RefreshDatabase` and `Http::fake()`, and set `config(['ai.discord.api_url' => 'http://discord-api.test', 'ai.discord.api_secret' => 'secret'])`. Build the conversation with `discord_channel_id`, a user message with `discord_message_id`, and an assistant message carrying `Video::factory()->completed()`. Call `app(DeliverVideoToDiscord::class)->handle(new VideoGenerationFinished($video))` directly. Cover:
  - It POSTs to `http://discord-api.test/assistants/{assistantId}/channels/{channelId}/videos` with the `X-Internal-Secret: secret` header and body `{replyToMessageId, videoUrl}`, where `videoUrl` equals `$video->url`.
  - A `Video::factory()->failed('Provider error')` sends `{replyToMessageId, failureReason: 'Provider error'}` with no `videoUrl`.
  - `replyToMessageId` is the `discord_message_id` of the user message just before the video's message, even when an older user message in the same conversation has a different one.
  - `replyToMessageId` is null when that user message has none.
  - Two request/reply pairs in the same conversation, each reply carrying its own video: each delivery's `replyToMessageId` is the `discord_message_id` of the request just before that video's message.
  - `shouldQueue()` is false for a conversation without `discord_channel_id`.
  - `handle()` sends nothing when the conversation was deleted after the event was created.

### Implementation for User Story 3

- [X] T008 [US3] Add `public int $videoId;` to `app/Events/VideoGenerationFinished.php`, set from `$video->id` in the constructor, next to `conversationId`.
- [X] T009 [US3] Create `app/Listeners/DeliverVideoToDiscord.php` (auto-discovered like `app/Listeners/UnlockQuests.php`; no registration). Make it implement `ShouldQueue` and use `InteractsWithQueue`. Give it:
  - `shouldQueue(VideoGenerationFinished $event): bool`, true only when `Conversation::whereKey($event->conversationId)->whereNotNull('discord_channel_id')->exists()` (FR-010).
  - A private `replyTarget(Video $video): ?string` returning the `discord_message_id` of the latest `role = 'user'` message in the same conversation with an `id` lower than `$video->videoable_id` (research R4). Add one comment above it saying the earlier user message is reliable because Discord requests arrive one at a time.
  - `handle(VideoGenerationFinished $event): void`:
    - Load `Video::find($event->videoId)` and `Conversation::find($event->conversationId)`; return when either is null.
    - Read `config('ai.discord')` and POST with `Http::timeout($apiConfig['delivery_timeout'])->withHeaders(['X-Internal-Secret' => $apiConfig['api_secret']])` to `{api_url}/assistants/{assistantId}/channels/{discord_channel_id}/videos`, where `assistantId` is `$conversation->assistantUser()->assistant_id`.
    - Send `replyToMessageId` from `replyTarget()`, plus `videoUrl` (`$video->url`) when `$video->status === VideoStatus::Completed`, or `failureReason` (`$video->failure_reason`) otherwise.
    - Return on a 2xx answer. On any other answer, throw a `RuntimeException` with the status and body. A connection exception propagates as it is. US4 refines which answers stop instead of retrying.

**Checkpoint**: Every Discord video's outcome is sent once.

---

## Phase 5: User Story 4 - Retry delivery when the Discord API service is unavailable (Priority: P2)

**Goal**: Delivery retries on an unavailable service and stops on a refused post.

**Independent Test**: quickstart.md end-to-end scenarios 8 and 9, plus the US4 tests below.

### Tests for User Story 4

- [X] T010 [US4] Add to `tests/Feature/DeliverVideoToDiscordTest.php`:
  - `backoff()` returns `[10, 30, 60, 120, 300]`, `$tries` is 6, and `$timeout` is 200.
  - A connection exception (`Http::fake(fn () => throw new ConnectionException('down'))`), a 404, a 502 and a 500 each make `handle()` throw.
  - A 422 and a 401 do not throw. They call `fail()` on the job once with an exception carrying the status and body: give the listener a mocked job with `$listener->setJob($job)` and expect `fail` once.
  - `failed($event, $exception)` logs one error, `Discord video delivery failed`, with `video_id`, `conversation_id` and the exception message (use `Log::spy()`).

### Implementation for User Story 4

- [X] T011 [US4] In `app/Listeners/DeliverVideoToDiscord.php`:
  - Add `public int $tries = 6;`, `public int $timeout = 200;` and `backoff(): array` returning `[10, 30, 60, 120, 300]`.
  - On 401 or 422, call `$this->fail(new RuntimeException(...))` with the status and body, and return. Every other non-2xx answer keeps throwing from T009, so the queue retries.
  - Add `failed(VideoGenerationFinished $event, Throwable $exception): void` that logs `Discord video delivery failed` with `video_id`, `conversation_id` and the exception message. It is the only place delivery failures are logged, for both refused posts and exhausted retries (FR-012, FR-013).

**Checkpoint**: Every Discord video ends with a delivery or a logged failure.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T012 [P] Update `README.md`: in the Discord section, document `/create-video` from Discord, the first-frame rule, and `DISCORD_API_DELIVERY_TIMEOUT`. Add `DeliverVideoToDiscord.php` to the Project Structure tree under a `Listeners/` entry.
- [X] T013 [P] Update `ARCHITECTURE.md`: add the delivery flow (`VideoGenerationFinished` → `DeliverVideoToDiscord` → `POST /assistants/{assistantId}/channels/{channelId}/videos`) next to the discovery call, and the `/create-video` branch in the Discord message flow.
- [X] T014 Run `php -l` on `app/Listeners/DeliverVideoToDiscord.php`, `app/Events/VideoGenerationFinished.php` and `app/Http/Controllers/Api/ConversationController.php` (parse checks only, per CLAUDE.md).
- [ ] T015 Walk through quickstart.md end-to-end scenarios 1–9 with the owner.
- [X] T016 When the owner says it is time to push: run `vendor/bin/pint --dirty --format agent`, `npm run lint` and `php artisan test --compact` once, and fix everything they surface.

---

## Dependencies & Execution Order

- **Setup (T001)**: no dependencies.
- **US1 (T002–T004)**: after Setup. T003 → T004 (same file).
- **US2 (T005–T006)**: after T003.
- **US3 (T007–T009)**: after Setup. T008 → T009. Independent of US1 and US2.
- **US4 (T010–T011)**: after T009.
- **Polish (T012–T016)**: after the stories it documents. T016 runs last, only on the owner's instruction.

### Parallel opportunities

- T002 and T007 (different test files).
- US1/US2 (controller) and US3/US4 (event and listener).
- T012 and T013 (different files).

## Implementation Strategy

1. **MVP**: Setup and US1. A Discord `/create-video` starts a video and gets its reply.
2. Add US2 (first frame), US3 (delivery), then US4 (retries).
3. Documentation, parse checks, the owner's run-through, then the gates once at push time.
